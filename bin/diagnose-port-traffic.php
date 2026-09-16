#!/usr/bin/env php
<?php

declare(strict_types=1);

// Run from the plugin checkout as the LibreNMS user. Does not modify map/config data.
$portId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$root = rtrim($argv[2] ?? '/opt/librenms', '/');
if (!$portId || !is_file($root . '/bootstrap/app.php') || !is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php bin/diagnose-port-traffic.php PORT_ID [LIBRENMS_ROOT]\n");
    exit(2);
}
require_once $root . '/vendor/autoload.php';
$pluginRoot = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($pluginRoot): void {
    $prefix = 'LibreNMS\\Plugins\\LibreLiveTopology\\';
    if (str_starts_with($class, $prefix)) {
        $file = $pluginRoot . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

try {
    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $tool = new \LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool();
    $rrd = new \LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService($tool);
    $util = new \LibreNMS\Plugins\LibreLiveTopology\Services\PortUtilService($rrd);
    $links = \Illuminate\Support\Facades\DB::table('llt_links')
        ->where('port_id_a', $portId)->orWhere('port_id_b', $portId)
        ->orderBy('id')->limit(10)
        ->get(['id', 'map_id', 'port_id_a', 'port_id_b', 'bandwidth_bps']);
    $ids = [$portId];
    foreach ($links as $link) {
        foreach ([$link->port_id_a, $link->port_id_b] as $id) {
            if ($id) $ids[] = (int) $id;
        }
    }
    // Diagnostic-only access to the exact resolver used by live traffic.
    $infoMethod = new \ReflectionMethod($rrd, 'getPortInfo');
    $pathMethod = new \ReflectionMethod($rrd, 'resolvePortRrdPath');
    $infoMethod->setAccessible(true);
    $pathMethod->setAccessible(true);
    $report = [
        'diagnostic_version' => 1,
        'observed_at_utc' => gmdate('c'),
        'requested_port_id' => $portId,
        'demo_mode' => (bool) config('librelivetopology.demo_mode', false),
        'context' => 'CLI; web PHP workers may load a different cached version',
        'ports' => [], 'links_first_10' => [],
    ];
    foreach (array_unique($ids) as $id) {
        $info = $infoMethod->invoke($rrd, $id);
        $path = $info ? $pathMethod->invoke($rrd, $info) : null;
        $entry = [
            'port_id' => $id,
            'rrd_file' => $path ? basename($path) : null,
            'ifSpeed_bps' => $info['ifSpeed'] ?? null,
            'cache_before_read_bps' => \Illuminate\Support\Facades\Cache::get("librelivetopology.port.traffic.v3.{$id}"),
        ];
        if ($path) {
            $entry['parsed_octets_per_second'] = $tool->getLastValues($path);
            $entry['uncached_bits_per_second'] = $rrd->getPortTraffic($id);
            $process = new \Symfony\Component\Process\Process([
                (string) config('librelivetopology.rrdtool_path', '/usr/bin/rrdtool'),
                'fetch', $path, 'AVERAGE', '--start', '-10m', '--end', 'now',
            ]);
            $process->setTimeout(15);
            $process->run();
            if ($process->isSuccessful()) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
                $entry['rrd_header'] = $lines[0] ?? '';
                $numericRows = array_values(array_filter($lines, static fn ($line) => preg_match('/^\d+:.*\d/', $line)));
                $entry['last_numeric_rows_octets_per_second'] = array_slice($numericRows, -3);
            } else {
                $entry['raw_fetch_error'] = 'rrdtool fetch failed';
            }
        }
        $report['ports'][] = $entry;
    }
    foreach ($links as $link) {
        $report['links_first_10'][] = [
            'link_id' => $link->id, 'map_id' => $link->map_id,
            'port_id_a' => $link->port_id_a, 'port_id_b' => $link->port_id_b,
            'hover_graph_port_id' => $link->port_id_a ?: $link->port_id_b,
            'computed_live_bps' => $util->linkUtilBits((array) $link),
        ];
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Traffic diagnostic failed: ' . get_class($e) . ". Check LibreNMS CLI configuration and permissions.\n");
    exit(1);
}
