<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use LibreNMS\Plugins\LibreLiveTopology\Services\RrdDataService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class MapService
{
    public function createMap(array $data): Map
    {
        $allowedOptionKeys = ['background', 'tags', 'default_node_style', 'default_link_style'];
        $incomingOptions = !empty($data['options']) && is_array($data['options'])
            ? array_intersect_key($data['options'], array_flip($allowedOptionKeys))
            : [];

        $options = array_merge([
            'width' => $data['width'] ?? 800,
            'height' => $data['height'] ?? 600,
            'background' => '#ffffff',
        ], $incomingOptions);

        return Map::create([
            'name' => $data['name'],
            'title' => $data['title'] ?? $data['name'],
            'options' => $options,
        ]);
    }

    public function updateMap(Map $map, array $data): Map
    {
        $incomingOptions = array_filter([
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'background' => $data['background'] ?? null,
        ], fn($value) => $value !== null);

        $options = $this->mergeMapOptions($map->options ?? [], $incomingOptions);

        $update = ['options' => $options];
        if (array_key_exists('title', $data) && Schema::hasColumn('llt_maps', 'title')) {
            $update['title'] = $data['title'] ?? $map->title;
        }

        $map->update($update);
        return $map->refresh();
    }

    public function deleteMap(Map $map): void
    {
        try {
            DB::transaction(function () use ($map) {
                $map->links()->delete();
                $map->nodes()->delete();
                $map->delete();
            });
        } catch (\Exception $e) {
            Log::error("Failed to delete map {$map->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveMap(Map $map, array $data): void
    {
        // API validation: refuse to wipe an existing map when the payload is
        // malformed (no "nodes"/"links" key at all). An intentional clear sends
        // `nodes: []` / `links: []` explicitly; a missing key means the caller
        // never populated them. This does NOT protect against the editor race
        // (the client always sends both keys, even when empty) — that is the
        // client-side load gate's job.
        if (!array_key_exists('nodes', $data) || !array_key_exists('links', $data)) {
            $existing = $map->links()->exists() || $map->nodes()->exists();
            if ($existing) {
                throw new \InvalidArgumentException(
                    'Save payload missing "nodes" or "links" key; refusing to wipe existing map content.'
                );
            }
        }

        try {
            DB::transaction(function () use ($map, $data) {
                $this->updateMapProperties($map, $data);
                $this->replaceMapContent($map, $data);
            });
        } catch (\Exception $e) {
            Log::error("Failed to save map {$map->id}: " . $e->getMessage());
            throw new \RuntimeException("Failed to save map: " . $e->getMessage(), 0, $e);
        }
    }

    private function updateMapProperties(Map $map, array $data): void
    {
        if (empty($data['options']) && !array_key_exists('title', $data) && !array_key_exists('name', $data)) {
            return;
        }

        $updates = [];

        if (!empty($data['options'])) {
            $updates['options'] = $this->mergeMapOptions($map->options ?? [], $data['options']);
        }
        if (array_key_exists('title', $data) && is_string($data['title']) && trim($data['title']) !== '' && Schema::hasColumn('llt_maps', 'title')) {
            $updates['title'] = $data['title'];
        }
        if (array_key_exists('name', $data) && is_string($data['name']) && trim($data['name']) !== '' && Schema::hasColumn('llt_maps', 'name')) {
            $updates['name'] = $data['name'];
        }

        if (!empty($updates)) {
            $map->fill($updates)->save();
        }
    }

    private function mergeMapOptions(array $currentOptions, array $newOptions): array
    {
        // Recursive merge: nested arrays like default_node_style/default_link_style
        // should be merged, not replaced wholesale by array_merge.
        $merged = $currentOptions;
        foreach ($newOptions as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        // Ensure width/height/background always have defaults
        $merged['width'] = $merged['width'] ?? 800;
        $merged['height'] = $merged['height'] ?? 600;
        $merged['background'] = $merged['background'] ?? '#ffffff';

        return $merged;
    }

    private function replaceMapContent(Map $map, array $data): void
    {
        $map->links()->delete();
        $map->nodes()->delete();

        $nodeIdMap = $this->createNodes($map, $data['nodes'] ?? []);
        $this->createLinks($map, $data['links'] ?? [], $nodeIdMap);
    }

    private function createNodes(Map $map, array $nodesData): array
    {
        $nodeIdMap = [];

        foreach ($nodesData as $index => $nodeData) {
            $node = Node::create([
                'map_id' => $map->id,
                'label' => $nodeData['label'] ?? 'Node',
                'x' => isset($nodeData['x']) && is_numeric($nodeData['x']) ? (float) $nodeData['x'] : 0,
                'y' => isset($nodeData['y']) && is_numeric($nodeData['y']) ? (float) $nodeData['y'] : 0,
                'device_id' => isset($nodeData['device_id']) && is_numeric($nodeData['device_id']) ? (int) $nodeData['device_id'] : null,
                'meta' => is_array($nodeData['meta'] ?? null) ? $nodeData['meta'] : [],
            ]);

            $clientKey = $nodeData['id'] ?? $nodeData['node_id'] ?? $nodeData['_id'] ?? (string)$index;
            $nodeIdMap[$clientKey] = $node->id;
        }

        return $nodeIdMap;
    }

    private function createLinks(Map $map, array $linksData, array $nodeIdMap): void
    {
        foreach ($linksData as $linkData) {
            $sourceId = $this->resolveNodeId($linkData['src_node_id'] ?? $linkData['source'] ?? $linkData['src'] ?? null, $nodeIdMap);
            $targetId = $this->resolveNodeId($linkData['dst_node_id'] ?? $linkData['target'] ?? $linkData['dst'] ?? null, $nodeIdMap);
            if (!$sourceId || !$targetId) {
                Log::warning('LibreLiveTopology: dropped link with unresolvable node reference', [
                    'map_id' => $map->id,
                    'src' => $linkData['src_node_id'] ?? $linkData['source'] ?? $linkData['src'] ?? null,
                    'dst' => $linkData['dst_node_id'] ?? $linkData['target'] ?? $linkData['dst'] ?? null,
                ]);
                continue;
            }

            Link::create([
                'map_id' => $map->id,
                'src_node_id' => $sourceId,
                'dst_node_id' => $targetId,
                'port_id_a' => isset($linkData['port_id_a']) && is_numeric($linkData['port_id_a']) ? (int) $linkData['port_id_a'] : (isset($linkData['port_a']) && is_numeric($linkData['port_a']) ? (int) $linkData['port_a'] : null),
                'port_id_b' => isset($linkData['port_id_b']) && is_numeric($linkData['port_id_b']) ? (int) $linkData['port_id_b'] : (isset($linkData['port_b']) && is_numeric($linkData['port_b']) ? (int) $linkData['port_b'] : null),
                'bandwidth_bps' => isset($linkData['bandwidth_bps']) && is_numeric($linkData['bandwidth_bps']) ? (int) $linkData['bandwidth_bps'] : (isset($linkData['bandwidth']) && is_numeric($linkData['bandwidth']) ? (int) $linkData['bandwidth'] : null),
                'style' => $linkData['style'] ?? [],
            ]);
        }
    }

    private function resolveNodeId($clientId, array $nodeIdMap): ?int
    {
        if ($clientId === null) {
            return null;
        }

        return $nodeIdMap[$clientId] ?? null;
    }

    public function importMap(Request $request, array $validated): Map
    {
        $file = $request->file('file');
        if (!$file) {
            throw new \InvalidArgumentException('No file uploaded');
        }

        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON in map file: ' . json_last_error_msg());
        }

        if (!$data || !isset($data['nodes']) || !isset($data['links'])) {
            throw new \InvalidArgumentException('Invalid map file format: missing nodes or links');
        }

        $data = $this->sanitizeImportedData($data);

        try {
            return DB::transaction(function () use ($data, $validated) {
                $options = $data['options'] ?? [];
                if (empty($options)) {
                    $options = ['width' => 800, 'height' => 600, 'background' => '#ffffff'];
                }

                $map = Map::create([
                    'name' => $validated['name'],
                    'title' => $validated['title'] ?? $validated['name'],
                    'options' => $options,
                ]);

                $nodeIdMap = $this->createNodes($map, $data['nodes']);
                $this->createLinks($map, $data['links'], $nodeIdMap);

                return $map;
            });
        } catch (\Exception $e) {
            Log::error("Failed to import map: " . $e->getMessage());
            throw new \RuntimeException("Failed to import map: " . $e->getMessage(), 0, $e);
        }
    }

    private function sanitizeImportedData(array $data): array
    {
        if (!is_array($data['nodes']) || !is_array($data['links'])
            || count($data['nodes']) > 2000 || count($data['links']) > 2000) {
            throw new \InvalidArgumentException('Map import exceeds the node or link limit');
        }

        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $data['options'] = array_intersect_key($options, array_flip([
            'width', 'height', 'background', 'tags', 'default_node_style', 'default_link_style',
        ]));

        foreach (['width', 'height'] as $key) {
            if (isset($data['options'][$key]) && (!is_numeric($data['options'][$key])
                || (int) $data['options'][$key] < 100 || (int) $data['options'][$key] > 4096)) {
                throw new \InvalidArgumentException("Invalid map {$key}");
            }
            if (isset($data['options'][$key])) {
                $data['options'][$key] = (int) $data['options'][$key];
            }
        }

        foreach (['default_node_style', 'default_link_style'] as $key) {
            if (isset($data['options'][$key]) && !is_array($data['options'][$key])) {
                throw new \InvalidArgumentException("Invalid map {$key}");
            }
        }
        if (isset($data['options']['default_node_style'])) {
            $data['options']['default_node_style'] = $this->sanitizeImportedStyle(
                $data['options']['default_node_style'], ['color', 'label_color']
            );
        }
        if (isset($data['options']['default_link_style'])) {
            $data['options']['default_link_style'] = $this->sanitizeImportedStyle(
                $data['options']['default_link_style'], ['color', 'width', 'via_style']
            );
        }

        foreach ($data['nodes'] as $index => &$node) {
            if (!is_array($node)) {
                throw new \InvalidArgumentException("Invalid node at index {$index}");
            }
            $node['label'] = NodeLabelNormalizer::normalizeOrThrow((string) ($node['label'] ?? 'Node'));
            foreach (['x', 'y'] as $coordinate) {
                if (isset($node[$coordinate]) && (!is_numeric($node[$coordinate])
                    || (float) $node[$coordinate] < 0 || (float) $node[$coordinate] > 10000)) {
                    throw new \InvalidArgumentException("Invalid node {$coordinate} at index {$index}");
                }
            }
            if (isset($node['meta'])) {
                if (!is_array($node['meta']) || strlen((string) json_encode($node['meta'])) > 16384) {
                    throw new \InvalidArgumentException("Invalid node metadata at index {$index}");
                }
                if (isset($node['meta']['device_type']) && !in_array($node['meta']['device_type'], [
                    'auto', 'paloalto', 'netscaler', 'fortinet', 'firewall', 'router', 'switch', 'aruba', 'brocade', 'server'
                ], true)) {
                    throw new \InvalidArgumentException("Invalid device type at index {$index}");
                }
            }
            $node = array_intersect_key($node, array_flip(['id', 'label', 'x', 'y', 'device_id', 'meta']));
        }
        unset($node);

        foreach ($data['links'] as $index => &$link) {
            if (!is_array($link)) {
                throw new \InvalidArgumentException("Invalid link at index {$index}");
            }
            $link = array_intersect_key($link, array_flip([
                'src', 'dst', 'source', 'target', 'src_node_id', 'dst_node_id',
                'port_id_a', 'port_id_b', 'port_a', 'port_b', 'bandwidth_bps', 'bandwidth', 'style',
            ]));
            if (isset($link['style']) && (!is_array($link['style'])
                || strlen((string) json_encode($link['style'])) > 16384)) {
                throw new \InvalidArgumentException("Invalid link style at index {$index}");
            }
            if (isset($link['style'])) {
                $link['style'] = $this->sanitizeImportedStyle(
                    $link['style'], ['via_style', 'via_points', 'color', 'width']
                );
            }
        }
        unset($link);

        return $data;
    }

    private function sanitizeImportedStyle(array $style, array $allowedKeys): array
    {
        $style = array_intersect_key($style, array_flip($allowedKeys));
        foreach (['color', 'label_color'] as $key) {
            if (isset($style[$key]) && (!is_string($style[$key])
                || !preg_match('/^#[0-9a-fA-F]{6}$/', $style[$key]))) {
                throw new \InvalidArgumentException("Invalid style {$key}");
            }
        }
        if (isset($style['width']) && (!is_numeric($style['width'])
            || (float) $style['width'] < 0.5 || (float) $style['width'] > 20)) {
            throw new \InvalidArgumentException('Invalid style width');
        }
        if (isset($style['via_style']) && !in_array($style['via_style'], ['straight', 'angled', 'curved'], true)) {
            throw new \InvalidArgumentException('Invalid style via_style');
        }
        if (isset($style['via_points']) && (!is_array($style['via_points']) || count($style['via_points']) > 200)) {
            throw new \InvalidArgumentException('Invalid style via_points');
        }

        return $style;
    }

    /**
     * Produce a per-map data-integrity report for the admin diagnostics screen.
     *
     * Surfaces three classes of problem:
     *  - broken port associations: links whose port_id_a/port_id_b reference a
     *    ports row that does not exist, and nodes whose device_id is unknown;
     *  - missing RRD files: links with a valid port whose resolved .rrd file is
     *    absent on disk (reuses RrdDataService resolution);
     *  - orphaned llt rows: links referencing missing llt_nodes, and
     *    nodes/links that point at a deleted map.
     *
     * Reads are batched (a few aggregate queries, then in-memory grouping) and
     * per-map findings are capped at $limit so a huge broken install cannot
     * balloon memory.
     *
     * @param RrdDataService|null $rrdService RRD resolver; lazily resolved when null.
     * @param int $limit Maximum specific findings retained per map (0 = none).
     * @return array<string,mixed> ['maps' => list<array>, 'summary' => array]
     */
    public function getDataIntegrityIssues(?RrdDataService $rrdService = null, int $limit = 20): array
    {
        $rrdService = $rrdService ?? $this->resolveRrdService();

        // Reference id sets, fetched once with a handful of aggregate queries.
        $deviceSet = array_flip($this->devicesTable()->pluck('device_id')->all());
        $portSet = array_flip($this->portsTable()->whereNotNull('port_id')->pluck('port_id')->all());
        $nodeSet = array_flip(DB::table('llt_nodes')->pluck('id')->all());
        $mapSet = array_flip(DB::table('llt_maps')->pluck('id')->all());

        $maps = DB::table('llt_maps')->orderBy('id')->get(['id', 'name', 'title']);
        $nodes = DB::table('llt_nodes')->get(['id', 'map_id', 'device_id']);
        $links = DB::table('llt_links')->get(['id', 'map_id', 'src_node_id', 'dst_node_id', 'port_id_a', 'port_id_b']);

        // Prime the RRD resolver's port cache in bulk so per-link existence
        // checks below do not issue one query per port.
        $portsOfInterest = [];
        foreach ($links as $link) {
            foreach ([$link->port_id_a, $link->port_id_b] as $pid) {
                if ($pid !== null && (int) $pid > 0) {
                    $portsOfInterest[] = (int) $pid;
                }
            }
        }
        $rrdService?->preloadPortInfo($portsOfInterest);

        $byMap = [];
        $danglingNodes = 0;
        $danglingLinks = 0;

        foreach ($nodes as $node) {
            $mapId = (int) $node->map_id;
            if (!isset($mapSet[$mapId])) {
                $danglingNodes++;
                continue;
            }
            if (!isset($byMap[$mapId])) {
                $byMap[$mapId] = $this->emptyIntegrityBucket($limit);
            }
            $byMap[$mapId]['nodes']++;
            if ($node->device_id !== null && !isset($deviceSet[(int) $node->device_id])) {
                $byMap[$mapId]['orphan_nodes']++;
                $this->recordFinding($byMap[$mapId], 'orphan_node', "Node {$node->id} on map {$mapId} references unknown device {$node->device_id}", $limit);
            }
        }

        foreach ($links as $link) {
            $mapId = (int) $link->map_id;
            if (!isset($mapSet[$mapId])) {
                $danglingLinks++;
                continue;
            }
            if (!isset($byMap[$mapId])) {
                $byMap[$mapId] = $this->emptyIntegrityBucket($limit);
            }
            $byMap[$mapId]['links']++;

            $srcMissing = !isset($nodeSet[(int) $link->src_node_id]);
            $dstMissing = !isset($nodeSet[(int) $link->dst_node_id]);
            if ($srcMissing || $dstMissing) {
                $byMap[$mapId]['orphan_links']++;
                $this->recordFinding($byMap[$mapId], 'orphan_link',
                    "Link {$link->id} references missing node (src={$link->src_node_id}, dst={$link->dst_node_id})", $limit);
            }

            foreach ([['port_id_a', $link->port_id_a], ['port_id_b', $link->port_id_b]] as [$column, $pid]) {
                if ($pid === null || (int) $pid <= 0) {
                    continue;
                }
                $pidInt = (int) $pid;
                if (!isset($portSet[$pidInt])) {
                    $byMap[$mapId]['broken_links']++;
                    $this->recordFinding($byMap[$mapId], 'broken_port',
                        "Link {$link->id} {$column} references missing port {$pidInt}", $limit);
                } elseif ($rrdService !== null && !$rrdService->hasRrdFile($pidInt)) {
                    $byMap[$mapId]['missing_rrd']++;
                    $this->recordFinding($byMap[$mapId], 'missing_rrd',
                        "Link {$link->id} {$column} ({$pidInt}) has no RRD file", $limit);
                }
            }
        }

        $mapsOut = [];
        $summary = [
            'maps' => 0,
            'broken_links' => 0,
            'missing_rrd' => 0,
            'orphan_nodes' => 0,
            'orphan_links' => 0,
            'dangling_nodes' => $danglingNodes,
            'dangling_links' => $danglingLinks,
        ];

        foreach ($maps as $map) {
            $bucket = $byMap[$map->id] ?? $this->emptyIntegrityBucket($limit);
            $mapId = (int) $map->id;
            $summary['maps']++;
            $summary['broken_links'] += $bucket['broken_links'];
            $summary['missing_rrd'] += $bucket['missing_rrd'];
            $summary['orphan_nodes'] += $bucket['orphan_nodes'];
            $summary['orphan_links'] += $bucket['orphan_links'];

            $mapsOut[] = [
                'map_id' => $mapId,
                'name' => (string) ($map->title ?: $map->name),
                'nodes' => $bucket['nodes'],
                'links' => $bucket['links'],
                'broken_links' => $bucket['broken_links'],
                'missing_rrd' => $bucket['missing_rrd'],
                'orphan_nodes' => $bucket['orphan_nodes'],
                'orphan_links' => $bucket['orphan_links'],
                'total' => $bucket['total'],
                'findings' => $bucket['findings'],
            ];
        }

        return ['maps' => $mapsOut, 'summary' => $summary];
    }

    private function devicesTable()
    {
        return class_exists('\\App\\Models\\Device')
            ? \App\Models\Device::query()
            : DB::table('devices');
    }

    private function portsTable()
    {
        return class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::query()
            : DB::table('ports');
    }

    private function resolveRrdService(): ?RrdDataService
    {
        try {
            return app(RrdDataService::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function emptyIntegrityBucket(int $limit): array
    {
        return [
            'nodes' => 0,
            'links' => 0,
            'broken_links' => 0,
            'missing_rrd' => 0,
            'orphan_nodes' => 0,
            'orphan_links' => 0,
            'total' => 0,
            'findings' => [],
        ];
    }

    private function recordFinding(array &$bucket, string $type, string $message, int $limit): void
    {
        $bucket['total']++;
        if ($limit > 0 && count($bucket['findings']) < $limit) {
            $bucket['findings'][] = ['type' => $type, 'message' => $message];
        }
    }
}
