<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use LibreNMS\Plugins\LibreLiveTopology\Services\AutoDiscoveryService;

/**
 * End-to-end tests for the LLDP/CDP auto-discovery path. A real sqlite
 * in-memory database is used so DB::table(...) (LibreNMS devices/ports/links)
 * and the Eloquent llt_* models resolve against the same connection.
 */
class AutoDiscoveryTest extends TestCase
{
    private AutoDiscoveryService $service;

    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension not available');
            return;
        }

        // Fresh, isolated in-memory schema per test. Build the Capsule on the
        // bootstrap container so the DB facade, Eloquent models, and the
        // capsule all resolve the same "db" connection manager.
        $app = Facade::getFacadeApplication();
        $this->capsule = new Capsule($app);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->capsule->bootEloquent();

        // DatabaseManager does not self-register; bind it as "db" on the shared
        // container so the DB facade resolves it (Eloquent already resolves the
        // same manager via bootEloquent's connection resolver).
        $app->instance('db', $this->capsule->getDatabaseManager());
        Facade::clearResolvedInstance('db');

        $this->createSchema($this->capsule);

        $this->service = new AutoDiscoveryService();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('db');
        parent::tearDown();
    }

    private function createSchema(Capsule $capsule): void
    {
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

        // LibreNMS core tables (non-llt_).
        $schema->create('devices', function ($t) {
            $t->increments('device_id');
            $t->string('hostname');
            $t->string('sysName')->nullable();
            $t->string('os')->default('');
            $t->boolean('disabled')->default(0);
            $t->boolean('ignore')->default(0);
            $t->boolean('status')->default(1);
        });

        $schema->create('ports', function ($t) {
            $t->increments('port_id');
            $t->unsignedInteger('device_id');
            $t->unsignedInteger('ifIndex');
            $t->string('ifDescr')->nullable();
            $t->string('ifOperStatus')->default('up');
            $t->string('ifAdminStatus')->default('up');
        });

        // LibreNMS topology (LLDP/XDP/CDP) links table.
        $schema->create('links', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('local_device_id');
            $t->unsignedInteger('local_port_id');
            $t->string('protocol')->default('lldp');
            $t->string('remote_hostname')->nullable();
            $t->unsignedInteger('remote_device_id');
            $t->unsignedInteger('remote_port_id');
        });
    }

    private function createMap(int $id): Map
    {
        $map = Map::find($id);
        return $map;
    }

    private function seedDevice(int $id, string $hostname, string $os = 'linux'): void
    {
        $this->capsule->getConnection()->table('devices')->insert([
            'device_id' => $id,
            'hostname' => $hostname,
            'os' => $os,
            'disabled' => 0,
            'ignore' => 0,
        ]);
    }

    private function seedPort(int $deviceId, int $portId, int $ifIndex): void
    {
        $this->capsule->getConnection()->table('ports')->insert([
            'port_id' => $portId,
            'device_id' => $deviceId,
            'ifIndex' => $ifIndex,
            'ifDescr' => "eth{$ifIndex}",
            'ifOperStatus' => 'up',
            'ifAdminStatus' => 'up',
        ]);
    }

    private function seedTopologyLink(
        int $localDevice,
        int $localPort,
        int $remoteDevice,
        int $remotePort,
        string $protocol = 'lldp'
    ): void {
        $this->capsule->getConnection()->table('links')->insert([
            'local_device_id' => $localDevice,
            'local_port_id' => $localPort,
            'protocol' => $protocol,
            'remote_device_id' => $remoteDevice,
            'remote_port_id' => $remotePort,
        ]);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_validate_normalizes_min_degree_and_os_list(): void
    {
        $params = $this->service->validateDiscoveryParams([
            'min_degree' => '-3',
            'os' => ' linux , ios , junos ',
        ]);

        $this->assertEquals(0, $params['minDegree']);
        $this->assertEquals(['linux', 'ios', 'junos'], $params['osFilter']);
    }

    public function test_validate_clamps_negative_min_degree_to_zero(): void
    {
        $params = $this->service->validateDiscoveryParams(['min_degree' => '-5']);
        $this->assertEquals(0, $params['minDegree']);
    }

    // ------------------------------------------------------------------
    // Discovery
    // ------------------------------------------------------------------

    public function test_discover_seeds_nodes_and_links_from_topology(): void
    {
        Map::forceCreate(['id' => 1, 'name' => 'core', 'options' => []]);

        $this->seedDevice(10, 'core-a', 'ios');
        $this->seedDevice(20, 'core-b', 'ios');
        $this->seedDevice(30, 'edge', 'linux');

        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedPort(30, 300, 1);

        // Bidirectional LLDP entries for the (10,20) pair — must collapse to one link.
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
        $this->seedTopologyLink(20, 200, 10, 100, 'lldp');
        // CDP link from edge to core-a.
        $this->seedTopologyLink(30, 300, 10, 100, 'cdp');

        $summary = $this->service->discoverAndSeedMap(Map::find(1), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(3, $summary['nodes_added']);
        // 3 candidate devices paired by 3 topology rows, but (10,20) appears
        // twice (both directions), so 2 distinct device pairs yield 2 links.
        $this->assertEquals(2, $summary['links_added']);

        $this->assertSame(3, Node::where('map_id', 1)->count());

        $links = Link::where('map_id', 1)->get();
        $this->assertCount(2, $links);

        // Link ports must carry the topology port_ids.
        $pairByDevice = [];
        foreach ($links as $link) {
            $a = Node::find($link->src_node_id)->device_id;
            $b = Node::find($link->dst_node_id)->device_id;
            $pairByDevice[($a < $b ? $a : $b) . '-' . ($a < $b ? $b : $a)] = $link;
        }

        $this->assertArrayHasKey('10-20', $pairByDevice);
        $link1020 = $pairByDevice['10-20'];
        $this->assertEquals(100, $link1020->port_id_a);
        $this->assertEquals(200, $link1020->port_id_b);

        $this->assertArrayHasKey('10-30', $pairByDevice);
        // Ordering of src/dst is min/max; ports on the 10-30 link are
        // 300 (edge) and 100 (core-a).
        $link1030 = $pairByDevice['10-30'];
        $this->assertContains($link1030->port_id_a, [100, 300]);
        $this->assertContains($link1030->port_id_b, [100, 300]);
    }

    public function test_discover_deduplicates_repeated_observations_of_the_same_ports(): void
    {
        Map::forceCreate(['id' => 2, 'name' => 'dedup', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // Ten duplicate topology rows for the same pair, in both directions.
        for ($i = 0; $i < 5; $i++) {
            $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
            $this->seedTopologyLink(20, 200, 10, 100, 'cdp');
        }

        $summary = $this->service->discoverAndSeedMap(Map::find(2), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);
        $this->assertSame(1, Link::where('map_id', 2)->count());
    }

    public function test_discover_skips_end_with_missing_port_but_keeps_link(): void
    {
        Map::forceCreate(['id' => 3, 'name' => 'missingport', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        // Device 10 has port 100; device 20 has NO matching port (row references
        // port 999 which does not exist).
        $this->seedPort(10, 100, 1);

        $this->seedTopologyLink(20, 999, 10, 100, 'lldp');

        $summary = $this->service->discoverAndSeedMap(Map::find(3), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);

        $link = Link::where('map_id', 3)->first();
        $this->assertNotNull($link);
        // The resolvable end (device 10, port 100) is kept.
        $this->assertEquals(100, $link->port_id_a);
        $this->assertNull($link->port_id_b);
    }

    public function test_discover_uses_topology_protocol_filter(): void
    {
        Map::forceCreate(['id' => 4, 'name' => 'proto', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // One supported protocol and one that must be ignored (e.g. ospf).
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');
        $this->seedTopologyLink(20, 200, 10, 100, 'ospf');

        $summary = $this->service->discoverAndSeedMap(Map::find(4), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(1, $summary['links_added']);
    }

    public function test_discover_with_no_candidate_devices_returns_empty_counts(): void
    {
        Map::forceCreate(['id' => 5, 'name' => 'nocand', 'options' => []]);

        // Only disabled/ignored devices exist.
        $this->capsule->getConnection()->table('devices')->insert([
            'device_id' => 50,
            'hostname' => 'disabled-host',
            'os' => 'linux',
            'disabled' => 1,
            'ignore' => 0,
        ]);

        $summary = $this->service->discoverAndSeedMap(Map::find(5), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(['nodes_added' => 0, 'links_added' => 0], $summary);
        $this->assertSame(0, Node::where('map_id', 5)->count());
        $this->assertSame(0, Link::where('map_id', 5)->count());
    }

    public function test_discover_with_empty_topology_returns_zero_links(): void
    {
        Map::forceCreate(['id' => 6, 'name' => 'notopo', 'options' => []]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);

        // No rows in the LibreNMS links table at all.
        $summary = $this->service->discoverAndSeedMap(Map::find(6), [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(2, $summary['nodes_added']);
        $this->assertEquals(0, $summary['links_added']);
        $this->assertSame(2, Node::where('map_id', 6)->count());
        $this->assertSame(0, Link::where('map_id', 6)->count());
    }

    public function test_discover_skips_existing_preexisting_nodes(): void
    {
        $map = Map::forceCreate(['id' => 7, 'name' => 'existing', 'options' => []]);
        Node::create([
            'map_id' => 7,
            'label' => 'a',
            'x' => 100,
            'y' => 100,
            'device_id' => 10,
            'meta' => [],
        ]);

        $this->seedDevice(10, 'a', 'linux');
        $this->seedDevice(20, 'b', 'linux');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedTopologyLink(10, 100, 20, 200, 'lldp');

        $summary = $this->service->discoverAndSeedMap($map, [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        // Only device 20 is new; device 10 already has a node.
        $this->assertEquals(1, $summary['nodes_added']);
        $this->assertEquals(1, $summary['links_added']);

        $repeatSummary = $this->service->discoverAndSeedMap($map, [
            'minDegree' => 0,
            'osFilter' => [],
        ]);

        $this->assertEquals(0, $repeatSummary['nodes_added']);
        $this->assertEquals(0, $repeatSummary['links_added']);
        $this->assertSame(1, Link::where('map_id', 7)->count());
    }

    public function test_discovery_keeps_parallel_circuits_and_is_idempotent(): void
    {
        $map = Map::forceCreate(['id' => 8, 'name' => 'parallel', 'options' => []]);
        $this->seedDevice(10, 'core');
        $this->seedDevice(20, 'edge');
        foreach ([1, 2] as $index) {
            $this->seedPort(10, 100 + $index, $index);
            $this->seedPort(20, 200 + $index, $index);
            $this->seedTopologyLink(10, 100 + $index, 20, 200 + $index);
            $this->seedTopologyLink(20, 200 + $index, 10, 100 + $index, 'cdp');
        }
        $this->seedTopologyLink(10, 101, 20, 0);

        $summary = $this->service->discoverAndSeedMap($map, []);
        $this->assertSame(['nodes_added' => 2, 'links_added' => 2], $summary);
        $links = Link::where('map_id', 8)->orderBy('port_id_a')->get();
        $this->assertSame([101, 102], $links->pluck('port_id_a')->all());
        $this->assertSame([201, 202], $links->pluck('port_id_b')->all());

        $this->assertSame(['nodes_added' => 0, 'links_added' => 0], (new AutoDiscoveryService())->discoverAndSeedMap($map, []));
        $this->assertSame(2, Link::where('map_id', 8)->count());
    }

    public function test_discovery_preserves_manual_nodes_and_places_additions_in_free_space(): void
    {
        $map = Map::forceCreate(['id' => 9, 'name' => 'positions', 'options' => ['background' => '#223344']]);
        $existing = Node::create([
            'map_id' => 9, 'device_id' => 10, 'label' => 'Custom core label',
            'x' => 100, 'y' => 100, 'meta' => ['icon' => 'router', 'color' => '#abcdef'],
        ]);
        $manual = Node::create([
            'map_id' => 9, 'device_id' => null, 'label' => 'Internet',
            'x' => 280, 'y' => 100, 'meta' => ['icon' => 'cloud'],
        ]);
        $existingAttributes = $existing->fresh()->getAttributes();
        $manualAttributes = $manual->fresh()->getAttributes();
        $this->seedDevice(10, 'core');
        $this->seedDevice(20, 'edge');

        $this->service->discoverAndSeedMap($map, []);
        $newNode = Node::where('map_id', 9)->where('device_id', 20)->firstOrFail();
        $this->assertSame(460.0, $newNode->x);
        $this->assertSame(100.0, $newNode->y);
        $this->assertSame($existingAttributes, $existing->fresh()->getAttributes());
        $this->assertSame($manualAttributes, $manual->fresh()->getAttributes());

        $this->seedDevice(30, 'new-edge');
        (new AutoDiscoveryService())->discoverAndSeedMap($map, []);
        $nextNode = Node::where('map_id', 9)->where('device_id', 30)->firstOrFail();
        $this->assertSame(640.0, $nextNode->x);
        $this->assertSame($newNode->getAttributes(), $newNode->fresh()->getAttributes());
        $this->assertSame('#223344', $map->fresh()->options['background']);
    }

    public function test_discovery_enriches_reversed_existing_circuit_without_changing_its_style(): void
    {
        $map = Map::forceCreate(['id' => 10, 'name' => 'enrichment', 'options' => []]);
        $a = Node::create(['map_id' => 10, 'device_id' => 10, 'label' => 'a', 'x' => 100, 'y' => 100, 'meta' => []]);
        $b = Node::create(['map_id' => 10, 'device_id' => 20, 'label' => 'b', 'x' => 400, 'y' => 100, 'meta' => []]);
        $style = ['color' => '#123456', 'via_points' => [['x' => 250, 'y' => 300]]];
        $existing = Link::create([
            'map_id' => 10, 'src_node_id' => $b->id, 'dst_node_id' => $a->id,
            'port_id_a' => 200, 'port_id_b' => null, 'bandwidth_bps' => 1000000000, 'style' => $style,
        ]);
        $this->seedDevice(10, 'core');
        $this->seedDevice(20, 'edge');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedTopologyLink(10, 100, 20, 200);

        $this->assertSame(['nodes_added' => 0, 'links_added' => 0], $this->service->discoverAndSeedMap($map, []));
        $existing->refresh();
        $this->assertSame($b->id, $existing->src_node_id);
        $this->assertSame($a->id, $existing->dst_node_id);
        $this->assertSame(200, $existing->port_id_a);
        $this->assertSame(100, $existing->port_id_b);
        $this->assertSame(1000000000, $existing->bandwidth_bps);
        $this->assertSame($style, $existing->style);
        $this->assertSame(1, Link::where('map_id', 10)->count());
    }

    public function test_discovery_resolves_existing_neighbor_ports_outside_the_os_filter(): void
    {
        $map = Map::forceCreate(['id' => 11, 'name' => 'filtered', 'options' => []]);
        Node::create(['map_id' => 11, 'device_id' => 20, 'label' => 'Existing neighbor', 'x' => 100, 'y' => 100, 'meta' => []]);
        $this->seedDevice(10, 'core', 'ios');
        $this->seedDevice(20, 'edge', 'junos');
        $this->seedDevice(30, 'filtered-out', 'junos');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedPort(30, 300, 1);
        $this->seedTopologyLink(20, 200, 10, 100);
        $this->seedTopologyLink(10, 100, 30, 300);

        $summary = $this->service->discoverAndSeedMap($map, ['osFilter' => ['ios'], 'minDegree' => 0]);

        $this->assertSame(['nodes_added' => 1, 'links_added' => 1], $summary);
        $link = Link::where('map_id', 11)->firstOrFail();
        $this->assertSame(100, $link->port_id_a);
        $this->assertSame(200, $link->port_id_b);
        $this->assertFalse(Node::where('map_id', 11)->where('device_id', 30)->exists());
    }

    public function test_min_degree_counts_distinct_known_neighbors_instead_of_active_ports(): void
    {
        $map = Map::forceCreate(['id' => 12, 'name' => 'degree', 'options' => []]);
        foreach ([10, 20, 30, 40] as $id) {
            $this->seedDevice($id, "device-{$id}");
            $this->seedPort($id, $id * 10, 1);
        }
        $this->seedPort(40, 401, 2);
        $this->seedPort(40, 402, 3);
        $this->seedTopologyLink(10, 100, 20, 200);
        $this->seedTopologyLink(20, 200, 10, 100, 'cdp');
        $this->seedTopologyLink(10, 100, 30, 300);
        $this->seedTopologyLink(20, 200, 99, 999);
        $this->seedTopologyLink(20, 200, 20, 200);

        $summary = $this->service->discoverAndSeedMap($map, ['minDegree' => 2]);

        $this->assertSame(['nodes_added' => 1, 'links_added' => 0], $summary);
        $this->assertSame([10], Node::where('map_id', 12)->pluck('device_id')->all());
    }

    public function test_new_discovery_is_placed_near_its_existing_neighbor_without_moving_it(): void
    {
        $map = Map::forceCreate(['id' => 16, 'name' => 'near-neighbor', 'options' => ['width' => 1800, 'height' => 1800]]);
        $this->seedDevice(10, 'core');
        $this->seedDevice(20, 'access');
        $core = Node::forceCreate(['map_id' => 16, 'device_id' => 10, 'label' => 'My core', 'x' => 1180, 'y' => 1180]);
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedTopologyLink(10, 100, 20, 200);

        $this->service->discoverAndSeedMap($map, []);

        $newNode = Node::where('map_id', 16)->where('device_id', 20)->firstOrFail();
        $this->assertEquals(1180, $core->fresh()->x);
        $this->assertEquals(1180, $core->fresh()->y);
        $this->assertEquals(1180, $newNode->x);
        $this->assertEquals(1360, $newNode->y);
    }

    public function test_discovery_grows_canvas_without_changing_other_options(): void
    {
        $map = Map::forceCreate(['id' => 13, 'name' => 'canvas', 'options' => ['width' => 800, 'height' => 600, 'tags' => ['core']]]);
        for ($id = 1; $id <= 25; $id++) {
            $this->seedDevice($id, "device-{$id}");
        }

        $this->service->discoverAndSeedMap($map, []);

        $options = $map->fresh()->options;
        $this->assertSame(['core'], $options['tags']);
        $this->assertLessThanOrEqual(4096, $options['width']);
        $this->assertLessThanOrEqual(4096, $options['height']);
        $this->assertGreaterThan(800, $options['width']);
        foreach ($map->nodes()->get() as $node) {
            $this->assertLessThanOrEqual($options['width'] - 100, $node->x);
            $this->assertLessThanOrEqual($options['height'] - 100, $node->y);
        }
    }

    public function test_discovery_rolls_back_if_the_canvas_has_no_free_positions(): void
    {
        $map = Map::forceCreate(['id' => 14, 'name' => 'full', 'options' => ['width' => 800, 'height' => 600]]);
        $this->seedDevice(10, 'one-more-device');
        $nodes = [];
        for ($row = 0; $row < 22; $row++) {
            for ($column = 0; $column < 22; $column++) {
                $nodes[] = ['map_id' => 14, 'label' => 'Manual', 'x' => 100 + $column * 180, 'y' => 100 + $row * 180];
            }
        }
        foreach (array_chunk($nodes, 100) as $batch) {
            $this->capsule->getConnection()->table('llt_nodes')->insert($batch);
        }

        try {
            $this->service->discoverAndSeedMap($map, []);
            $this->fail('A full canvas should reject discovery.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('free canvas space', $e->getMessage());
        }

        $this->assertSame(484, Node::where('map_id', 14)->count());
        $this->assertSame(['width' => 800, 'height' => 600], $map->fresh()->options);
    }

    public function test_failed_link_import_rolls_back_added_nodes_and_canvas_growth(): void
    {
        $map = Map::forceCreate(['id' => 15, 'name' => 'rollback', 'options' => ['width' => 100, 'height' => 100]]);
        $this->seedDevice(10, 'a');
        $this->seedDevice(20, 'b');
        $this->seedPort(10, 100, 1);
        $this->seedPort(20, 200, 1);
        $this->seedTopologyLink(10, 100, 20, 200);
        $this->capsule->getConnection()->getSchemaBuilder()->drop('llt_links');

        try {
            $this->service->discoverAndSeedMap($map, []);
            $this->fail('The missing destination table should fail the import.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('llt_links', $e->getMessage());
        }

        $this->assertSame(0, Node::where('map_id', 15)->count());
        $this->assertSame(['width' => 100, 'height' => 100], $map->fresh()->options);
    }
}
