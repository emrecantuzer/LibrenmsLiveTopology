<?php
/**
 * LibreLiveTopology Demo Data Seeder
 *
 * Creates sample network topologies for testing and demonstration:
 * - demo-network: a small eight-device network.
 * - enterprise-datacenter: dual ISPs, MPLS branches, a firewall HA pair,
 *   a spine-leaf data center, and a campus network (31 devices, 40 links).
 *
 * Existing demo maps are left untouched. Delete a map to recreate it.
 *
 * Usage: php database/seed-demo.php
 */

// Determine LibreNMS path
$possiblePaths = [
    dirname(__DIR__, 3),
    '/opt/librenms',
    getenv('LIBRENMS_PATH') ?: '',
];

$libreNMSPath = null;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path . '/vendor/autoload.php')) {
        $libreNMSPath = $path;
        break;
    }
}

if (!$libreNMSPath) {
    echo "Error: Could not find LibreNMS installation.\n";
    exit(1);
}

require_once $libreNMSPath . '/vendor/autoload.php';
$app = require_once $libreNMSPath . '/bootstrap/app.php';

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Exception $e) {
    // Fall back to direct PDO
}

const GBPS = 1000000000;

/**
 * Demo map definitions. Nodes are keyed by a local name that links refer to;
 * `type` becomes meta.device_type so the viewer draws the right shape.
 */
$demoMaps = [
    [
        'name' => 'demo-network',
        'title' => 'Demo Network Topology',
        'description' => 'Sample network for testing LibreLiveTopology',
        'width' => 1200,
        'height' => 700,
        'tags' => [],
        'nodes' => [
            'core' => ['Core Router', 600, 80],
            'swa' => ['Switch-A', 300, 250],
            'swb' => ['Switch-B', 900, 250],
            'web' => ['Web Server', 150, 450],
            'db' => ['DB Server', 450, 450],
            'app' => ['App Server', 750, 450],
            'file' => ['File Server', 1050, 450],
            'fw' => ['Firewall', 600, 620],
        ],
        'links' => [
            ['core', 'swa', 10], ['core', 'swb', 10], ['swa', 'web', 1], ['swa', 'db', 1],
            ['swb', 'app', 1], ['swb', 'file', 1], ['core', 'fw', 10], ['swa', 'swb', 10],
        ],
    ],
    [
        'name' => 'enterprise-datacenter',
        'title' => 'Enterprise Data Center & Campus',
        'description' => 'Dual ISPs, MPLS branches, firewall HA, spine-leaf fabric, and campus access',
        'width' => 1600,
        'height' => 820,
        'tags' => ['datacenter', 'campus', 'wan'],
        'nodes' => [
            // Internet and WAN
            'isp_a' => ['ISP-A Transit', 363, 60],
            'isp_b' => ['ISP-B Transit', 603, 60],
            'mpls' => ['MPLS WAN', 1310, 60],
            'br_ist' => ['Branch Router IST', 1180, 170, 'router'],
            'br_ank' => ['Branch Router ANK', 1440, 170, 'router'],
            // Edge and security
            'edge1' => ['Edge-Router-01', 363, 170, 'router'],
            'edge2' => ['Edge-Router-02', 603, 170, 'router'],
            'fw1' => ['Firewall-01', 363, 280, 'firewall'],
            'fw2' => ['Firewall-02', 603, 280, 'firewall'],
            'adc' => ['Load-Balancer', 830, 280, 'netscaler'],
            // Core
            'core1' => ['Core-01', 363, 390, 'router'],
            'core2' => ['Core-02', 603, 390, 'router'],
            // Data center fabric
            'spine1' => ['Spine-01', 368, 510, 'switch'],
            'spine2' => ['Spine-02', 598, 510, 'switch'],
            'leaf1' => ['Leaf-01', 138, 630, 'switch'],
            'leaf2' => ['Leaf-02', 368, 630, 'switch'],
            'leaf3' => ['Leaf-03', 598, 630, 'switch'],
            'leaf4' => ['Leaf-04', 828, 630, 'switch'],
            'hv1' => ['Hypervisor-01', 80, 750, 'server'],
            'hv2' => ['Hypervisor-02', 195, 750, 'server'],
            'app1' => ['App-Node-01', 310, 750, 'server'],
            'app2' => ['App-Node-02', 425, 750, 'server'],
            'dbp' => ['DB-Primary', 540, 750, 'server'],
            'dbr' => ['DB-Replica', 655, 750, 'server'],
            'nas' => ['Storage-NAS', 770, 750, 'server'],
            'bkp' => ['Backup-Server', 885, 750, 'server'],
            // Campus
            'dist1' => ['Campus-Dist-01', 1130, 510, 'switch'],
            'dist2' => ['Campus-Dist-02', 1400, 510, 'switch'],
            'acc1' => ['Campus-Access-01', 1060, 630, 'switch'],
            'acc2' => ['Campus-Access-02', 1265, 630, 'switch'],
            'acc3' => ['Campus-Access-03', 1470, 630, 'switch'],
        ],
        'links' => [
            // Internet, WAN, and branches
            ['isp_a', 'edge1', 10], ['isp_b', 'edge2', 10], ['edge1', 'edge2', 10],
            ['mpls', 'edge2', 1], ['mpls', 'br_ist', 1], ['mpls', 'br_ank', 1],
            // Firewall HA pair and load balancer
            ['edge1', 'fw1', 10], ['edge2', 'fw2', 10], ['fw1', 'fw2', 10],
            ['fw1', 'core1', 40], ['fw2', 'core2', 40], ['core1', 'core2', 100],
            ['adc', 'core1', 10], ['adc', 'core2', 10],
            // Core to spines
            ['core1', 'spine1', 100], ['core1', 'spine2', 100], ['core2', 'spine1', 100], ['core2', 'spine2', 100],
            // Spine-leaf mesh
            ['spine1', 'leaf1', 40], ['spine1', 'leaf2', 40], ['spine1', 'leaf3', 40], ['spine1', 'leaf4', 40],
            ['spine2', 'leaf1', 40], ['spine2', 'leaf2', 40], ['spine2', 'leaf3', 40], ['spine2', 'leaf4', 40],
            // Servers
            ['leaf1', 'hv1', 25], ['leaf1', 'hv2', 25], ['leaf2', 'app1', 25], ['leaf2', 'app2', 25],
            ['leaf3', 'dbp', 25], ['leaf3', 'dbr', 25], ['leaf4', 'nas', 25], ['leaf4', 'bkp', 25],
            // Campus
            ['core1', 'dist1', 40], ['core2', 'dist2', 40], ['dist1', 'dist2', 40],
            ['dist1', 'acc1', 10], ['dist1', 'acc2', 10], ['dist2', 'acc3', 10],
        ],
    ],
];

