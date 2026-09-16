<?php

namespace LibreNMS\Plugins\LibreLiveTopology\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Redirect;
use LibreNMS\Plugins\LibreLiveTopology\Models\Map;
use Exception;

class PageController extends Controller
{
    /**
     * Display main plugin page
     */
    public function index(): View
    {
        // Check if database tables exist, redirect to installer if not
        if (!$this->isInstalled()) {
            return redirect()->route('librelivetopology.install');
        }

        // Get all maps with counts using Eloquent for proper model features
        $maps = Map::withCount(['nodes', 'links'])->orderBy('name')->get();

        return view('LibreLiveTopology::index', compact('maps'));
    }

    /**
     * Display editor page
     */
    public function editor($mapId = null): View
    {
        $map = null;
        if ($mapId) {
            $map = DB::table('llt_maps')->find($mapId);
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
    public function view($mapId)
    {
        $map = DB::table('llt_maps')->find($mapId);

        if (!$map) {
            abort(404, 'Map not found');
        }

        return redirect()->route('librelivetopology.embed', ['map' => $mapId]);
    }

    /**
     * Check if plugin is installed (database tables exist)
     */
    private function isInstalled(): bool
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'llt_%'");
            return count($tables) >= 3;
        } catch (Exception $e) {
            return false;
        }
    }
}
