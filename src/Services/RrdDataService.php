<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use LibreNMS\Plugins\LibreLiveTopology\RRD\RRDTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RrdDataService
{
    private RRDTool $rrdTool;
    private array $portInfoCache = [];
    private array $deviceInfoCache = [];
    private ?string $rrdBase;

    public function __construct(RRDTool $rrdTool, ?string $rrdBase = null)
    {
        $this->rrdTool = $rrdTool;
        // Overridable via constructor injection (used by tests); defaults to the
        // configured LibreNMS RRD root when not provided.
        $this->rrdBase = $rrdBase ?? (string) config('librelivetopology.rrd_base', '/opt/librenms/rrd');
    }
    public function preloadPortInfo(array $portIds): void
    {
        $ids = array_values(array_unique(array_filter($portIds, fn ($id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        try {
            $rows = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::whereIn('port_id', $ids)
                    ->get(['device_id', 'ifIndex', 'ifName', 'port_id', 'ifSpeed'])->keyBy('port_id')
                : DB::table('ports')->whereIn('port_id', $ids)
                    ->get(['device_id', 'ifIndex', 'ifName', 'port_id', 'ifSpeed'])->keyBy('port_id');

            foreach ($ids as $id) {
                $row = $rows->get($id);
                $this->portInfoCache[$id] = $row
                    ? (method_exists($row, 'toArray') ? $row->toArray() : (array) $row)
                    : null;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to preload port info: ' . $e->getMessage());
        }
    }

    public function preloadDeviceInfo(array $deviceIds): void
    {
        $ids = array_values(array_unique(array_filter($deviceIds, fn ($id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        try {
            $rows = class_exists('\\App\\Models\\Device')
                ? \App\Models\Device::whereIn('device_id', $ids)
                        ->get(['device_id', 'hostname', 'sysName'])->keyBy('device_id')
                : DB::table('devices')->whereIn('device_id', $ids)
                        ->get(['device_id', 'hostname', 'sysName'])->keyBy('device_id');

            foreach ($ids as $id) {
                $this->deviceInfoCache[$id] = $rows->has($id) ? (array) $rows->get($id) : null;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to preload device info: ' . $e->getMessage());
        }
    }

    public function getPortSpeed(int $portId): ?int
    {
        $speed = $this->getPortInfo($portId)['ifSpeed'] ?? null;
        return is_numeric($speed) && $speed > 0 ? (int) $speed : null;
    }

    public function getPortTraffic(int $portId): ?array
    {
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return null;
        }

        $rrdPath = $this->resolvePortRrdPath($port);
        if (!$rrdPath || !file_exists($rrdPath)) {
            return null;
        }

        return $this->fetchTrafficFromRrd($rrdPath);
    }

    public function getPortTrafficAt(int $portId, int $timestamp): ?array
    {
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return null;
        }

        $rrdPath = $this->resolvePortRrdPath($port);
        if (!$rrdPath) {
            return null;
        }

        return [
            'in' => $this->octetsToBits($this->rrdTool->getValueAt($rrdPath, 'traffic_in', $timestamp)),
            'out' => $this->octetsToBits($this->rrdTool->getValueAt($rrdPath, 'traffic_out', $timestamp)),
        ];
    }

    /**
     * Whether the RRD file backing a port exists on disk. Reuses the exact
     * path resolution used by getPortTraffic() so integrity checks agree with
     * live traffic reads. Returns false when the port is unknown, the device
     * is unresolvable, or no matching .rrd file is present.
     */
    public function hasRrdFile(int $portId): bool
    {
        $port = $this->getPortInfo($portId);
        if (!$port) {
            return false;
        }

        return $this->resolvePortRrdPath($port) !== null;
    }

    private function getPortInfo(int $portId): ?array
    {
        if (array_key_exists($portId, $this->portInfoCache)) {
            return $this->portInfoCache[$portId];
        }

        try {
            $query = class_exists('\\App\\Models\\Port')
                ? \App\Models\Port::select('device_id', 'ifIndex', 'ifName', 'port_id', 'ifSpeed')
                    ->where('port_id', $portId)->first()
                : DB::table('ports')->select('device_id', 'ifIndex', 'ifName', 'port_id', 'ifSpeed')
                    ->where('port_id', $portId)->first();

            return $this->portInfoCache[$portId] = $query
                ? (method_exists($query, 'toArray') ? $query->toArray() : (array) $query)
                : null;
        } catch (\Exception $e) {
            Log::debug("Failed to get port info for port {$portId}: " . $e->getMessage());
            return null;
        }
    }

    private function resolvePortRrdPath(array $port): ?string
    {
        $config = $this->rrdBase;
        $deviceId = (int) ($port['device_id'] ?? 0);
        if ($deviceId <= 0) {
            Log::debug('LibreLiveTopology: Port has no valid LibreNMS device ID', [
                'port_id' => $port['port_id'] ?? null,
            ]);
            return null;
        }

        // LibreNMS' standard filename is authoritative. Resolve it directly
        // before relying on the device model's hostname representation.
        $portId = (int) ($port['port_id'] ?? 0);
        if ($portId > 0) {
            $directPath = $this->findPortRrdById($config, $portId);
            if ($directPath !== null) {
                return $directPath;
            }
        }

        $device = $this->getDeviceInfo($deviceId);

        if (!$device) {
            return null;
        }

        $hostname = $device['hostname'] ?? $device['sysName'] ?? '';
            if (!$hostname) {
            return null;
        }

        $ifIndex = $port['ifIndex'] ?? '';
        $ifName = $port['ifName'] ?? '';

        // Sanitize ifName for filesystem (LibreNMS replaces / : and spaces with -)
        $sanitizedIfName = preg_replace('/[\/\s:]+/', '-', $ifName);

            $deviceDirectories = ["{$config}/{$hostname}"];

        // LibreNMS stores port RRDs under the device hostname; older layouts
        // may use ifIndex instead of port_id.
        $patterns = [];
        foreach ($deviceDirectories as $directory) {
            $patterns[] = "{$directory}/port-id{$portId}.rrd";
            if ($sanitizedIfName !== '') {
                $patterns[] = "{$directory}/port-{$sanitizedIfName}.rrd";
            }
            if ($ifIndex !== '') {
                $patterns[] = "{$directory}/port-{$ifIndex}.rrd";
                $patterns[] = "{$directory}/port_{$ifIndex}.rrd";
            }
        }

        foreach ($patterns as $pattern) {
            if (file_exists($pattern)) {
                return $pattern;
            }
        }

        Log::debug("LibreLiveTopology: No RRD file found for port {$port['port_id']} ({$ifName}), tried: " . implode(', ', $patterns));
        return null;
    }

    private function findPortRrdById(string $rrdBase, int $portId): ?string
    {
        $pattern = rtrim($rrdBase, '/') . '/*/port-id' . $portId . '.rrd';
        $matches = glob($pattern, GLOB_NOSORT);

        if (!empty($matches)) {
            return $matches[0];
        }

        if (!is_dir($rrdBase)) {
            return null;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rrdBase, \FilesystemIterator::SKIP_DOTS)
            );
            $filename = 'port-id' . $portId . '.rrd';
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $filename) {
                    Log::debug('LibreLiveTopology: Resolved port RRD by recursive search', [
                        'port_id' => $portId,
                        'path' => $file->getPathname(),
                    ]);
                    return $file->getPathname();
                }
            }
        } catch (\UnexpectedValueException $e) {
            Log::debug("LibreLiveTopology: RRD directory search failed for port {$portId}: " . $e->getMessage());
        }

        return null;
    }

    private function getDeviceInfo(int $deviceId): ?array
    {
        if (array_key_exists($deviceId, $this->deviceInfoCache)) {
            return $this->deviceInfoCache[$deviceId];
        }

        try {
            $device = class_exists('\\App\\Models\\Device')
                    ? \App\Models\Device::select('hostname', 'sysName')
                    ->where('device_id', $deviceId)->first()
                    : DB::table('devices')->select('hostname', 'sysName')
                    ->where('device_id', $deviceId)->first();

            return $this->deviceInfoCache[$deviceId] = $device
                ? (method_exists($device, 'toArray') ? $device->toArray() : (array) $device)
                : null;
        } catch (\Exception $e) {
            Log::debug("Failed to get device info for device {$deviceId}: " . $e->getMessage());
            return null;
        }
    }

    // LibreNMS port RRD datasources are octets/second; port_bits graphs multiply by 8.
    private function octetsToBits($value): ?int
    {
        return is_numeric($value) && is_finite((float) $value) && $value >= 0
            ? (int) round((float) $value * 8) : null;
    }

    private function fetchTrafficFromRrd(string $rrdPath): array
    {
        try {
            $values = $this->rrdTool->getLastValues($rrdPath);

            return [
                'in' => $this->octetsToBits($values['traffic_in'] ?? null) ?? 0,
                'out' => $this->octetsToBits($values['traffic_out'] ?? null) ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to fetch traffic from RRD {$rrdPath}: " . $e->getMessage());
            return ['in' => 0, 'out' => 0];
        }
    }
}
