<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\MapVersion;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapVersionService;

/**
 * Snapshot retention and the automatic backup taken before a restore,
 * exercised against an in-memory sqlite database.
 */
class MapVersionRetentionTest extends TestCase
{
    private Map $map;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
            return;
        }

        $app = Facade::getFacadeApplication();
        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->bootEloquent();
        $app->instance('db', $capsule->getDatabaseManager());
        Facade::clearResolvedInstance('db');

        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('llt_maps', function ($t) {
            $t->increments('id');
            $t->string('name');
            $t->string('title')->nullable();
            $t->text('options')->nullable();
            $t->timestamps();
        });
        $schema->create('llt_nodes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->string('label');
            $t->float('x');
            $t->float('y');
            $t->unsignedInteger('device_id')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });
        $schema->create('llt_links', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->unsignedInteger('src_node_id');
            $t->unsignedInteger('dst_node_id');
            $t->unsignedInteger('port_id_a')->nullable();
            $t->unsignedInteger('port_id_b')->nullable();
            $t->bigInteger('bandwidth_bps')->nullable();
            $t->text('style')->nullable();
            $t->timestamps();
        });
        $schema->create('llt_map_versions', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('map_id');
            $t->string('name')->nullable();
            $t->text('description')->nullable();
            $t->text('config_snapshot');
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $this->map = Map::create(['name' => 'retention', 'title' => 'Retention', 'options' => []]);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        parent::tearDown();
    }

    private function serviceWithRetention(int $limit): MapVersionService
    {
        return new class ($limit) extends MapVersionService {
            public function __construct(private int $limit)
            {
            }

            public function retentionLimit(): int
            {
                return $this->limit;
            }
        };
    }

    private function versionNames(): array
    {
        return MapVersion::where('map_id', $this->map->id)->orderBy('id')->pluck('name')->all();
    }

    public function test_create_version_prunes_oldest_beyond_retention(): void
    {
        $service = $this->serviceWithRetention(3);

        foreach (['v1', 'v2', 'v3', 'v4', 'v5'] as $name) {
            $service->createVersion($this->map->fresh(), $name);
        }

        $this->assertSame(['v3', 'v4', 'v5'], $this->versionNames());
    }

    public function test_versions_created_in_the_same_second_list_newest_first(): void
    {
        $service = $this->serviceWithRetention(20);

        foreach (['first', 'second', 'third'] as $name) {
            $service->createVersion($this->map->fresh(), $name);
        }

        // getVersions() adds the LibreNMS creator relation, so check its ordering scope directly.
        $this->assertSame(['third', 'second', 'first'], MapVersion::versions($this->map->id, 20)->pluck('name')->all());
        $this->assertSame('third', $service->getLatestVersion($this->map)->name);
    }

    public function test_zero_retention_keeps_every_version(): void
    {
        $service = $this->serviceWithRetention(0);

        foreach (['v1', 'v2', 'v3', 'v4'] as $name) {
            $service->createVersion($this->map->fresh(), $name);
        }

        $this->assertCount(4, $this->versionNames());
        $this->assertSame(MapVersionService::UNLIMITED_LIST_LIMIT, $service->listLimit());
    }

    public function test_pruning_is_scoped_to_one_map(): void
    {
        $service = $this->serviceWithRetention(1);
        $other = Map::create(['name' => 'other', 'title' => 'Other', 'options' => []]);

        $service->createVersion($other, 'other-1');
        $service->createVersion($this->map->fresh(), 'v1');
        $service->createVersion($this->map->fresh(), 'v2');

        $this->assertSame(['v2'], $this->versionNames());
        $this->assertSame(1, MapVersion::where('map_id', $other->id)->count());
    }

    public function test_restore_saves_current_state_as_backup_first(): void
    {
        $service = $this->serviceWithRetention(20);
        Node::create(['map_id' => $this->map->id, 'label' => 'core', 'x' => 10, 'y' => 10]);
        $target = $service->createVersion($this->map->fresh(), 'one node', null, 5);

        Node::create(['map_id' => $this->map->id, 'label' => 'edge', 'x' => 50, 'y' => 50]);

        $service->restoreVersion($target->fresh(), 7);

        $this->assertSame(['core'], Node::where('map_id', $this->map->id)->pluck('label')->all());

        $backup = MapVersion::where('map_id', $this->map->id)->orderByDesc('id')->first();
        $this->assertSame('Before restore: one node', $backup->name);
        $this->assertSame(7, (int) $backup->created_by);
        $labels = array_column(array_values($backup->config_snapshot['nodes']), 'label');
        sort($labels);
        $this->assertSame(['core', 'edge'], $labels);
    }

    public function test_restore_never_prunes_the_restored_version(): void
    {
        $service = $this->serviceWithRetention(1);
        $target = $service->createVersion($this->map->fresh(), 'only');

        $service->restoreVersion($target->fresh());

        $names = $this->versionNames();
        $this->assertContains('only', $names);
        $this->assertContains('Before restore: only', $names);
    }

    public function test_restore_without_backup_adds_no_version(): void
    {
        $service = $this->serviceWithRetention(20);
        $target = $service->createVersion($this->map->fresh(), 'plain');

        $service->restoreVersion($target->fresh(), null, false);

        $this->assertSame(['plain'], $this->versionNames());
    }
}
