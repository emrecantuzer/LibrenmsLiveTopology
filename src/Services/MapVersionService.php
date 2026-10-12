<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use Illuminate\Support\Facades\DB;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use LibreNMS\Plugins\LibreLiveTopology\Models\MapVersion;

class MapVersionService
{
    /** Snapshots kept per map when `librelivetopology.version_retention` is unset. */
    public const DEFAULT_RETENTION = 20;

    /** Upper bound for listing when retention is disabled (0 = keep everything). */
    public const UNLIMITED_LIST_LIMIT = 100;

    public function createVersion(Map $map, string $name, ?string $description = null, ?int $userId = null): MapVersion
    {
        $version = MapVersion::create([
            'map_id' => $map->id,
            'name' => $name,
            'description' => $description,
            'config_snapshot' => $this->captureSnapshot($map),
            'created_by' => $userId,
        ]);

        $this->pruneVersions($map->id, [$version->id]);

        return $version;
    }

    /**
     * Number of snapshots kept per map. 0 or a negative value disables pruning.
     */
    public function retentionLimit(): int
    {
        return (int) config('librelivetopology.version_retention', self::DEFAULT_RETENTION);
    }

    /**
     * How many versions the history list should return.
     */
    public function listLimit(): int
    {
        $retention = $this->retentionLimit();

        return $retention > 0 ? $retention : self::UNLIMITED_LIST_LIMIT;
    }

    /**
     * Delete the oldest snapshots of a map beyond the retention limit.
     * Versions in $keepIds are never deleted, even when they are the oldest.
     *
     * @param array<int> $keepIds
     * @return int Number of deleted versions.
     */
    public function pruneVersions(int $mapId, array $keepIds = []): int
    {
        $retention = $this->retentionLimit();
        if ($retention <= 0) {
            return 0;
        }

        $staleIds = MapVersion::where('map_id', $mapId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($retention)
            ->reject(fn ($id) => in_array($id, $keepIds, true))
            ->values()
            ->all();

        if (empty($staleIds)) {
            return 0;
        }

        return MapVersion::whereIn('id', $staleIds)->delete();
    }

    /**
     * Restore a map to a snapshot. Unless $backup is false, the current state
     * is first saved as an automatic snapshot so the restore can be undone.
     */
    public function restoreVersion(MapVersion $version, ?int $userId = null, bool $backup = true): Map
    {
        $map = $version->map;
        $mapId = $version->map_id;

        // config_snapshot is cast to 'array' on the model, so $version->config_snapshot
        // is already a PHP array. Handle both string and array defensively in case
        // the cast is removed or the raw attribute is accessed via getRawOriginal().
        $raw = $version->config_snapshot;
        $snapshot = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('Version snapshot is corrupt or empty.');
        }

        return DB::transaction(function () use ($map, $mapId, $snapshot, $version, $userId, $backup) {
            if ($backup) {
                $backupVersion = MapVersion::create([
                    'map_id' => $mapId,
                    'name' => mb_substr('Before restore: ' . $version->name, 0, 100),
                    'description' => 'Automatic snapshot created before restoring version #' . $version->id . '.',
                    'config_snapshot' => $this->captureSnapshot($map),
                    'created_by' => $userId,
                ]);
                $this->pruneVersions($mapId, [$backupVersion->id, $version->id]);
            }

            // True rollback: delete links first (FK), then nodes, then
            // recreate from snapshot preserving original IDs so link
            // src_node_id/dst_node_id references stay valid.
            $map->links()->delete();
            $map->nodes()->delete();

            if (isset($snapshot['nodes']) && is_array($snapshot['nodes'])) {
                $allowedNode = array_flip(['id', 'label', 'x', 'y', 'device_id', 'meta']);
                foreach ($snapshot['nodes'] as $nodeData) {
                    if (!is_array($nodeData)) continue;
                    $data = array_merge(['map_id' => $mapId], array_intersect_key($nodeData, $allowedNode));
                    // forceCreate bypasses fillable to preserve the original id.
                    Node::forceCreate($data);
                }
            }

            if (isset($snapshot['links']) && is_array($snapshot['links'])) {
                $allowedLink = array_flip(['id', 'src_node_id', 'dst_node_id', 'port_id_a', 'port_id_b', 'bandwidth_bps', 'style']);
                foreach ($snapshot['links'] as $linkData) {
                    if (!is_array($linkData)) continue;
                    $data = array_merge(['map_id' => $mapId], array_intersect_key($linkData, $allowedLink));
                    Link::forceCreate($data);
                }
            }

            $map->title = $snapshot['map']['title'] ?? $map->title;
            $options = $map->options;
            if (is_string($options)) $options = json_decode($options, true) ?: [];
            if (!is_array($options)) $options = [];
            if (isset($snapshot['map']['width'])) {
                $options['width'] = $snapshot['map']['width'];
            }
            if (isset($snapshot['map']['height'])) {
                $options['height'] = $snapshot['map']['height'];
            }
            if (isset($snapshot['map']['background'])) {
                $options['background'] = $snapshot['map']['background'];
            }
            $map->options = $options;
            $map->save();

            return $map->fresh();
        });
    }

