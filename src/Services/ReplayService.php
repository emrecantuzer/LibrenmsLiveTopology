<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use LibreNMS\Plugins\LibreLiveTopology\Models\Map;

class ReplayService
{
    public function __construct(
        private RrdDataService $rrdData,
        private MapVersionService $versions
    ) {
    }

    public function buildPayload(Map $map, int $timestamp): array
    {
        $version = $this->versions->getVersionAt($map, $timestamp);
        $snapshot = $version?->config_snapshot;
        if (!is_array($snapshot)) {
            $snapshot = $map->toJsonModel();
        }

        $links = [];
        foreach (($snapshot['links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $in = 0;
            $out = 0;
            foreach ([(int) ($link['port_id_a'] ?? 0), (int) ($link['port_id_b'] ?? 0)] as $portId) {
                if ($portId <= 0) {
                    continue;
                }
                $traffic = $this->rrdData->getPortTrafficAt($portId, $timestamp) ?? [];
                $in = max($in, (int) ($traffic['in'] ?? 0));
                $out = max($out, (int) ($traffic['out'] ?? 0));
            }
            $bandwidth = max(1, (int) ($link['bandwidth_bps'] ?? 1));
            $links[(string) ($link['id'] ?? count($links))] = [
                'in_bps' => $in,
                'out_bps' => $out,
                'pct' => min(100, (($in + $out) / $bandwidth) * 100),
                'bandwidth' => $bandwidth,
                'source' => 'rrd-replay',
            ];
        }

        return [
            'mode' => 'replay',
            'timestamp' => $timestamp,
            'version_id' => $version?->id,
            'map' => $snapshot,
            'links' => $links,
            'nodes' => [],
            'alerts' => [],
        ];
    }
}
