<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Services\Logger;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapService;
use LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class HealthController
{
    use AdminCheck;

    private ?Logger $logger = null;

    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = Logger::getInstance();
        }
        return $this->logger;
    }
    /**
     * Basic health check endpoint (v2)
     * GET /plugin/LibreLiveTopology/health
     */
    public function check(): \Illuminate\Http\JsonResponse
    {
        // Keep the public probe free of version, filesystem, and configuration details.
        $databaseHealthy = $this->checkDatabase()['status'] === 'healthy';
        $status = $databaseHealthy ? 'healthy' : 'unhealthy';

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ], $this->getHttpStatusCode($status));
    }

    private function getHttpStatusCode(string $status): int
    {
        return match ($status) {
            'healthy' => 200,
            'warning' => 200,
            'unhealthy' => 503,
            default => 200,
        };
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();

        $stats = [
            'maps' => Map::count(),
            'nodes' => \DB::table('llt_nodes')->count(),
            'links' => \DB::table('llt_links')->count(),
            'last_updated' => Map::max('updated_at'),
            'database_size' => $this->getDatabaseSize(),
            'cache_info' => $this->getCacheInfo()
        ];

        return response()->json($stats);
    }

    private function getDatabaseSize(): string
    {
        try {
            $tables = ['llt_maps', 'llt_nodes', 'llt_links'];
            $totalSize = 0;

            foreach ($tables as $table) {
                $size = \DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = ?
                ", [$table]);

                if (!empty($size)) {
                    $totalSize += $size[0]->size_mb ?? 0;
                }
            }

            return round($totalSize, 2) . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getCacheInfo(): array
    {
        try {
            $cache = app('cache');
            $store = $cache->getStore();

            if (method_exists($store, 'getCache')) {
                // Redis/File cache
                return [
                    'driver' => config('cache.default'),
                    'status' => 'available'
                ];
            }

            return [
                'driver' => config('cache.default'),
                'status' => 'unknown'
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Readiness probe for container orchestration
     * GET /plugin/LibreLiveTopology/ready
     */
    public function ready(): \Illuminate\Http\JsonResponse
    {
        try {
            // Check database connectivity
            DB::connection()->getPdo();

            // Check critical directories
            $outputDir = config('librelivetopology.output_dir', __DIR__ . '/../../../output/maps/');
            if (!is_dir($outputDir)) {
                throw new \RuntimeException('Output directory not found');
            }

            return response()->json([
                'ready' => true,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            $this->getLogger()->error('Readiness check failed', ['error' => $e->getMessage()]);

            return response()->json([
                'ready' => false,
                'error' => 'Readiness check failed',
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Liveness probe for container orchestration
     * GET /plugin/LibreLiveTopology/live
     */
    public function live(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Prometheus metrics endpoint
     * GET /plugin/LibreLiveTopology/metrics
     */
    public function diagnostics(): \Illuminate\View\View
    {
        $this->requireAdmin();

        $health = $this->check()->getData(true);

        $stats = [
            'maps' => 0,
            'nodes' => 0,
            'links' => 0,
            'database_size' => 'Unknown',
        ];
        try {
            $stats['maps'] = Map::count();
            $stats['nodes'] = DB::table('llt_nodes')->count();
            $stats['links'] = DB::table('llt_links')->count();
            $stats['database_size'] = $this->getDatabaseSize();
        } catch (\Exception $e) {
            $stats['error'] = 'Unable to query statistics: ' . $e->getMessage();
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
            'dependencies' => $this->checkDependencies(),
            'configuration' => $this->checkConfiguration(),
            'performance' => $this->getPerformanceMetrics(),
        ];

        $routes = [
            ['name' => 'Index', 'route' => 'librelivetopology.index', 'method' => 'GET', 'url' => route('librelivetopology.index')],
            ['name' => 'Embed', 'route' => 'librelivetopology.embed', 'method' => 'GET'],
            ['name' => 'Map JSON', 'route' => 'librelivetopology.json', 'method' => 'GET'],
            ['name' => 'Live data', 'route' => 'librelivetopology.live', 'method' => 'GET'],
            ['name' => 'Save map', 'route' => 'librelivetopology.map.save', 'method' => 'POST'],
            ['name' => 'Health check', 'route' => 'librelivetopology.health', 'method' => 'GET', 'url' => route('librelivetopology.health')],
            ['name' => 'Health detailed', 'route' => 'librelivetopology.health.detailed', 'method' => 'GET', 'url' => route('librelivetopology.health.detailed')],
            ['name' => 'Health stats', 'route' => 'librelivetopology.health.stats', 'method' => 'GET', 'url' => route('librelivetopology.health.stats')],
            ['name' => 'Metrics', 'route' => 'librelivetopology.metrics', 'method' => 'GET', 'url' => route('librelivetopology.metrics')],
            ['name' => 'Diagnostics', 'route' => 'librelivetopology.diagnostics', 'method' => 'GET', 'url' => route('librelivetopology.diagnostics')],
        ];

        $routeStatus = array_map(function ($r) {
            if (!Route::has($r['route'])) {
                $r['url'] = '#';
                $r['status'] = 'missing';
                return $r;
            }
            // Parameterized routes are registered; don't try to synthesize a URL.
            if (!isset($r['url'])) {
                $r['url'] = '#';
            }
            $r['status'] = 'ok';
            return $r;
        }, $routes);

        $writablePaths = [
            'output/maps' => config('librelivetopology.output_dir', __DIR__ . '/../../../output/maps/'),
            'resources/output' => __DIR__ . '/../../../resources/output/',
            'storage' => __DIR__ . '/../../../storage/',
        ];

        $pathStatus = [];
        foreach ($writablePaths as $label => $path) {
            $resolved = realpath($path) ?: $path;
            $pathStatus[$label] = [
                'path' => $resolved,
                'exists' => is_dir($resolved),
                'writable' => is_writable($resolved),
            ];
        }

        // Data-integrity scan: broken ports, missing RRD files, orphaned rows.
        $integrity = [];
        try {
            $integrity = (new MapService())->getDataIntegrityIssues(
                $this->resolveRrdDataService(),
                20
            );
        } catch (\Exception $e) {
            $this->getLogger()->error('Data integrity scan failed', ['error' => $e->getMessage()]);
            $integrity = ['maps' => [], 'summary' => [], 'error' => $e->getMessage()];
        }

        return view('LibreLiveTopology::diagnostics', [
            'version' => $this->getVersion(),
            'overallStatus' => $health['status'] ?? 'unknown',
            'checks' => $checks,
            'stats' => $stats,
            'routes' => $routeStatus,
            'paths' => $pathStatus,
            'integrity' => $integrity,
            'librenmsVersion' => config('librenms.version', 'unknown'),
        ]);
    }

    public function metrics(): \Illuminate\Http\Response
    {
        $this->requireAdmin();

        $metrics = [];

        // Database metrics
        try {
            $mapCount = Map::count();
            $nodeCount = DB::table('llt_nodes')->count();
            $linkCount = DB::table('llt_links')->count();

            $metrics[] = "# HELP librelivetopology_maps_total Total number of maps";
            $metrics[] = "# TYPE librelivetopology_maps_total gauge";
            $metrics[] = "librelivetopology_maps_total $mapCount";

            $metrics[] = "# HELP librelivetopology_nodes_total Total number of nodes";
            $metrics[] = "# TYPE librelivetopology_nodes_total gauge";
            $metrics[] = "librelivetopology_nodes_total $nodeCount";

            $metrics[] = "# HELP librelivetopology_links_total Total number of links";
            $metrics[] = "# TYPE librelivetopology_links_total gauge";
            $metrics[] = "librelivetopology_links_total $linkCount";
        } catch (\Exception $e) {
            $this->getLogger()->error('Failed to collect metrics', ['error' => $e->getMessage()]);
        }

        // Memory metrics
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $metrics[] = "# HELP librelivetopology_memory_usage_bytes Current memory usage";
        $metrics[] = "# TYPE librelivetopology_memory_usage_bytes gauge";
        $metrics[] = "librelivetopology_memory_usage_bytes $memoryUsage";

        $metrics[] = "# HELP librelivetopology_memory_peak_bytes Peak memory usage";
        $metrics[] = "# TYPE librelivetopology_memory_peak_bytes gauge";
        $metrics[] = "librelivetopology_memory_peak_bytes $memoryPeak";

        return response(implode("\n", $metrics) . "\n")
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * Detailed health check
     * GET /plugin/LibreLiveTopology/health/detailed
     */
    public function detailed(): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();

        $startTime = microtime(true);
        $checks = [];

        // Database check
        $checks['database'] = $this->checkDatabase();

        // Filesystem check
        $checks['filesystem'] = $this->checkFilesystem();

        // Dependencies check
        $checks['dependencies'] = $this->checkDependencies();

        // Configuration check
        $checks['configuration'] = $this->checkConfiguration();

        // Performance metrics
        $checks['performance'] = $this->getPerformanceMetrics();

        // Overall status
        $overallStatus = $this->determineOverallStatus($checks);

        $response = [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => $this->getVersion(),
            'checks' => $checks,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];

        $statusCode = $overallStatus === 'healthy' ? 200 : 503;

        if ($overallStatus !== 'healthy') {
            $this->getLogger()->warning('Health check detected issues', $response);
        }

        return response()->json($response, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connected'
            ];
        } catch (\Exception $exception) {
            $this->getLogger()->error('Health check database failure', ['error' => $exception->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed'
            ];
        }
    }

    private function checkFilesystem(): array
    {
        $checks = [];

        // RRD access check
        $rrdPath = config('librelivetopology.rrd_base', '/opt/librenms/rrd');
        $checks['rrd'] = [
            'status' => is_readable($rrdPath) ? 'healthy' : 'warning',
            'message' => is_readable($rrdPath)
                ? 'RRD directory accessible'
                : 'RRD directory not accessible'
        ];

        // Output directory check
        $outputDir = config('librelivetopology.output_dir', __DIR__ . '/../../../output/maps/');
        $checks['output'] = [
            'status' => is_writable($outputDir) ? 'healthy' : 'warning',
            'message' => is_writable($outputDir)
                ? 'Output directory writable'
                : 'Output directory not writable'
        ];

        // Return the most severe status
        $overallStatus = 'healthy';
        $messages = [];

        foreach ($checks as $check) {
            $messages[] = $check['message'];
            if ($check['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
            } elseif ($check['status'] === 'warning' && $overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }
        }

        return [
            'status' => $overallStatus,
            'message' => implode('; ', $messages)
        ];
    }

    private function checkDependencies(): array
    {
        $missingDeps = [];

        // Check GD extension
        if (!extension_loaded('gd')) {
            $missingDeps[] = 'GD extension';
        }

        // Check JSON extension
        if (!extension_loaded('json')) {
            $missingDeps[] = 'JSON extension';
        }

        if (empty($missingDeps)) {
            return [
                'status' => 'healthy',
                'message' => 'All required PHP extensions loaded'
            ];
        }

        return [
            'status' => 'unhealthy',
            'message' => 'Missing PHP extensions: ' . implode(', ', $missingDeps)
        ];
    }

    private function checkConfiguration(): array
    {
        $issues = [];

        // API token check
        $apiToken = config('librelivetopology.api_token');
        if (!$apiToken) {
            $issues[] = 'Configuration incomplete (API fallback may not work)';
        }

        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'Configuration is valid'
            ];
        }

        return [
            'status' => 'warning',
            'message' => implode('; ', $issues)
        ];
    }





    private function getPerformanceMetrics(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'load_average' => sys_getloadavg()
        ];
    }

    private function determineOverallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? 'healthy') === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if (($check['status'] ?? 'healthy') === 'warning') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    private function resolveRrdDataService(): ?RrdDataService
    {
        try {
            return app(RrdDataService::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getVersion(): string
    {
        $versionFile = __DIR__ . '/../../../VERSION';
        if (is_readable($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        $composerJson = __DIR__ . '/../../../composer.json';
        if (file_exists($composerJson)) {
            $composer = json_decode(file_get_contents($composerJson), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }
}
