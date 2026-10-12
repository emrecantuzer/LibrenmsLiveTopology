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
            $links[(string) ($link['id'] ?? count($links))] = $this->linkTrafficAt($link, $timestamp);
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

    /**
     * Historical counterpart of PortUtilService::linkUtilBits(): read one
     * endpoint (A, or B with directions swapped), never mix independently
     * polled ports, and report utilization as max(in, out) / bandwidth.
     */
    private function linkTrafficAt(array $link, int $timestamp): array
    {
        $portA = (int) ($link['port_id_a'] ?? 0);
        $portB = (int) ($link['port_id_b'] ?? 0);
        if ($portA > 0 && $portA === $portB) {
            $portB = 0;
        }

        $in = 0;
        $out = 0;
        if ($portA > 0) {
            $traffic = $this->rrdData->getPortTrafficAt($portA, $timestamp) ?? [];
            $in = (int) ($traffic['in'] ?? 0);
            $out = (int) ($traffic['out'] ?? 0);
        } elseif ($portB > 0) {
            $traffic = $this->rrdData->getPortTrafficAt($portB, $timestamp) ?? [];
            $in = (int) ($traffic['out'] ?? 0);
            $out = (int) ($traffic['in'] ?? 0);
        }

        $bandwidth = (int) ($link['bandwidth_bps'] ?? 0);
        if ($bandwidth <= 0) {
            $speeds = array_filter([
                $portA > 0 ? $this->rrdData->getPortSpeed($portA) : null,
                $portB > 0 ? $this->rrdData->getPortSpeed($portB) : null,
            ], fn ($speed) => $speed !== null && $speed > 0);
            $bandwidth = $speeds ? min($speeds) : 0;
        }

        $pct = $bandwidth > 0 ? round(max($in, $out) / $bandwidth * 100, 2) : null;

        return [
            'in_bps' => $in,
            'out_bps' => $out,
            'pct' => $pct,
            'bandwidth_bps' => $bandwidth ?: null,
            // Kept for clients that read the pre-1.13 key.
            'bandwidth' => $bandwidth ?: null,
            'source' => 'rrd-replay',
        ];
    }
}
