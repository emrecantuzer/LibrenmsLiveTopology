<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapAccess;

/**
 * Map visibility follows LibreNMS device permissions: a map is readable only
 * when every device it references (node devices and link port devices) is
 * accessible to the user.
 */
class MapAccessTest extends TestCase
{
    private Map $open;
    private Map $mixed;
    private Map $portOnly;
    private Map $empty;

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
        $schema->create('ports', function ($t) {
            $t->increments('port_id');
            $t->unsignedInteger('device_id');
        });

        DB::table('ports')->insert([['port_id' => 100, 'device_id' => 1], ['port_id' => 200, 'device_id' => 2]]);

        // Devices 1 only.
        $this->open = $this->mapWithNodes('open', [1, null]);
        // Devices 1 and 2.
        $this->mixed = $this->mapWithNodes('mixed', [1, 2]);
        // Unbound nodes, but a link port that lives on device 2.
        $this->portOnly = $this->mapWithNodes('port-only', [null, null]);
        DB::table('llt_links')->insert([
            'map_id' => $this->portOnly->id, 'src_node_id' => 1, 'dst_node_id' => 2, 'port_id_a' => 200,
        ]);
        // No devices at all.
        $this->empty = Map::create(['name' => 'empty', 'options' => []]);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        parent::tearDown();
    }

    private function mapWithNodes(string $name, array $deviceIds): Map
    {
        $map = Map::create(['name' => $name, 'options' => []]);
        foreach ($deviceIds as $i => $deviceId) {
            DB::table('llt_nodes')->insert([
                'map_id' => $map->id, 'label' => "n{$i}", 'x' => 0, 'y' => 0, 'device_id' => $deviceId,
            ]);
        }
        return $map;
    }

    private function access(string $mode, array $allowedDevices, bool $privileged = false): MapAccess
    {
        return new class ($mode, $allowedDevices, $privileged) extends MapAccess {
            public function __construct(private string $m, private array $allowed, private bool $privileged)
            {
            }

            public function mode(): string
            {
                return $this->m;
            }

            protected function isPrivileged(object $user): bool
            {
                return $this->privileged;
            }

            protected function allowedDeviceIds(object $user): array
            {
                return $this->allowed;
            }
        };
    }

    private function user(): object
    {
        return (object) ['user_id' => 42];
    }

    public function test_devices_mode_hides_maps_with_inaccessible_devices(): void
    {
        $visible = $this->access(MapAccess::MODE_DEVICES, [1])->visibleMapIds($this->user());

        sort($visible);
        $this->assertSame([$this->open->id, $this->empty->id], $visible);
    }

    public function test_link_port_devices_count_toward_access(): void
    {
        $access = $this->access(MapAccess::MODE_DEVICES, [1]);
        $this->assertFalse($access->canView($this->portOnly, $this->user()));

        $access = $this->access(MapAccess::MODE_DEVICES, [1, 2]);
        $this->assertTrue($access->canView($this->portOnly, $this->user()));
        $this->assertTrue($access->canView($this->mixed, $this->user()));
    }

    public function test_user_without_devices_sees_only_device_free_maps(): void
    {
        $visible = $this->access(MapAccess::MODE_DEVICES, [])->visibleMapIds($this->user());

        $this->assertSame([$this->empty->id], $visible);
    }

    public function test_privileged_users_are_unrestricted(): void
    {
        $access = $this->access(MapAccess::MODE_DEVICES, [], true);

        $this->assertNull($access->visibleMapIds($this->user()));
        $this->assertTrue($access->canView($this->mixed, $this->user()));
    }

    public function test_admin_mode_hides_everything_from_regular_users(): void
    {
        $this->assertSame([], $this->access(MapAccess::MODE_ADMIN, [1, 2])->visibleMapIds($this->user()));
        $this->assertNull($this->access(MapAccess::MODE_ADMIN, [], true)->visibleMapIds($this->user()));
    }

    public function test_all_mode_keeps_legacy_behavior(): void
    {
        $this->assertNull($this->access(MapAccess::MODE_ALL, [])->visibleMapIds($this->user()));
    }

    public function test_guests_see_nothing(): void
    {
        $previous = $GLOBALS['__test_auth_resolver'] ?? null;
        $GLOBALS['__test_auth_resolver'] = fn () => null;

        try {
            $this->assertSame([], $this->access(MapAccess::MODE_ALL, [1, 2])->visibleMapIds());
        } finally {
            $GLOBALS['__test_auth_resolver'] = $previous;
        }
    }

    public function test_unknown_mode_falls_back_to_devices(): void
    {
        $this->assertSame(MapAccess::MODE_DEVICES, (new MapAccess())->mode());
    }
}