    public function deleteVersion(MapVersion $version): void
    {
        $version->delete();
    }

    public function getVersions(Map $map, int $limit = 10): \Illuminate\Support\Collection
    {
        return MapVersion::with('creator')->versions($map->id, $limit)->get();
    }

    public function getVersion(Map $map, int $versionId): ?MapVersion
    {
        return MapVersion::where('map_id', $map->id)->find($versionId);
    }

    public function getLatestVersion(Map $map): ?MapVersion
    {
        return MapVersion::latestForMap($map->id)->first();
    }

    public function getVersionAt(Map $map, int $timestamp): ?MapVersion
    {
        return MapVersion::where('map_id', $map->id)
            ->where('created_at', '<=', date('Y-m-d H:i:s', $timestamp))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function compareVersions(MapVersion $version1, MapVersion $version2): array
    {
        [$nodesAdded, $nodesRemoved] = $this->getAddedRemovedIds($version1, $version2, 'nodes');
        [$linksAdded, $linksRemoved] = $this->getAddedRemovedIds($version1, $version2, 'links');

        return [
            'nodes_added' => $nodesAdded,
            'nodes_removed' => $nodesRemoved,
            'nodes_modified' => $this->getModifiedIds($version1, $version2, 'nodes'),
            'links_added' => $linksAdded,
            'links_removed' => $linksRemoved,
            'links_modified' => $this->getModifiedIds($version1, $version2, 'links'),
        ];
    }

    private function captureSnapshot(Map $map): array
    {
        return [
            'map' => [
                'id' => $map->id,
                'name' => $map->name,
                'title' => $map->title,
                'width' => $map->width,
                'height' => $map->height,
                'background' => $map->background,
            ],
            'nodes' => $map->nodes->map(fn($node) => [
                'id' => $node->id,
                'label' => $node->label,
                'x' => $node->x,
                'y' => $node->y,
                'device_id' => $node->device_id,
                'meta' => $node->meta,
            ])->keyBy('id'),
            'links' => $map->links->map(fn($link) => [
                'id' => $link->id,
                'src_node_id' => $link->src_node_id,
                'dst_node_id' => $link->dst_node_id,
                'port_id_a' => $link->port_id_a,
                'port_id_b' => $link->port_id_b,
                'bandwidth_bps' => $link->bandwidth_bps,
                'style' => $link->style,
            ])->keyBy('id'),
        ];
    }

    private function getSnapshotNodes(MapVersion $version): \Illuminate\Support\Collection
    {
        $snapshot = $version->config_snapshot;
        if (!is_array($snapshot)) {
            $snapshot = json_decode($snapshot ?? '', true) ?: [];
        }
        return collect($snapshot['nodes'] ?? []);
    }

    private function getSnapshotLinks(MapVersion $version): \Illuminate\Support\Collection
    {
        $snapshot = $version->config_snapshot;
        if (!is_array($snapshot)) {
            $snapshot = json_decode($snapshot ?? '', true) ?: [];
        }
        return collect($snapshot['links'] ?? []);
    }

    private function getAddedRemovedIds(MapVersion $v1, MapVersion $v2, string $type): array
    {
        $items1 = $type === 'nodes' ? $this->getSnapshotNodes($v1) : $this->getSnapshotLinks($v1);
        $items2 = $type === 'nodes' ? $this->getSnapshotNodes($v2) : $this->getSnapshotLinks($v2);
        $ids1 = $items1->keys()->all();
        $ids2 = $items2->keys()->all();
        // Direction: v1 → v2 transition. Added = in v2 but not v1.
        // Removed = in v1 but not v2.
        return [
            array_values(array_diff($ids2, $ids1)),
            array_values(array_diff($ids1, $ids2)),
        ];
    }

    private function getModifiedIds(MapVersion $v1, MapVersion $v2, string $type): array
    {
        $items1 = $type === 'nodes' ? $this->getSnapshotNodes($v1) : $this->getSnapshotLinks($v1);
        $items2 = $type === 'nodes' ? $this->getSnapshotNodes($v2) : $this->getSnapshotLinks($v2);

        $modified = [];
        foreach ($items1 as $id => $item) {
            $item2 = $items2->get($id);
            if ($item2 && $item != $item2) {
                $modified[] = $id;
            }
        }

        return $modified;
    }
}
