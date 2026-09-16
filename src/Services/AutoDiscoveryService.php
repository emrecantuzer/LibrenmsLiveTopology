<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Models\Node;
use LibreNMS\Plugins\LibreLiveTopology\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDiscoveryService
{
    /** LibreNMS topology protocols we trust for neighbor discovery. */
    private const TOPOLOGY_PROTOCOLS = ['lldp', 'xdp', 'cdp'];

    /**
     * Discover the topology around the map's candidate devices and seed
     * missing llt nodes + links from LibreNMS LLDP/XDP/CDP data.
     *
     * Returns a summary array (nodes_added, links_added) for the UI.
     */
    public function discoverAndSeedMap(Map $map, array $params): array
    {
        $devices = $this->discoverDevices($params);
        $candidateIds = array_map('intval', array_column($devices, 'device_id'));

        if (empty($candidateIds)) {
            Log::info("LibreLiveTopology: Auto-discovery found no candidate devices for map {$map->id}.");
            return ['nodes_added' => 0, 'links_added' => 0];
        }

        $linkRows = $this->queryTopologyLinks($candidateIds);

        return DB::transaction(function () use ($map, $devices, $candidateIds, $linkRows, $params) {
            // Serialize discovery requests for the same map and roll back partial imports.
            $map = Map::whereKey($map->id)->lockForUpdate()->firstOrFail();
            $existingNodes = $this->getExistingNodeMapping($map);
            $knownDeviceIds = array_values(array_unique(array_merge($candidateIds, array_keys($existingNodes))));
            $deviceDegrees = $this->calculateDeviceDegrees($linkRows, $knownDeviceIds);
            $nodeMapping = $this->createMissingNodes($map, $devices, $existingNodes, $params['minDegree'] ?? 0, $deviceDegrees, $linkRows);
            $nodesAdded = count($nodeMapping) - count($existingNodes);

            // Existing nodes can be outside the current OS filter and still be neighbors.
            $portsByDevice = empty($linkRows) ? [] : $this->getTopologyPorts(array_keys($nodeMapping));
            $links = $this->buildLinksFromTopology($linkRows, $nodeMapping, $portsByDevice);
            $linksAdded = $this->createDiscoveredLinks($map, $links, $nodeMapping);

            Log::info("LibreLiveTopology: Auto-discovery for map {$map->id} added {$nodesAdded} nodes and {$linksAdded} links.");

            return ['nodes_added' => $nodesAdded, 'links_added' => $linksAdded];
        });
    }

    public function validateDiscoveryParams(array $params): array
    {
        return [
            'minDegree' => max(0, (int) ($params['min_degree'] ?? 0)),
            'osFilter' => array_values(array_filter(array_map('trim', explode(',', trim((string) ($params['os'] ?? '')))), fn ($os) => $os !== '')),
        ];
    }

    private function discoverDevices(array $params): array
    {
        $query = $this->buildDeviceQuery($params['osFilter'] ?? []);
        $devices = $query->orderBy('device_id')->get()->toArray();

        return array_map(fn($device) => (array) $device, $devices);
    }

    private function buildDeviceQuery(array $osFilters): object
    {
        $baseQuery = class_exists('\\App\\Models\\Device')
            ? \App\Models\Device::where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os')
            : DB::table('devices')->where('disabled', 0)->where('ignore', 0)->select('device_id', 'hostname', 'os');

        if (!empty($osFilters)) {
            $baseQuery->where(function ($query) use ($osFilters) {
                foreach ($osFilters as $index => $filter) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->$method('os', 'like', '%' . $filter . '%');
                }
            });
        }

        return $baseQuery;
    }

    private function getExistingNodeMapping(Map $map): array
    {
        $mapping = [];
        foreach ($map->nodes()->where('device_id', '>', 0)->orderBy('id')->get(['id', 'device_id']) as $node) {
            // A map may intentionally show a device more than once; use its oldest node.
            $mapping[(int) $node->device_id] ??= (int) $node->id;
        }

        return $mapping;
    }

    private function createMissingNodes(Map $map, array $devices, array $existingNodes, int $minDegree, array $deviceDegrees, array $linkRows): array
    {
        $nodeMapping = $existingNodes;
        $gridLayout = new GridLayout(100, 100, 180, 22);
        $occupied = $map->nodes()->get(['device_id', 'x', 'y'])->map(fn ($node) => [
            'device_id' => (int) $node->device_id,
            'x' => (float) $node->x,
            'y' => (float) $node->y,
        ])->all();
        $positionsByDevice = [];
        foreach ($occupied as $position) {
            if ($position['device_id'] > 0) {
                $positionsByDevice[$position['device_id']] ??= $position;
            }
        }
        $freePositions = [];
        for ($slot = 0; $slot < 22 * 22; $slot++) {
            $position = $gridLayout->getNextPosition();
            if (!$this->positionIsOccupied($position, $occupied)) {
                $freePositions[$slot] = $position;
            }
        }
        $neighbors = [];
        foreach ($linkRows as $row) {
            $a = (int) ($row['local_device_id'] ?? 0);
            $b = (int) ($row['remote_device_id'] ?? 0);
            if ($a > 0 && $b > 0 && $a !== $b) {
                $neighbors[$a][$b] = true;
                $neighbors[$b][$a] = true;
            }
        }

        foreach ($devices as $device) {
            $deviceId = (int) ($device['device_id'] ?? 0);

            if (!$deviceId || isset($nodeMapping[$deviceId])) {
                continue;
            }

            if ($minDegree > 0 && ($deviceDegrees[$deviceId] ?? 0) < $minDegree) {
                continue;
            }

            if (!$freePositions) {
                throw new \InvalidArgumentException('Auto-discovery needs more free canvas space. Use an OS filter or a separate map.');
            }
            $adjacent = array_intersect_key($positionsByDevice, $neighbors[$deviceId] ?? []);
            $bestSlot = array_key_first($freePositions);
            if ($adjacent) {
                // Insert close to known physical neighbors without moving any
                // existing device. Below wins an equal-distance tie; logical
                // core/access ranks remain the editor's explicit Auto-Layout.
                $centerX = array_sum(array_column($adjacent, 'x')) / count($adjacent);
                $centerY = array_sum(array_column($adjacent, 'y')) / count($adjacent);
                $bestScore = INF;
                foreach ($freePositions as $slot => $candidate) {
                    $dx = $candidate['x'] - $centerX;
                    $dy = $candidate['y'] - $centerY;
                    $score = $dx * $dx + $dy * $dy + abs($dx) * .01 + ($dy < 0 ? 1 : 0);
                    if ($score < $bestScore) {
                        $bestSlot = $slot;
                        $bestScore = $score;
                    }
                }
            }
            $position = $freePositions[$bestSlot];
            unset($freePositions[$bestSlot]);
            $occupied[] = $position;
            $positionsByDevice[$deviceId] = $position;

            $node = Node::create([
                'map_id' => $map->id,
                'label' => $device['hostname'] ?? "Device {$deviceId}",
                'x' => $position['x'],
                'y' => $position['y'],
                'device_id' => $deviceId,
                'meta' => [],
            ]);

            $nodeMapping[$deviceId] = $node->id;
        }

        if (count($nodeMapping) > count($existingNodes)) {
            $options = $map->options ?? [];
            $options['width'] = max((int) ($options['width'] ?? 800), min(4096, (int) ceil(max(array_column($occupied, 'x')) + 100)));
            $options['height'] = max((int) ($options['height'] ?? 600), min(4096, (int) ceil(max(array_column($occupied, 'y')) + 100)));
            $map->update(['options' => $options]);
        }

        return $nodeMapping;
    }

    private function positionIsOccupied(array $position, array $occupied): bool
    {
        foreach ($occupied as $other) {
            if (abs($position['x'] - $other['x']) < 180 && abs($position['y'] - $other['y']) < 180) {
                return true;
            }
        }

        return false;
    }

    private function calculateDeviceDegrees(array $linkRows, array $deviceIds): array
    {
        $known = array_fill_keys($deviceIds, true);
        $neighbors = [];
        foreach ($linkRows as $row) {
            $a = (int) ($row['local_device_id'] ?? 0);
            $b = (int) ($row['remote_device_id'] ?? 0);
            if ($a <= 0 || $b <= 0 || $a === $b || !isset($known[$a], $known[$b])) {
                continue;
            }
            $neighbors[$a][$b] = true;
            $neighbors[$b][$a] = true;
        }

        return array_map('count', $neighbors);
    }

    /**
     * Pull LibreNMS topology links (LLDP/XDP/CDP) that touch any candidate
     * device, on either end (local or remote).
     */
    private function queryTopologyLinks(array $candidateIds): array
    {
        $query = class_exists('\\App\\Models\\Link')
            ? \App\Models\Link::whereIn('protocol', self::TOPOLOGY_PROTOCOLS)
            : DB::table('links')->whereIn('protocol', self::TOPOLOGY_PROTOCOLS);

        $query->where(function ($q) use ($candidateIds) {
            $q->whereIn('local_device_id', $candidateIds)
                ->orWhereIn('remote_device_id', $candidateIds);
        });

        $rows = $query->select(
            'local_device_id',
            'local_port_id',
            'remote_device_id',
            'remote_port_id',
            'protocol'
        )->get()->toArray();

        return array_map(fn($row) => (array) $row, $rows);
    }

    /** Fetch the ports owned by every mapped device, including existing neighbors. */
    private function getTopologyPorts(array $candidateIds): array
    {
        $query = class_exists('\\App\\Models\\Port')
            ? \App\Models\Port::whereIn('device_id', $candidateIds)
            : DB::table('ports')->whereIn('device_id', $candidateIds);

        $ports = $query->select('device_id', 'port_id')->get()->toArray();
        $ports = array_map(fn($port) => (array) $port, $ports);

        $grouped = [];
        foreach ($ports as $port) {
            $grouped[$port['device_id']][] = $port;
        }

        return $grouped;
    }

    /** Collapse duplicate observations of a circuit, preserving parallel ports. */
    private function buildLinksFromTopology(array $linkRows, array $nodeMapping, array $portsByDevice): array
    {
        $observations = [];

        foreach ($linkRows as $row) {
            $deviceA = (int) ($row['local_device_id'] ?? 0);
            $deviceB = (int) ($row['remote_device_id'] ?? 0);

            if ($deviceA <= 0 || $deviceB <= 0 || $deviceA === $deviceB) {
                continue;
            }

            // Both ends must correspond to llt nodes on this map.
            if (!isset($nodeMapping[$deviceA]) || !isset($nodeMapping[$deviceB])) {
                continue;
            }

            $portA = $this->resolveTopologyPort($deviceA, $row['local_port_id'] ?? null, $portsByDevice);
            $portB = $this->resolveTopologyPort($deviceB, $row['remote_port_id'] ?? null, $portsByDevice);
            $link = $this->normalizeEndpoints($deviceA, $deviceB, $portA, $portB);
            $observations[$this->createCircuitKey($link)] = $link;
        }

        // Resolve complete observations first so incomplete rows cannot hide circuits.
        uasort($observations, function ($a, $b) {
            $aPorts = (int) ($a['port_a'] !== null) + (int) ($a['port_b'] !== null);
            $bPorts = (int) ($b['port_a'] !== null) + (int) ($b['port_b'] !== null);
            return ($bPorts <=> $aPorts) ?: strcmp($this->createCircuitKey($a), $this->createCircuitKey($b));
        });

        $links = [];
        $byPair = [];
        foreach ($observations as $key => $link) {
            $pair = $this->createLinkKey($link['device_a'], $link['device_b']);
            $existing = $byPair[$pair] ?? [];
            if ($link['port_a'] === null && $link['port_b'] === null && $existing) {
                continue;
            }
            foreach ($existing as $other) {
                if ($this->samePhysicalLink($link, $other)) {
                    continue 2;
                }
            }
            $links[$key] = $link;
            $byPair[$pair][] = $link;
        }

        return $links;
    }

    /** LibreNMS links reference port_id; an unknown ID must not be guessed from ifIndex. */
    private function resolveTopologyPort(int $deviceId, $portRef, array $portsByDevice): ?int
    {
        if (!is_scalar($portRef) || !ctype_digit((string) $portRef) || (int) $portRef <= 0) {
            return null;
        }
        $portRef = (int) $portRef;

        $devicePorts = $portsByDevice[$deviceId] ?? [];

        foreach ($devicePorts as $port) {
            if ((int) ($port['port_id'] ?? 0) === $portRef) {
                return (int) $port['port_id'];
            }
        }

        return null;
    }

    private function normalizeEndpoints(int $deviceA, int $deviceB, ?int $portA, ?int $portB): array
    {
        return [
            'device_a' => min($deviceA, $deviceB),
            'device_b' => max($deviceA, $deviceB),
            'port_a' => ($deviceA <= $deviceB ? $portA : $portB) ?: null,
            'port_b' => ($deviceA <= $deviceB ? $portB : $portA) ?: null,
        ];
    }

    private function samePhysicalLink(array $a, array $b): bool
    {
        $sharedPort = false;
        foreach (['port_a', 'port_b'] as $end) {
            if ($a[$end] !== null && $b[$end] !== null) {
                if ($a[$end] !== $b[$end]) {
                    return false;
                }
                $sharedPort = true;
            }
        }

        return $sharedPort || ($a['port_a'] === null && $a['port_b'] === null
            && $b['port_a'] === null && $b['port_b'] === null);
    }

    private function createCircuitKey(array $link): string
    {
        return $this->createLinkKey($link['device_a'], $link['device_b'])
            . ':' . ($link['port_a'] ?? '?') . ':' . ($link['port_b'] ?? '?');
    }

    private function createLinkKey(int $deviceA, int $deviceB): string
    {
        return min($deviceA, $deviceB) . '-' . max($deviceA, $deviceB);
    }

    private function createDiscoveredLinks(Map $map, array $links, array $nodeMapping): int
    {
        $created = 0;
        if (!$links) {
            return $created;
        }

        $deviceByNode = $map->nodes()->where('device_id', '>', 0)->pluck('device_id', 'id')->all();
        $existingByPair = [];
        foreach ($map->links()->get() as $link) {
            $deviceA = (int) ($deviceByNode[$link->src_node_id] ?? 0);
            $deviceB = (int) ($deviceByNode[$link->dst_node_id] ?? 0);
            if (!$deviceA || !$deviceB || $deviceA === $deviceB) {
                continue;
            }
            $data = $this->normalizeEndpoints($deviceA, $deviceB, $link->port_id_a, $link->port_id_b);
            $pair = $this->createLinkKey($deviceA, $deviceB);
            $existingByPair[$pair][] = ['model' => $link, 'data' => $data, 'reversed' => $deviceA > $deviceB];
        }

        foreach ($links as $linkData) {
            $srcNode = $nodeMapping[$linkData['device_a']];
            $dstNode = $nodeMapping[$linkData['device_b']];
            $pair = $this->createLinkKey($linkData['device_a'], $linkData['device_b']);
            $existing = $existingByPair[$pair] ?? [];
            if ($linkData['port_a'] === null && $linkData['port_b'] === null && $existing) {
                continue;
            }

            $matches = array_keys(array_filter($existing, fn ($entry) => $this->samePhysicalLink($linkData, $entry['data'])));
            if ($matches) {
                // Enrich a uniquely identified circuit without replacing its style or direction.
                if (count($matches) === 1) {
                    $index = $matches[0];
                    $entry = &$existingByPair[$pair][$index];
                    foreach (['a', 'b'] as $end) {
                        if ($entry['data']['port_' . $end] === null && $linkData['port_' . $end] !== null) {
                            $modelEnd = $entry['reversed'] ? ($end === 'a' ? 'b' : 'a') : $end;
                            $entry['model']->{'port_id_' . $modelEnd} = $linkData['port_' . $end];
                            $entry['data']['port_' . $end] = $linkData['port_' . $end];
                        }
                    }
                    if ($entry['model']->isDirty()) {
                        $entry['model']->save();
                    }
                    unset($entry);
                }
                continue;
            }

            $link = Link::create([
                'map_id' => $map->id,
                'src_node_id' => $srcNode,
                'dst_node_id' => $dstNode,
                'port_id_a' => $linkData['port_a'],
                'port_id_b' => $linkData['port_b'],
                'bandwidth_bps' => null,
                'style' => [],
            ]);
            $existingByPair[$pair][] = ['model' => $link, 'data' => $linkData, 'reversed' => false];
            $created++;
        }

        return $created;
    }
}