echo "LibreLiveTopology Demo Data Seeder\n";
echo "=============================\n\n";

try {
    if (!class_exists('Illuminate\Support\Facades\DB')) {
        echo "Error: Laravel database layer is not available.\n";
        exit(1);
    }

    $db = Illuminate\Support\Facades\DB::class;
    $created = [];

    foreach ($demoMaps as $demo) {
        $existing = $db::table('llt_maps')->where('name', $demo['name'])->first();
        if ($existing) {
            echo "Map '{$demo['name']}' already exists (ID: {$existing->id}), skipping\n";
            echo "  Delete it first to recreate: DELETE FROM llt_maps WHERE name='{$demo['name']}'\n\n";
            continue;
        }

        $mapId = $db::transaction(function () use ($db, $demo) {
            $options = ['background' => '#ffffff', 'width' => $demo['width'], 'height' => $demo['height']];
            if (!empty($demo['tags'])) {
                $options['tags'] = $demo['tags'];
            }

            $mapId = $db::table('llt_maps')->insertGetId([
                'name' => $demo['name'],
                'title' => $demo['title'],
                'description' => $demo['description'],
                'width' => $demo['width'],
                'height' => $demo['height'],
                'options' => json_encode($options),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $nodeIds = [];
            foreach ($demo['nodes'] as $key => $node) {
                [$label, $x, $y] = $node;
                $meta = isset($node[3]) ? ['device_type' => $node[3]] : [];
                $nodeIds[$key] = $db::table('llt_nodes')->insertGetId([
                    'map_id' => $mapId,
                    'label' => $label,
                    'x' => $x,
                    'y' => $y,
                    'device_id' => null,
                    'meta' => json_encode($meta),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($demo['links'] as [$src, $dst, $gbps]) {
                $db::table('llt_links')->insert([
                    'map_id' => $mapId,
                    'src_node_id' => $nodeIds[$src],
                    'dst_node_id' => $nodeIds[$dst],
                    'bandwidth_bps' => $gbps * GBPS,
                    'port_id_a' => null,
                    'port_id_b' => null,
                    'style' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $mapId;
        });

        $created[] = $mapId;
        echo "Created map '{$demo['name']}' (ID: $mapId): " . count($demo['nodes']) . " nodes, " . count($demo['links']) . " links\n";
        echo "  View at: /plugin/LibreLiveTopology/embed/$mapId\n\n";
    }

    if ($created) {
        echo "Demo data created successfully.\n";
        echo "\nTip: Enable demo mode for simulated traffic:\n";
        echo "  export LIBRELIVETOPOLOGY_DEMO_MODE=true\n";
    }
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
