<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapService;
use LibreNMS\Plugins\LibreLiveTopology\Services\AutoDiscoveryService;
use LibreNMS\Plugins\LibreLiveTopology\Http\Requests\CreateMapRequest;
use LibreNMS\Plugins\LibreLiveTopology\Http\Requests\SaveMapRequest;
use LibreNMS\Plugins\LibreLiveTopology\Http\Requests\UpdateMapRequest;
use Illuminate\Support\Facades\Log;

class MapController
{
    use AdminCheck;

    private $mapService;
    private $autoDiscoveryService;

    public function __construct(
        MapService $mapService,
        AutoDiscoveryService $autoDiscoveryService
    ) {
        $this->mapService = $mapService;
        $this->autoDiscoveryService = $autoDiscoveryService;
    }

    public function create(CreateMapRequest $request): mixed
    {
        $this->requireAdmin();
        $validated = $request->sanitize($request->validated());
        $map = $this->mapService->createMap($validated);

        return $this->handleCreateResponse($request, $map);
    }
    public function update(UpdateMapRequest $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->sanitize($request->validated());
        $this->mapService->updateMap($map, $validated);

        return response()->json([
            'success' => true,
            'map' => $map,
        ]);
    }
    public function destroy(Map $map): mixed
    {
        $this->requireAdmin();
        $this->mapService->deleteMap($map);

        return $this->handleDeleteResponse();
    }

    public function save(SaveMapRequest $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();

        try {
            // sanitize() runs after validated() and uses normalizeOrThrow()
            // on node labels, so it can throw InvalidArgumentException on
            // an empty-after-strip label (e.g. "<b></b>"). Keep it inside
            // the try so that surfaces as 422, not a 500.
            $validatedData = $request->sanitize($request->validated());
            $this->mapService->saveMap($map, $validatedData);
            $savedMap = $map->fresh(['nodes', 'links']);
            return response()->json([
                'success' => true,
                'message' => 'Map saved successfully',
                'map' => $savedMap?->toJsonModel(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save map', [
                'map_id' => $map->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save map.'
            ], 500);
        }
    }
    public function autoDiscover(\Illuminate\Http\Request $request, Map $map): \Illuminate\Http\JsonResponse
    {
        $this->requireAdmin();
        $params = $this->autoDiscoveryService->validateDiscoveryParams($request->all());

        try {
            $summary = $this->autoDiscoveryService->discoverAndSeedMap($map, $params);
            $nodesAdded = (int) ($summary['nodes_added'] ?? 0);
            $linksAdded = (int) ($summary['links_added'] ?? 0);

            return response()->json([
                'success' => true,
                'message' => "Auto-discovery completed: {$nodesAdded} nodes, {$linksAdded} links added",
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Auto-discovery failed', [
                'map_id' => $map->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Auto-discovery failed.',
            ], 500);
        }
    }

    private function handleCreateResponse(\Illuminate\Http\Request $request, Map $map): mixed
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'map' => $map,
                'redirect' => route('librelivetopology.editor', $map)
            ]);
        }

        return redirect()->route('librelivetopology.editor', $map)
            ->with('success', 'Map created successfully!');
    }

    private function handleDeleteResponse(): mixed
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Map deleted successfully!'
            ]);
        }

        return redirect()->route('librelivetopology.index')
            ->with('success', 'Map deleted successfully!');
    }
}
