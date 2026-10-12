<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;

/**
 * Decides which maps a user may read, based on LibreNMS device permissions.
 *
 * Modes (`librelivetopology.access_mode`):
 * - devices (default): a map is visible when the user can access every
 *   device it references, through node devices or link ports.
 * - admin: only administrators and global-read users can read maps.
 * - all: any authenticated user can read every map (pre-1.13 behavior).
 *
 * Administrators and global-read users can always read every map.
 */
class MapAccess
{
    use AdminCheck;

    public const MODE_DEVICES = 'devices';
    public const MODE_ADMIN = 'admin';
    public const MODE_ALL = 'all';

    /** @var array<int|string, array<int>> Allowed device ids per user for this request. */
    private array $allowedCache = [];

    public function mode(): string
    {
        $mode = strtolower((string) config('librelivetopology.access_mode', self::MODE_DEVICES));

        return in_array($mode, [self::MODE_DEVICES, self::MODE_ADMIN, self::MODE_ALL], true)
            ? $mode
            : self::MODE_DEVICES;
    }

    public function canView(Map $map, ?object $user = null): bool
    {
        $visible = $this->visibleMapIds($user, [$map->id]);

        return $visible === null || in_array((int) $map->id, $visible, true);
    }

    /**
     * Abort with 403 unless the current user may read the map.
     */
    public function authorizeView(Map $map): void
    {
        if (!$this->canView($map)) {
            abort(403, 'You do not have access to all devices on this map.');
        }
    }

    /**
     * Ids of the maps the user may read, or null when the user is unrestricted.
     *
     * @param array<int>|null $candidateIds Limit the check to these maps.
     * @return array<int>|null
     */
    public function visibleMapIds(?object $user = null, ?array $candidateIds = null): ?array
    {
        $user ??= auth()->user();
        if (!$user) {
            return [];
        }

        $mode = $this->mode();
        if ($mode === self::MODE_ALL || $this->isPrivileged($user)) {
            return null;
        }
        if ($mode === self::MODE_ADMIN) {
            return [];
        }

        $allowed = $this->allowedDeviceIds($user);

        $mapIds = Map::query()
            ->when($candidateIds !== null, fn ($q) => $q->whereIn('id', $candidateIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $blocked = $this->mapsWithForeignDevices($allowed, $mapIds->all());

        return $mapIds->reject(fn ($id) => isset($blocked[$id]))->values()->all();
    }

    /**
     * Administrators and global-read users see every map.
     */
    protected function isPrivileged(object $user): bool
    {
        if ($this->userIsAdmin($user)) {
            return true;
        }

        return method_exists($user, 'hasGlobalRead') && $user->hasGlobalRead();
    }

    /**
     * Device ids the user may access in LibreNMS. Fails closed: when the
     * permission source is unavailable the user is treated as having none.
     *
     * @return array<int>
     */
    protected function allowedDeviceIds(object $user): array
    {
        $key = $user->user_id ?? $user->id ?? spl_object_id($user);
        if (isset($this->allowedCache[$key])) {
            return $this->allowedCache[$key];
        }

        $ids = [];
        try {
            if (class_exists('\App\Facades\Permissions')) {
                $ids = collect(\App\Facades\Permissions::devicesForUser($user))->all();
            } else {
                $ids = DB::table('devices_perms')
                    ->where('user_id', $user->user_id ?? $user->id ?? 0)
                    ->pluck('device_id')
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::warning('LibreLiveTopology: could not resolve device permissions', ['error' => $e->getMessage()]);
            $ids = [];
        }

        return $this->allowedCache[$key] = array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Maps (keys of the returned array) that reference a device outside $allowed.
     *
     * @param array<int> $allowed
     * @param array<int> $mapIds
     * @return array<int, true>
     */
    private function mapsWithForeignDevices(array $allowed, array $mapIds): array
    {
        if (empty($mapIds)) {
            return [];
        }

        $blocked = DB::table('llt_nodes')
            ->whereIn('map_id', $mapIds)
            ->whereNotNull('device_id')
            ->where('device_id', '!=', 0)
            ->when(!empty($allowed), fn ($q) => $q->whereNotIn('device_id', $allowed))
            ->distinct()
            ->pluck('map_id')
            ->all();

        try {
            foreach (['port_id_a', 'port_id_b'] as $column) {
                $blocked = array_merge($blocked, DB::table('llt_links')
                    ->join('ports', 'ports.port_id', '=', "llt_links.{$column}")
                    ->whereIn('llt_links.map_id', $mapIds)
                    ->when(!empty($allowed), fn ($q) => $q->whereNotIn('ports.device_id', $allowed))
                    ->distinct()
                    ->pluck('llt_links.map_id')
                    ->all());
            }
        } catch (\Throwable $e) {
            // Without the ports table the port devices cannot be checked; hide
            // every map that has mapped ports rather than leak their traffic.
            $blocked = array_merge($blocked, DB::table('llt_links')
                ->whereIn('map_id', $mapIds)
                ->where(fn ($q) => $q->where('port_id_a', '>', 0)->orWhere('port_id_b', '>', 0))
                ->distinct()
                ->pluck('map_id')
                ->all());
        }

        return array_fill_keys(array_map('intval', $blocked), true);
    }
}
