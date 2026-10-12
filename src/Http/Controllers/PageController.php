<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use LibreNMS\Plugins\LibreLiveTopology\Services\MapAccess;
use Exception;

class PageController extends Controller
{
    /** Tables that must exist before the plugin pages can render. */
    private const REQUIRED_TABLES = ['llt_maps', 'llt_nodes', 'llt_links'];

    /**
     * Display main plugin page
     */
    public function index(): View|RedirectResponse
    {
        // Check if database tables exist, redirect to installer if not
        if (!$this->isInstalled()) {
            return redirect()->route('librelivetopology.install');
        }

        // List only the maps this user may read (null = unrestricted)
        $visibleIds = app(MapAccess::class)->visibleMapIds();
        $maps = Map::withCount(['nodes', 'links'])
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('id', $visibleIds))
            ->orderBy('name')
            ->get();

        return view('LibreLiveTopology::index', compact('maps'));
    }

    /**
     * Display editor page
     */
    public function editor($mapId = null): View
    {
        $map = null;
        if ($mapId) {
            $map = Map::find($mapId);
            if (!$map) {
                abort(404, 'Map not found');
            }
            app(MapAccess::class)->authorizeView($map);
        }

        return view('LibreLiveTopology::editor', [
            'title' => $map ? 'Edit Map: ' . $map->name : 'Create New Map',
            'map' => $map,
            'mapId' => $mapId,
        ]);
    }

    /**
     * Display view page - redirects to embed for live visualization
     */
    public function view($mapId): RedirectResponse
    {
        $map = Map::find($mapId);

        if (!$map) {
            abort(404, 'Map not found');
        }

        app(MapAccess::class)->authorizeView($map);

        return redirect()->route('librelivetopology.embed', ['map' => $mapId]);
    }

    /**
     * Check if plugin is installed (database tables exist).
     * Uses the schema builder so the check works on every supported driver.
     */
    private function isInstalled(): bool
    {
        try {
            foreach (self::REQUIRED_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
