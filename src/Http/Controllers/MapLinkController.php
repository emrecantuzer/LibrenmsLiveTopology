<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use LibreNMS\Plugins\LibreLiveTopology\Services\LinkService;
use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MapLinkController
{
    use AdminCheck;
    private $linkService;

    public function __construct(LinkService $linkService)
    {
        $this->linkService = $linkService;
    }

    public function store(Request $request, Map $map): JsonResponse
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'links' => 'required|array',
            'links.*.src_node_id' => 'required|integer|exists:llt_nodes,id',
            'links.*.dst_node_id' => 'required|integer|exists:llt_nodes,id',
            'links.*.port_id_a' => 'nullable|integer',
            'links.*.port_id_b' => 'nullable|integer',
            'links.*.bandwidth_bps' => 'nullable|integer',
            'links.*.style' => 'nullable|array:via_style,via_points,color,width',
            'links.*.style.via_style' => 'nullable|string|in:straight,angled,curved',
            'links.*.style.via_points' => 'nullable|array|max:200',
            'links.*.style.via_points.*' => 'array:x,y',
            'links.*.style.via_points.*.x' => 'required|numeric|min:0|max:10000',
            'links.*.style.via_points.*.y' => 'required|numeric|min:0|max:10000',
            'links.*.style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'links.*.style.width' => 'nullable|numeric|min:0.5|max:20',
        ]);

        $this->linkService->storeLinks($map, $validated['links']);

        return response()->json(['success' => true]);
    }

    public function create(Request $request, Map $map): JsonResponse
    {
        $this->requireAdmin();
        $data = $request->validate([
            'src_node_id' => 'required|integer|exists:llt_nodes,id',
            'dst_node_id' => 'required|integer|exists:llt_nodes,id',
            'port_id_a' => 'nullable|integer',
            'port_id_b' => 'nullable|integer',
            'bandwidth_bps' => 'nullable|integer',
            'style' => 'nullable|array:via_style,via_points,color,width',
            'style.via_style' => 'nullable|string|in:straight,angled,curved',
            'style.via_points' => 'nullable|array|max:200',
            'style.via_points.*' => 'array:x,y',
            'style.via_points.*.x' => 'required|numeric|min:0|max:10000',
            'style.via_points.*.y' => 'required|numeric|min:0|max:10000',
            'style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'style.width' => 'nullable|numeric|min:0.5|max:20',
        ]);

        try {
            $link = $this->linkService->createLink($map, $data);
            return response()->json(['success' => true, 'link' => $link]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, Map $map, Link $link): JsonResponse
    {
        $this->requireAdmin();
        $data = $request->validate([
            'src_node_id' => 'sometimes|integer',
            'dst_node_id' => 'sometimes|integer',
            'port_id_a' => 'sometimes|nullable|integer',
            'port_id_b' => 'sometimes|nullable|integer',
            'bandwidth_bps' => 'sometimes|nullable|integer',
            'style' => 'sometimes|array:via_style,via_points,color,width',
            'style.via_style' => 'nullable|string|in:straight,angled,curved',
            'style.via_points' => 'nullable|array|max:200',
            'style.via_points.*' => 'array:x,y',
            'style.via_points.*.x' => 'required|numeric|min:0|max:10000',
            'style.via_points.*.y' => 'required|numeric|min:0|max:10000',
            'style.color' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{6}$/',
            'style.width' => 'nullable|numeric|min:0.5|max:20',
        ]);

        try {
            $link = $this->linkService->updateLink($map, $link, $data);
            return response()->json(['success' => true, 'link' => $link]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete(Map $map, Link $link): JsonResponse
    {
        $this->requireAdmin();
        try {
            $this->linkService->deleteLink($map, $link);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
