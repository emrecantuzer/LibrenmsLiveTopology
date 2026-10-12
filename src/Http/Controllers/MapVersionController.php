<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\MapVersion;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapVersionService;
use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Http\Requests\SaveMapVersionRequest;

class MapVersionController extends Controller
{
    use AdminCheck;
    private $mapVersionService;

    public function __construct(MapVersionService $mapVersionService)
    {
        $this->mapVersionService = $mapVersionService;
    }

    public function index(int $mapId): JsonResponse
    {
        $this->requireAdmin();
        $map = Map::findOrFail($mapId);

        $versions = $this->mapVersionService->getVersions($map, $this->mapVersionService->listLimit());
        $retention = $this->mapVersionService->retentionLimit();

        return response()->json([
            'success' => true,
            'versions' => $versions,
            'latest_version' => $this->mapVersionService->getLatestVersion($map),
            'total_versions' => count($versions),
            // null means retention is disabled and every snapshot is kept.
            'max_versions' => $retention > 0 ? $retention : null,
            'backup_before_restore' => true,
        ]);
    }

    public function show(int $versionId): JsonResponse
    {
        $this->requireAdmin();
        $version = MapVersion::findOrFail($versionId);

        return response()->json([
            'success' => true,
            'version' => $version,
            'snapshot' => $version->config_snapshot,
            'created_at' => $version->created_at?->toIso8601String(),
            'created_at_human' => $version->created_at_human,
            'created_by' => $version->creator?->name ?? 'Unknown',
        ]);
    }

    public function store(SaveMapVersionRequest $request, int $mapId): JsonResponse
    {
        $this->requireAdmin();
        $map = Map::findOrFail($mapId);
        $userId = auth()->id();

        $version = $this->mapVersionService->createVersion(
            $map,
            strip_tags($request->validated('name')),
            $request->validated('description') ? strip_tags($request->validated('description')) : null,
            $userId
        );

        return response()->json([
            'success' => true,
            'version' => $version,
            'message' => 'Version saved successfully',
            'map_id' => $mapId,
        ], 201);
    }

    public function restore(int $versionId): JsonResponse
    {
        $this->requireAdmin();
        $version = MapVersion::findOrFail($versionId);
        $this->mapVersionService->restoreVersion($version, auth()->id());

        $map = $version->map;

        return response()->json([
            'success' => true,
            'message' => 'Map restored to version: ' . $version->name,
            'map' => $map,
            'version' => $version,
            'restored_at' => now(),
        ]);
    }

    public function compare(int $versionId, int $compareId): JsonResponse
    {
        $this->requireAdmin();
        $version1 = MapVersion::findOrFail($versionId);
        $version2 = MapVersion::findOrFail($compareId);

        if ($version1->map_id !== $version2->map_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot compare versions from different maps',
            ], 422);
        }

        $diff = $this->mapVersionService->compareVersions($version1, $version2);

        return response()->json([
            'success' => true,
            'diff' => $diff,
            'version1' => $version1,
            'version2' => $version2,
            'created_at_1' => $version1->created_at->toIso8601String(),
            'created_at_2' => $version2->created_at->toIso8601String(),
        ]);
    }

    public function destroy(int $versionId): JsonResponse
    {
        $this->requireAdmin();
        $version = MapVersion::findOrFail($versionId);

        $this->mapVersionService->deleteVersion($version);

        return response()->json([
            'success' => true,
            'message' => 'Version deleted successfully',
            'deleted_at' => now(),
        ]);
    }

    public function export(int $mapId): JsonResponse
    {
        $this->requireAdmin();
        $map = Map::findOrFail($mapId);
        $versions = $this->mapVersionService->getVersions($map);

        return response()->json([
            'success' => true,
            'export_data' => [
                'map' => [
                    'id' => $map->id,
                    'name' => $map->name,
                    'title' => $map->title,
                    'created_at' => $map->created_at->toIso8601String(),
                ],
                'versions' => $versions->map(function ($version) {
                    return [
                        'id' => $version->id,
                        'name' => $version->name,
                        'description' => $version->description,
                        'created_at' => $version->created_at->toIso8601String(),
                        'created_at_human' => $version->created_at_human,
                        'created_by' => $version->creator?->name ?? 'Unknown',
                        'snapshot' => $version->config_snapshot,
                    ];
                })->toArray(),
                'metadata' => [
                    'exported_at' => now()->toIso8601String(),
                    'total_versions' => count($versions),
                    'format' => 'json',
                    'compression' => 'none',
                    'exported_by' => auth()->user()?->name ?? 'Unknown',
                ],
            ],
        ]);
    }

}
