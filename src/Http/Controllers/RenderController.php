<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapService;
use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Services\NodeDataService;
use LibreNMS\Plugins\LibreLiveTopology\Services\ReplayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenderController
{
    use AdminCheck;

    private $nodeDataService;
    private $mapService;
    private $replayService;

    public function __construct(NodeDataService $nodeDataService, MapService $mapService, ?ReplayService $replayService = null)
    {
        $this->nodeDataService = $nodeDataService;
        $this->mapService = $mapService;
        $this->replayService = $replayService;
    }

    public function json(Map $map): JsonResponse
    {
        return response()->json($map->toJsonModel())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function live(Map $map): JsonResponse
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $data = [
            'ts' => time(),
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->nodeDataService->buildAlertData($map),
        ];

        return response()->json($data);
    }

    public function replay(Map $map, Request $request): JsonResponse
    {
        $timestamp = $request->integer('timestamp');
        if ($timestamp < now()->subDays(30)->timestamp || $timestamp > now()->timestamp) {
            return response()->json(['error' => 'Timestamp is outside the replay window'], 422);
        }

        $replayService = $this->replayService ?: app(ReplayService::class);
        return response()->json($replayService->buildPayload($map, $timestamp));
    }

    public function embed(Map $map, Request $request): View
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $mapData = $map->toJsonModel();
        $mapId = $map->id;

        // Include initial live data so page renders with traffic immediately
        $liveData = [
            'links' => $this->nodeDataService->buildLinkData($map),
            'nodes' => $this->nodeDataService->buildNodeData($map),
            'alerts' => $this->nodeDataService->buildAlertData($map),
        ];

        $demoMode = config('librelivetopology.demo_mode', false);

        $kiosk = $request->boolean('kiosk');
        $cycle = $request->input('cycle');
        $cycleSeconds = ctype_digit((string) $cycle) ? max(5, (int) $cycle) : null;
        $target = in_array($request->input('target'), ['self', '_self'], true) ? '_self' : '_blank';

        // Ordered list of maps so kiosk mode can cycle without an extra API call.
        // Only build it when kiosk cycling is actually active.
        $mapList = ($kiosk === true && $cycleSeconds !== null)
            ? Map::query()
                ->select(['id', 'name', 'title'])
                ->orderBy('name')
                ->get()
                ->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'title' => $m->title])
                ->values()
                ->toArray()
            : [];

        return view('LibreLiveTopology::embed', compact(
            'mapData',
            'mapId',
            'liveData',
            'demoMode',
            'kiosk',
            'cycleSeconds',
            'target',
            'mapList'
        ));
    }

    public function export(Map $map, Request $request): JsonResponse
    {
        $format = $request->get('format', 'json');

        if ($format === 'json') {
            $map->load(['nodes', 'links']);
            // Strip CR/LF, quotes, backslashes, and path separators so the
            // map name cannot inject response headers or traverse paths.
            $safeName = preg_replace('/[\r\n"\0\\\\\/]+/', '_', $map->name ?? 'map');
            $safeName = trim($safeName, " \t._-") ?: 'map';
            return response()->json($map->toJsonModel())
                           ->header('Content-Disposition', 'attachment; filename="' . $safeName . '.json"');
        }

        return response()->json(['error' => 'Unsupported format'], 400);
    }

    public function import(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'file' => 'required|file|mimes:json|max:8192',
            'name' => 'required|string|max:255|unique:llt_maps,name',
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $map = $this->mapService->importMap($request, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->getLogger()->error('Map import failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Map import failed'], 500);
        }

        return response()->json([
            'success' => true,
            'map_id' => $map->id,
            'message' => 'Map imported successfully'
        ]);
    }

    public function sse(Map $map, Request $request): StreamedResponse
    {
        $map->load(['nodes', 'links']);
        $this->nodeDataService->preloadForMap($map);
        $interval = min(60, max(1, (int) $request->get('interval', 5)));
        $maxSeconds = min(600, max(5, (int) $request->get('max', 300)));

        return $this->nodeDataService->stream($map, $interval, $maxSeconds);
    }
}
