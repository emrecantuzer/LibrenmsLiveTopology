<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PortUtilService
{
    private RrdDataService $rrdService;

    public function __construct(RrdDataService $rrdService)
    {
        $this->rrdService = $rrdService;
    }

    /**
     * Prime the RrdDataService request-local caches for port and device info
     so getPortData() doesn't fire per-port queries during bulk link traffic
     reads. Delegates to the same RrdDataService instance used by getPortData.
     */
    public function preloadForPorts(array $portIds, array $deviceIds): void
    {
        $this->rrdService->preloadPortInfo($portIds);
        $this->rrdService->preloadDeviceInfo($deviceIds);
    }

    public function linkUtilBits(array $link): array
    {
        $portA = $link['port_id_a'] ?? null;
        $portB = $link['port_id_b'] ?? null;
        $bandwidth = $link['bandwidth_bps'] ?? null;
        // A port mapped to both ends is one measurement, not two opposing endpoints.
        // Comparing it with itself would force both directions to max(in, out).
        if ($portA && $portB && (int) $portA === (int) $portB) {
            $portB = null;
        }

        if (!$portA && !$portB) {
            return [
                'in_bps' => 0,
                'out_bps' => 0,
                'pct' => null,
                'err' => 'No ports configured',
            ];
        }

        $dataA = $portA ? $this->getPortData($portA) : ['in' => 0, 'out' => 0];
        $dataB = $portB ? $this->getPortData($portB) : ['in' => 0, 'out' => 0];

        // Use the graph's source port; never mix independently polled endpoints.
        $inBps = $portA ? $dataA['in'] : $dataB['out'];
        $outBps = $portA ? $dataA['out'] : $dataB['in'];

        if (!$bandwidth || $bandwidth <= 0) {
            $speeds = array_filter([
                $portA ? $this->rrdService->getPortSpeed((int) $portA) : null,
                $portB ? $this->rrdService->getPortSpeed((int) $portB) : null,
            ], fn ($speed) => $speed !== null && $speed > 0);
            $bandwidth = $speeds ? min($speeds) : null;
        }

        $utilization = null;
        if ($bandwidth && $bandwidth > 0) {
            // Use max for full-duplex links (both directions can saturate independently)
            $utilization = round(max($inBps, $outBps) / $bandwidth * 100, 2);
        }

        $result = [
            'in_bps' => $inBps,
            'out_bps' => $outBps,
            'pct' => $utilization,
            'bandwidth_bps' => $bandwidth,
            'err' => null,
        ];

        // Keep endpoint measurements available for server-side comparison.
        if (config('librelivetopology.debug', false)) {
            Log::debug('LibreLiveTopology linkUtilBits', [
                'port_a' => $portA,
                'port_b' => $portB,
                'a_in' => (int) $dataA['in'],
                'a_out' => (int) $dataA['out'],
                'b_in' => (int) $dataB['in'],
                'b_out' => (int) $dataB['out'],
                'in_bps' => $inBps,
                'out_bps' => $outBps,
            ]);
        }

        return $result;
    }

    public function getPortData(int $portId): array
    {
        // Use distinct cache key to avoid collision with DevicePortLookup
        // Version the key to discard values cached by older releases, and keep
        // live traffic short-lived so the embed agrees with the hover graph.
        $cacheKey = "librelivetopology.port.traffic.v3.{$portId}";
        $cacheTtl = 5;

        if (!class_exists(Cache::class)) {
            return $this->fetchPortData($portId);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($portId) {
            return $this->fetchPortData($portId);
        });
    }

    public function deviceAggregateBits(int $deviceId): array
    {
        $cacheKey = "librelivetopology.device.{$deviceId}.aggregate";
        $cacheTtl = config('librelivetopology.cache_ttl', 300);

        if (!class_exists(Cache::class)) {
            return $this->fetchDeviceAggregate($deviceId);
        }

        return Cache::remember($cacheKey, $cacheTtl, function () use ($deviceId) {
            return $this->fetchDeviceAggregate($deviceId);
        });
    }

    private function fetchPortData(int $portId): array
    {
        // RRD is the single source of truth
        $rrdData = $this->rrdService->getPortTraffic($portId);

        if ($rrdData !== null) {
            return $rrdData;
        }

        Log::warning("LibreLiveTopology: No RRD data for port {$portId}");
        return ['in' => 0, 'out' => 0];
    }

    private function fetchDeviceAggregate(int $deviceId): array
    {
        // Sum traffic from all ports on the device
        // This is a placeholder - would need to query all device ports
        Log::debug("LibreLiveTopology: Device aggregate not implemented for device {$deviceId}");
        return ['in' => 0, 'out' => 0];
    }
}
