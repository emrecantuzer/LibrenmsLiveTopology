#!/usr/bin/env php
<?php
/**
 * LibreLiveTopology Map Poller
 *
 * CLI script to poll and cache map data for improved performance.
 * Run this via cron every 5 minutes.
 */

// Locate LibreNMS bootstrap and plugin autoloader
$pluginAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($pluginAutoload)) {
    require $pluginAutoload; // ensures PSR-4 autoload for plugin classes (RRDTool, Services, Models)
}

// Locate LibreNMS bootstrap
$bootstrapCandidates = [
    // Legacy includes/init.php
    '/opt/librenms/includes/init.php',
    __DIR__ . '/../../../../includes/init.php',
    __DIR__ . '/../../includes/init.php',
    // Laravel bootstrap for LibreNMS v2+
    '/opt/librenms/bootstrap/app.php',
    __DIR__ . '/../../../../bootstrap/app.php',
];

$bootstrapLoaded = false;
foreach ($bootstrapCandidates as $file) {
    if (file_exists($file)) {
        if (substr($file, -13) === 'bootstrap/app.php') {
            // Load vendor autoload
            $vendor = dirname($file) . '/vendor/autoload.php';
            if (file_exists($vendor)) {
                require $vendor;
            }
            $app = require $file;
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
        } else {
            // Set init_modules for legacy init.php
            $init_modules = ['web'];
            require $file;
        }
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    fwrite(STDERR, "Unable to locate LibreNMS bootstrap (includes/init.php).\n");
    exit(1);
}

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use LibreNMS\Plugins\LibreLiveTopology\Services\PortUtilService;
use LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService;
use LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool;

class MapPoller
{
    private $processed = 0;
    private $errors = 0;
    private $bandwidthSynced = 0;
    private $startTime;
    private PortUtilService $portUtil;

    public function __construct()
    {
        $this->startTime = microtime(true);
        // A single shared PortUtilService (and its RrdDataService) is reused
        // across every map so the request-local RRD port/device caches benefit
        // from cross-map port and device overlap.
        $this->portUtil = new PortUtilService(new RrdDataService(new RRDTool()));
    }

    public function run()
    {
        $this->log("Starting LibreLiveTopology poller at " . date('Y-m-d H:i:s'));

        try {
            $maps = Map::with(['nodes', 'links'])->get();

            if ($maps->isEmpty()) {
                $this->log("No maps found to process");
                return;
            }

            foreach ($maps as $map) {
                $this->processMap($map);
            }

            $duration = round(microtime(true) - $this->startTime, 2);
            $this->log("Poller completed in {$duration}s. Processed: {$this->processed}, Errors: {$this->errors}, Bandwidth synced: {$this->bandwidthSynced}");

        } catch (\Exception $e) {
            $this->log("Critical error: " . $e->getMessage());
            exit(1);
        }
    }

    private function processMap(Map $map)
    {
        try {
            $this->log("Processing map: {$map->name}");

            $synced = $this->syncLinkBandwidth($map);
            if ($synced > 0) {
                $this->bandwidthSynced += $synced;
                $this->log("Auto-detected bandwidth for {$synced} link(s) on map: {$map->name}");
            }

            $liveData = $this->getLiveData($map);

            $this->cacheLiveData($map, $liveData);

            $this->generateStaticAssets($map);

            $this->processed++;
            $this->log("Successfully processed map: {$map->name}");

        } catch (\Exception $e) {
            $this->errors++;
            $this->log("Error processing map {$map->name}: " . $e->getMessage());
        }
    }

    /**
     * Fill in bandwidth_bps for links that don't have one set yet, using the
     * slower of the two attached ports' LibreNMS ifSpeed (same bottleneck
     * logic as the editor's manual auto-detect). Never overwrites a value
     * an operator already set, so existing maps keep syncing safely on
     * every poll run as new links are added or ports change.
     */
    private function syncLinkBandwidth(Map $map): int
    {
        $links = $map->links->filter(function ($link) {
            return empty($link->bandwidth_bps) && ($link->port_id_a || $link->port_id_b);
        });

        if ($links->isEmpty()) {
            return 0;
        }

        $portIds = $links->flatMap(fn($l) => [$l->port_id_a, $l->port_id_b])
            ->filter(fn($id) => $id !== null && $id !== 0)
            ->unique()
            ->values()
            ->all();

        if (empty($portIds)) {
            return 0;
        }

        $speeds = $this->fetchPortSpeeds($portIds);
        $updated = 0;

        foreach ($links as $link) {
            $candidates = array_filter([
                $speeds[$link->port_id_a] ?? null,
                $speeds[$link->port_id_b] ?? null,
            ], fn($s) => $s !== null && $s > 0);

            if (empty($candidates)) {
                continue;
            }

            $link->bandwidth_bps = min($candidates);
            $link->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * ifSpeed keyed by port_id. Falls back to ifHighSpeed (Mbps) when
     * ifSpeed is missing/0 or stuck at the 32-bit SNMP counter sentinel
     * (4294967295), a well-known quirk on very high speed interfaces that
     * otherwise makes bandwidth look absurdly large and utilization show
     * as 0% for everything.
     *
     * @return array<int,int>
     */
    private function fetchPortSpeeds(array $portIds): array
    {
        if (class_exists('\App\Models\Port')) {
            $rows = \App\Models\Port::whereIn('port_id', $portIds)
                ->get(['port_id', 'ifSpeed', 'ifHighSpeed']);

            $speeds = [];
            foreach ($rows as $row) {
                $speeds[$row->port_id] = $this->resolvePortSpeed($row->ifSpeed, $row->ifHighSpeed ?? null);
            }

            return $speeds;
        }

        $placeholders = implode(',', array_fill(0, count($portIds), '?'));
        $rows = dbFetchRows("SELECT port_id, ifSpeed, ifHighSpeed FROM ports WHERE port_id IN ({$placeholders})", $portIds);

        $speeds = [];
        foreach ($rows ?: [] as $row) {
            $speeds[$row['port_id']] = $this->resolvePortSpeed($row['ifSpeed'] ?? null, $row['ifHighSpeed'] ?? null);
        }

        return $speeds;
    }

    private function resolvePortSpeed($ifSpeed, $ifHighSpeed): ?int
    {
        $ifSpeed = (int) $ifSpeed;
        if ($ifSpeed > 0 && $ifSpeed !== 4294967295) {
            return $ifSpeed;
        }
        if ($ifHighSpeed) {
            return (int) $ifHighSpeed * 1000000;
        }
        return $ifSpeed > 0 ? $ifSpeed : null;
    }

    private function getLiveData(Map $map): array
    {
        // Prime the caches in bulk before the per-link loop so linkUtilBits()
        // does not trigger one cache miss (and per-port DB query) per port.
        // Mirrors NodeDataService::preloadForMap(), threading the map's own
        // nodes/links through the same batch-prefetch helpers the render path
        // uses.
        $deviceIds = $map->nodes->pluck('device_id')->filter()->unique()->values()->all();
        $portIds = $map->links->flatMap(fn($l) => [$l->port_id_a, $l->port_id_b])
            ->filter(fn($id) => $id !== null && $id !== 0)
            ->unique()
            ->values()
            ->all();

        Node::preloadDevices($deviceIds);
        Link::preloadPortNames($portIds);
        $this->portUtil->preloadForPorts($portIds, $deviceIds);

        $liveData = [
            'ts' => time(),
            'links' => []
        ];

        foreach ($map->links as $link) {
            $liveData['links'][$link->id] = $this->portUtil->linkUtilBits([
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
            ]);
        }

        return $liveData;
    }

    private function cacheLiveData(Map $map, array $liveData)
    {
        // Cache live data for faster API responses
        $cacheKey = "librelivetopology.live.{$map->id}";
        $cacheTtl = config('librelivetopology.cache_ttl', 300);

        \Illuminate\Support\Facades\Cache::put($cacheKey, $liveData, $cacheTtl);

        // Optionally write to file for backup
        $cacheFile = __DIR__ . "/../output/cache/{$map->name}.json";
        $this->ensureDirectory(dirname($cacheFile));

        file_put_contents($cacheFile, json_encode($liveData, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function generateStaticAssets(Map $map)
    {
        // Generate static thumbnails or pre-rendered images if needed
        // This could be extended to generate PNG thumbnails of maps

        $thumbnailDir = __DIR__ . '/../output/thumbnails';
        $this->ensureDirectory($thumbnailDir);

        // Placeholder for thumbnail generation
        // In a full implementation, this would render a small PNG of the map
    }

    private function ensureDirectory($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        // Log to stdout (captured by cron)
        echo $logMessage;

        // Also log to file if configured
        $logFile = config('librelivetopology.log_file', __DIR__ . '/../logs/poller.log');
        if ($logFile) {
            $this->ensureDirectory(dirname($logFile));
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
}

// Run the poller
$poller = new MapPoller();
$poller->run();

echo "LibreLiveTopology poller completed successfully.\n";
