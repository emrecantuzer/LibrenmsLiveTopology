<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Services\NodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MapNodeController
{
    use AdminCheck;

    private $nodeService;

    public function __construct(NodeService $nodeService)
    {
        $this->nodeService = $nodeService;
    }

    public function store(Request $request, Map $map): JsonResponse
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.label' => 'required|string|max:255',
            'nodes.*.x' => 'required|numeric',
            'nodes.*.y' => 'required|numeric',
            'nodes.*.device_id' => 'nullable|integer',
        ]);

        try {
            $this->nodeService->storeNodes($map, $validated['nodes']);
            return response()->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function create(Request $request, Map $map): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->validate([
            'label' => 'required|string|max:255',
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'device_id' => 'nullable|integer',
            'meta' => 'array',
            'meta.device_type' => 'nullable|string|in:auto,paloalto,netscaler,fortinet,firewall,router,switch,aruba,brocade,server',
        ]);

        try {
            $node = $this->nodeService->createNode($map, $data);
            return response()->json(['success' => true, 'node' => $node]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, Map $map, Node $node): JsonResponse
    {
        $this->requireAdmin();

        $data = $request->validate([
            'label' => 'sometimes|string|max:255',
            'x' => 'sometimes|numeric',
            'y' => 'sometimes|numeric',
            'device_id' => 'sometimes|nullable|integer',
            'meta' => 'sometimes|array',
            'meta.device_type' => 'sometimes|nullable|string|in:auto,paloalto,netscaler,fortinet,firewall,router,switch,aruba,brocade,server',
        ]);

        try {
            $node = $this->nodeService->updateNode($map, $node, $data);
            return response()->json(['success' => true, 'node' => $node]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete(Map $map, Node $node): JsonResponse
    {
        $this->requireAdmin();

        try {
            $this->nodeService->deleteNode($map, $node);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
