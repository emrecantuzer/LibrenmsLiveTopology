<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibreLiveTopology - {{ $mapId }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #eef3f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
        }

        #map-container {
            width: 100vw;
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 50% 45%, rgba(255,255,255,0.98) 0%, rgba(247,250,252,0.96) 58%, rgba(235,241,246,0.98) 100%);
        }

        #map-canvas {
            border: none;
            display: block;
        }

        #overlay-canvas {
            position: absolute;
            pointer-events: none;
        }

        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #6c757d;
            background: #f8f9fa;
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            opacity: 0.7;
        }

        .loading span {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            height: 100%;
            color: #dc3545;
        }

        .error i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .status-bar {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-light {
            color: #212529;
            background-color: #fff;
            border-color: #ccc;
        }

        .llt-control-label {
            background: #fff;
            border: 1px solid #ccc;
            padding: 0.25rem 0.5rem;
            border-radius: 0.2rem;
            font-size: 0.875rem;
        }

        .llt-control-label select {
            border: 0;
            font-size: 0.875rem;
            background: transparent;
        }

        /* Nav bar */
        .embed-nav-bar {
            position: absolute; top: 0; left: 0; right: 0;
            background: rgba(18, 27, 38, 0.96); color: #fff;
            padding: 8px 16px; display: flex; justify-content: space-between;
            align-items: center; z-index: 1001; font-size: 13px;
            border-bottom: 1px solid rgba(89, 209, 178, 0.35);
            box-shadow: 0 2px 18px rgba(9, 16, 25, 0.22);
        }
        .embed-nav-left { display: flex; align-items: center; gap: 16px; }
        .embed-nav-right { display: flex; align-items: center; gap: 12px; }
        .embed-nav-link { color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .embed-nav-edit { color: #ffc107; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .embed-nav-demo { background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }

        /* Controls bar */
        .embed-controls {
            position: absolute; top: 55px; left: 10px; z-index: 1000;
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
            padding: 6px; border: 1px solid rgba(22, 38, 52, 0.12);
            border-radius: 12px; background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 8px 28px rgba(23, 39, 53, 0.14);
            backdrop-filter: blur(12px);
        }
        .embed-viz-menu {
            position: absolute; top: 100%; left: 0; background: #fff;
            border: 1px solid #ccc; border-radius: 4px; padding: 10px;
            min-width: 250px; display: none; margin-top: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .embed-viz-section { font-size: 12px; font-weight: bold; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .embed-viz-row { margin-bottom: 8px; }
        .embed-viz-label { font-size: 11px; display: block; }

        /* Legend */
        .embed-legend {
            position: absolute; bottom: 10px; left: 10px;
            background: rgba(18, 27, 38, 0.94); color: #edf7f4;
            border: 1px solid rgba(89, 209, 178, 0.35);
            border-radius: 8px; font-size: 12px; padding: 8px 10px; z-index: 1000;
            box-shadow: 0 5px 18px rgba(9, 16, 25, 0.2);
        }
        .embed-legend-title { font-weight: 600; margin-bottom: 4px; }

        .embed-legend .legend-row { color: #d8e8e4; }

        /* Tooltip */
        .embed-tooltip {
            position: absolute; background: rgba(0,0,0,0.8); color: #fff;
            padding: 6px 8px; border-radius: 4px; font-size: 12px;
            display: none; pointer-events: none;
        }

        /* Graph hover popup (RRD time-series image) */
        .embed-graph-popup {
            position: absolute; background: rgba(255,255,255,0.97);
            border: 1px solid #ccc; border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            padding: 8px; display: none; pointer-events: none;
            z-index: 1002; max-width: 420px;
        }
        .embed-graph-popup img {
            display: block; max-width: 400px; max-height: 180px;
            border-radius: 3px;
        }
        .embed-graph-popup .graph-loading {
            width: 300px; height: 120px; display: flex;
            align-items: center; justify-content: center;
            color: #6c757d; font-size: 13px;
        }
        .embed-graph-popup .graph-caption {
            margin-top: 4px; font-size: 11px; color: #333; text-align: center;
        }
        .embed-graph-popup .graph-text-fallback {
            background: rgba(0,0,0,0.8); color: #fff; padding: 6px 8px;
            border-radius: 4px; font-size: 12px;
        }

        /* Minimap */
        .embed-minimap {
            position: absolute; top: 55px; right: 10px;
            background: rgba(255,255,255,0.88); border: 1px solid rgba(22,38,52,0.14); border-radius: 10px;
            box-shadow: 0 8px 24px rgba(23,39,53,0.12);
        }

        /* Responsive: wrap nav and controls on small screens */
        @media (max-width: 640px) {
            .embed-nav-bar { flex-wrap: wrap; padding: 6px 10px; font-size: 12px; }
            .embed-nav-left { gap: 8px; flex-wrap: wrap; }
            .embed-nav-right { gap: 8px; }
            /* Push controls/minimap below the nav, which may wrap to 2 rows (~56px) */
            .embed-controls { top: 74px; gap: 4px; }
            .embed-minimap { top: 74px; }
            .embed-legend { font-size: 11px; padding: 4px 6px; }
        }
        @media (max-width: 480px) {
            .embed-minimap { display: none; }
            .embed-controls { top: 80px; }
        }
        /* Kiosk / NOC wall mode */
        body.kiosk-mode .embed-nav-bar,
        body.kiosk-mode .embed-controls,
        body.kiosk-mode .embed-legend,
        body.kiosk-mode .embed-minimap,
        body.kiosk-mode .status-bar,
        body.kiosk-mode .embed-graph-popup,
        body.kiosk-mode #loading {
            display: none !important;
        }
        body.kiosk-mode {
            cursor: none;
        }
        body.kiosk-mode.show-chrome {
            cursor: auto;
        }
        body.kiosk-mode.show-chrome .embed-nav-bar,
        body.kiosk-mode.show-chrome .embed-controls,
        body.kiosk-mode.show-chrome .embed-legend,
        body.kiosk-mode.show-chrome .embed-minimap,
        body.kiosk-mode.show-chrome .status-bar {
            display: flex !important;
        }
        body.kiosk-mode.show-chrome .embed-legend,
        body.kiosk-mode.show-chrome #loading {
            display: block !important;
        }
        .kiosk-exit {
            position: fixed;
            bottom: 48px;
            right: 10px;
            z-index: 1002;
            background: rgba(0,0,0,0.6);
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        body.kiosk-mode .kiosk-exit,
        body.kiosk-mode.show-chrome .kiosk-exit {
            opacity: 1;
            pointer-events: auto;
        }

        /* Keep the standalone embed independent from a LibreNMS Font Awesome
           path that may not exist on every installation. */
        .embed-nav-link .fas::before { content: '\2190'; }
        .embed-nav-edit .fas::before { content: '\270E'; }
        .status-bar .fas::before { content: '\25F7'; }
        #toggle-flow .fas::before { content: '\2248'; }
        #viz-settings .fas::before { content: '\2699'; }
        .fa-spinner { display: inline-block; width: 1em; height: 1em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; }
        .fa-spin { animation: embed-spin 0.8s linear infinite; }
        @keyframes embed-spin { to { transform: rotate(360deg); } }
    </style>
    <style>@include('LibreLiveTopology::partials.embed-noc-css')</style>
</head>
<body>
    <div id="map-container">
        <svg class="embed-icon-defs" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
            <symbol id="llt-icon-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
            <symbol id="llt-icon-zoom-in" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M11 8v6M8 11h6m6 9-3-3"/></symbol>
            <symbol id="llt-icon-zoom-out" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M8 11h6m6 9-3-3"/></symbol>
            <symbol id="llt-icon-reset" viewBox="0 0 24 24"><path d="M3 11a9 9 0 1 1 2.6 6.4M3 4v7h7"/></symbol>
            <symbol id="llt-icon-fit" viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></symbol>
            <symbol id="llt-icon-fullscreen" viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></symbol>
            <symbol id="llt-icon-flow" viewBox="0 0 24 24"><path d="M3 17h5l4-10 4 10h5M4 12h4m8 0h4"/></symbol>
            <symbol id="llt-icon-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
        </svg>
        <div id="nav-bar" class="embed-nav-bar">
            <div class="embed-nav-left">
                <span class="embed-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 17V7l8-4 8 4v10l-8 4-8-4Z"/><path d="m4 7 8 5 8-5M12 12v9"/></svg></span>
                <span class="embed-brand-text">LibreLive<span>Topology</span><small>NETWORK OPERATIONS</small></span>
                <div id="status-bar" class="status-bar" style="display: none;">
                    <button type="button" id="toggle-transport" class="btn btn-light btn-sm" aria-label="Live update status" title="Toggle live update transport">Live: loading…</button>
                    <span id="live-ping" class="embed-live-ping">RTT N/A</span>
                    <span class="embed-updated">Updated <span id="last-updated">Waiting…</span></span>
                </div>
            </div>
            <div class="embed-breadcrumb" aria-label="Map location">
                <a href="{{ url('plugin/LibreLiveTopology') }}" class="embed-nav-link">All maps</a>
                <span class="embed-breadcrumb-separator" aria-hidden="true">/</span>
                <strong title="{{ $mapData['title'] ?? $mapData['name'] ?? 'Map' }}">{{ $mapData['title'] ?? $mapData['name'] ?? 'Map' }}</strong>
                @if($demoMode ?? false)
                <span class="embed-nav-demo">DEMO MODE</span>
                @endif
            </div>
        </div>
        <div id="controls" class="embed-controls" role="toolbar" aria-label="Map controls">
                <label class="embed-search" title="Search nodes">
                    <svg aria-hidden="true"><use href="#llt-icon-search"></use></svg>
                    <input id="node-search" type="search" placeholder="Find device..." aria-label="Search nodes" autocomplete="off">
                    <kbd id="search-shortcut">Ctrl K</kbd>
                </label>
                <span id="search-result" class="embed-search-result" aria-live="polite"></span>
                <div id="zoom-controls" class="embed-zoom-controls"></div>
                <button type="button" id="zoom-fit" class="btn btn-light btn-sm" aria-label="Fit all devices" title="Fit all devices" data-tooltip="Fit all"><svg aria-hidden="true"><use href="#llt-icon-fit"></use></svg></button>
                <button type="button" id="toggle-fullscreen" class="btn btn-light btn-sm" aria-label="Toggle fullscreen" title="Toggle fullscreen" data-tooltip="Fullscreen"><svg aria-hidden="true"><use href="#llt-icon-fullscreen"></use></svg></button>
                <button type="button" id="toggle-flow" aria-pressed="true" class="btn btn-primary btn-sm" aria-label="Toggle flow animation" title="Toggle flow animation" data-tooltip="Traffic flow"><svg aria-hidden="true"><use href="#llt-icon-flow"></use></svg></button>
                <div class="embed-more">
                    <button type="button" id="viz-settings" aria-controls="viz-menu" aria-expanded="false" class="btn btn-light btn-sm" aria-label="Visualization settings" title="Visualization settings" data-tooltip="Display settings"><svg aria-hidden="true"><use href="#llt-icon-settings"></use></svg></button>
                    <div id="viz-menu" class="embed-viz-menu">
                        <div class="embed-viz-section">Display settings</div>
                        <label class="embed-viz-label" for="dashboard-mode">View mode</label>
                        <select id="dashboard-mode"><option value="0">Operations view</option><option value="1">Dashboard - hidden names</option></select>
                        <label class="embed-viz-label" for="metric-select">Link metric</label>
                        <select id="metric-select">
                            <option value="percent">Percent</option><option value="in">Inbound</option><option value="out">Outbound</option><option value="sum">In + Out</option>
                        </select>
                        <div class="embed-viz-row"><label class="embed-viz-label" for="particle-density">Particle density <span id="density-value">1.0</span></label><input type="range" id="particle-density" min="0.5" max="2" step="0.1" value="1"></div>
                        <div class="embed-viz-row"><label class="embed-viz-label" for="particle-speed">Particle speed <span id="speed-value">1.0</span></label><input type="range" id="particle-speed" min="0.5" max="2" step="0.1" value="1"></div>
                        <button type="button" id="collapse-bundles" class="btn btn-light btn-sm" aria-label="Collapse parallel link groups">Collapse link groups</button>
                        <a href="{{ url('plugin/LibreLiveTopology/editor/' . $mapId) }}" class="embed-nav-edit">Edit map</a>
                        <button type="button" id="export-png" class="btn btn-light btn-sm" aria-label="Export map as PNG" title="Export PNG">Export PNG</button>
                    </div>
                </div>
        </div>
        <div id="loading" class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <div>Loading map...</div>
        </div>
        <canvas id="map-canvas"></canvas>
        <canvas id="overlay-canvas"></canvas>
        <canvas id="minimap" width="160" height="120" class="embed-minimap"></canvas>
        <div id="graph-popup" class="embed-graph-popup"></div>
        <div id="legend" class="embed-legend">
            <div class="embed-legend-title">LINK UTILIZATION</div>
            <div id="legend-rows"></div>
        </div>
        <div class="embed-bottom-right"><span id="topology-summary" class="embed-topology-summary">Topology loading…</span><div id="alert-pill" class="embed-alert-pill" role="status" aria-live="polite">No critical alerts</div></div>
        <button type="button" id="kiosk-exit" class="kiosk-exit" aria-label="Exit kiosk mode">Exit Kiosk</button>
    </div>

    <script src="{{ asset('plugins/LibreLiveTopology/resources/js/topology-layout.js') }}?v={{ filemtime(public_path('plugins/LibreLiveTopology/resources/js/topology-layout.js')) }}"></script>
    <script src="{{ asset('plugins/LibreLiveTopology/resources/js/topology-ports.js') }}?v={{ filemtime(public_path('plugins/LibreLiveTopology/resources/js/topology-ports.js')) }}"></script>
    <script>
        function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
        const mapId = '{{ $mapId }}';
        const baseUrl = '{{ url("/") }}';
        const deviceBaseUrl = '{{ url("device") }}';
        const LLT_CONFIG = {
            kioskEnabled: @json($kiosk),
            cycleSeconds: @json($cycleSeconds),
            linkTarget: @json($target),
            mapList: @json($mapList ?? []),
            thresholds: @json(config('librelivetopology.thresholds') ?? [50, 80, 95]),
            colors: {
                link_normal: '{{ config('librelivetopology.colors.link_normal', '#28a745') }}',
                link_warning: '{{ config('librelivetopology.colors.link_warning', '#ffc107') }}',
                link_critical: '{{ config('librelivetopology.colors.link_critical', '#dc3545') }}',
                node_up: '{{ config('librelivetopology.colors.node_up', '#28a745') }}',
                node_down: '{{ config('librelivetopology.colors.node_down', '#dc3545') }}',
                node_warning: '{{ config('librelivetopology.colors.node_warning', '#ffc107') }}',
                node_unknown: '{{ config('librelivetopology.colors.node_unknown', '#6c757d') }}'
            },
            enable_sse: @json(config('librelivetopology.enable_sse') ?? true),
            client_refresh: @json(config('librelivetopology.client_refresh') ?? 60),
            scale: @json(config('librelivetopology.scale') ?? 'bits'),
            link_style: '{{ config('librelivetopology.link_style', 'straight') }}',
            show_bandwidth: @json(config('librelivetopology.show_bandwidth', true)),
            show_percentages: @json(config('librelivetopology.show_percentages', true)),
            show_node_metrics: @json(config('librelivetopology.show_node_metrics', true)),
        };
        const urlParams = new URLSearchParams(window.location.search);
        const param = (k, d) => urlParams.has(k) ? urlParams.get(k) : d;
        let scale = (param('scale', LLT_CONFIG.scale) || '').toLowerCase();
        if (scale !== 'bytes') scale = 'bits';
        let intervalSec = Math.max(5, Math.min(300, parseInt(param('interval', LLT_CONFIG.client_refresh), 10) || LLT_CONFIG.client_refresh || 60));
        let sseEnabled = param('sse', LLT_CONFIG.enable_sse ? '1' : '0') !== '0' && !!window.EventSource;
        let sseMax = parseInt(param('max', 300), 10) || 300;  // 5 minutes default
        let graphsEnabled = param('graphs', '1') !== '0' && !LLT_CONFIG.kioskEnabled;
        let dashboardMode = param('dashboard', '0') === '1';
        let nodeMetricsEnabled = param('metrics', LLT_CONFIG.show_node_metrics ? '1' : '0') !== '0';
        let eventSourceRef = null;
        let sseReconnectAttempts = 0;
        const maxReconnectAttempts = 5;
        const reconnectDelay = 2000; // 2 seconds
        let sseReconnectTimer = null;
        let currentTransport = 'none';
        let lastDataUpdate = null;
        let mapData = {};
        let livePortAnchors = null;
        try {
            mapData = @json($mapData ?? []);
            // Apply initial live data if provided
            const initialLive = @json($liveData ?? []);
            if (initialLive) {
                if (initialLive.links && Array.isArray(mapData.links)) {
                    mapData.links.forEach(l => {
                        const id = l.id ?? l.link_id ?? null;
                        if (id && initialLive.links[id]) {
                            l.live = initialLive.links[id];
                        }
                    });
                }
                // Apply initial node status, metrics, and traffic.
                if (initialLive.nodes && Array.isArray(mapData.nodes)) {
                    mapData.nodes.forEach(n => {
                        const id = n.id ?? n.node_id ?? null;
                        if (id && initialLive.nodes[id]) {
                            const ln = initialLive.nodes[id];
                            n.status = ln.status || n.status;
                            if (ln.metrics) n.metrics = ln.metrics;
                            if (ln.traffic) {
                                n.traffic = ln.traffic;
                                const sum = Number(ln.traffic.sum_bps || 0);
                                n.current_value = isFinite(sum) ? sum : null;
                            }
                        }
                    });
                }
                // Apply initial alert overlays.
                if (initialLive.alerts) {
                    if (initialLive.alerts.nodes && Array.isArray(mapData.nodes)) {
                        mapData.nodes.forEach(n => {
                            const id = n.id ?? n.node_id ?? null;
                            n.alerts = (id && initialLive.alerts.nodes[id]) ? initialLive.alerts.nodes[id] : { count: 0, severity: 'ok' };
                        });
                    }
                    if (initialLive.alerts.links && Array.isArray(mapData.links)) {
                        mapData.links.forEach(l => {
                            const id = l.id ?? l.link_id ?? null;
                            l.alerts = (id && initialLive.alerts.links[id]) ? initialLive.alerts.links[id] : { count: 0, severity: 'ok' };
                        });
                    }
                }
                lastDataUpdate = Date.now();
            }
        } catch (e) {
            console.error('Failed to parse map data:', e);
            mapData = { error: 'Invalid map data' };
        }
        // nodeById lookup map — rebuilt whenever mapData.nodes changes,
        // eliminates O(L*N) Array.find() per render frame in drawLink.
        let nodeById = new Map();
        rebuildNodeIndex();
        let canvas, ctx, overlayCanvas, overlayCtx, minimap;
        let viewScale = 1, viewOffsetX = 0, viewOffsetY = 0;
        let staticDirty = true;
        let hasActiveTraffic = false;
        let animationId;
        let lastUpdate = Date.now();
        let animTick = 0;
        let bgImg = null;
        let currentMetric = (param('metric', 'percent') || 'percent').toLowerCase();
        let searchQuery = '';
        const trendHistory = new Map();
        function nodeMatchesSearch(node) {
            return !searchQuery || (dashboardMode ? [nodeDisplayName(node), node.id] : [node.label, node.device_name, node.device_sysname, node.id]).some(value => String(value ?? '').toLowerCase().includes(searchQuery));
        }
        function linkMatchesSearch(link) {
            if (!searchQuery) return true;
            return [link.src ?? link.source ?? link.source_id, link.dst ?? link.target ?? link.destination_id]
                .some(id => { const node = nodeById.get(id); return node && nodeMatchesSearch(node); });
        }
        function recordTrendSamples() {
            const now = Date.now();
            const cutoff = now - 15 * 60 * 1000;
            for (const [type, items] of [['link', mapData.links || []], ['node', mapData.nodes || []]]) {
                for (const item of items) {
                    const key = type + ':' + item.id;
                    const traffic = type === 'link' ? item.live : liveNodeTraffic(item);
                    const rate = Number(traffic?.in_bps || 0) + Number(traffic?.out_bps || 0);
                    if (!Number.isFinite(rate)) continue;
                    const points = (trendHistory.get(key) || []).filter(point => point.t >= cutoff);
                    points.push({ t: now, value: rate });
                    trendHistory.set(key, points.slice(-180));
                }
            }
        }
        // Navigation (pan/zoom)
        const navEnabled = (param('nav', '1') !== '0');
        const MIN_ZOOM = parseFloat(param('minz', '0.5')) || 0.5;
        const MAX_ZOOM = parseFloat(param('maxz', '4')) || 4;
        let baseScale = 1, baseOffsetX = 0, baseOffsetY = 0;
        let userScale = 1, userOffsetX = 0, userOffsetY = 0;
        let fitMode = 'readable', isFocusedView = false;
        let isPanning = false, panLastX = 0, panLastY = 0;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let flowAnimationEnabled = !reducedMotion;
        let particleDensity = 1.0; // 0.5 to 2.0
        let particleSpeed = 1.0; // 0.5 to 2.0
        // Compute initial traffic state from any inline live data so the RAF
        // loop starts animating immediately when the page loads with traffic.
        hasActiveTraffic = Array.isArray(mapData.links) &&
            mapData.links.some(l => (l.live?.in_bps > 0 || l.live?.out_bps > 0));
        function rebuildNodeIndex() {
            nodeById = new Map();
            if (Array.isArray(mapData.nodes)) {
                for (const n of mapData.nodes) {
                    nodeById.set(n.id ?? n.src_node_id, n);
                }
            }
        }

        // Preserve authored coordinates; repair missing/collapsed positions or an explicit layout request.
        function applyTieredEmbedLayout() {
            const nodes = mapData.nodes;
            const mode = param('layout', 'auto');
            if (mode === 'saved' || !Array.isArray(nodes) || nodes.length < 2 || !window.LLTTopologyLayout) return false;
            const coordinates = nodes.map(node => [node.position?.x ?? node.x, node.position?.y ?? node.y]);
            const invalid = coordinates.some(point => point.some(value => value === null || value === undefined || value === '' || !Number.isFinite(Number(value))));
            const collapsed = new Set(coordinates.map(([x, y]) => `${Number(x)},${Number(y)}`)).size < nodes.length;
            if (mode !== 'tiered' && !invalid && !collapsed) return false;
            const links = mapData.links || [];
            const result = window.LLTTopologyLayout.layout(nodes, links.map(link => ({
                ...link, srcId: link.src ?? link.source ?? link.source_id,
                dstId: link.dst ?? link.target ?? link.destination_id,
            })), { nodeSpacing: 230, layerSpacing: 190 });
            if (result.overflow) return false;
            result.positions.forEach((position, index) => {
                const node = nodes[index];
                node.x = position.x; node.y = position.y;
                if (node.position) node.position = { ...node.position, x: position.x, y: position.y };
            });
            result.routes.forEach(route => {
                const link = links[route.index];
                if (link) link.style = { ...(link.style || {}), via_style: route.via_style, via_points: route.via_points };
            });
            return true;
        }

        function liveNodeTraffic(node) {
            const current = node.traffic || {};
            const currentSum = Number(current.sum_bps ?? node.current_value ?? 0) || 0;
            if (currentSum > 0) return current;

            let inBps = 0;
            let outBps = 0;
            const nodeId = String(node.id);
            (mapData.links || []).forEach(link => {
                const live = link.live || {};
                const sourceId = String(link.src ?? link.source ?? link.source_id ?? '');
                const targetId = String(link.dst ?? link.target ?? link.destination_id ?? '');
                const linkIn = Number(live.in_bps) || 0;
                const linkOut = Number(live.out_bps) || 0;
                if (sourceId === nodeId) {
                    inBps += linkIn;
                    outBps += linkOut;
                } else if (targetId === nodeId) {
                    inBps += linkOut;
                    outBps += linkIn;
                }
            });

            if (inBps + outBps <= 0) return current;
            return { in_bps: inBps, out_bps: outBps, sum_bps: inBps + outBps, source: 'links' };
        }

        function backfillNodeTrafficFromLinks() {
            if (!Array.isArray(mapData.nodes) || !Array.isArray(mapData.links)) return;

            const totals = new Map();
            const add = (nodeId, inBps, outBps) => {
                if (!nodeId) return;
                const key = String(nodeId);
                const current = totals.get(key) || { in_bps: 0, out_bps: 0 };
                current.in_bps += Number(inBps) || 0;
                current.out_bps += Number(outBps) || 0;
                totals.set(key, current);
            };

            mapData.links.forEach(link => {
                const live = link.live || {};
                const inBps = Number(live.in_bps) || 0;
                const outBps = Number(live.out_bps) || 0;
                add(link.src ?? link.source ?? link.source_id, inBps, outBps);
                add(link.dst ?? link.target ?? link.destination_id, outBps, inBps);
            });

            mapData.nodes.forEach(node => {
                const current = Number(node.traffic?.sum_bps ?? node.current_value ?? 0);
                if (current > 0) return;
                const total = totals.get(String(node.id));
                if (!total || total.in_bps + total.out_bps <= 0) return;
                node.traffic = {
                    in_bps: total.in_bps,
                    out_bps: total.out_bps,
                    sum_bps: total.in_bps + total.out_bps,
                    source: 'links',
                };
                node.current_value = node.traffic.sum_bps;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            initKioskMode();
            // Sync flow toggle button with prefers-reduced-motion default
            const _flowBtn = document.getElementById('toggle-flow');
            _flowBtn?.setAttribute('aria-pressed', String(flowAnimationEnabled));
            if (_flowBtn && !flowAnimationEnabled) {
                _flowBtn.classList.remove('btn-primary');
                _flowBtn.classList.add('btn-secondary');
            }
            if (mapData && !mapData.error) {
                applyTieredEmbedLayout();
                rebuildNodeIndex();
                backfillNodeTrafficFromLinks();
                recordTrendSamples();
                renderMap();
                startLiveUpdates();
                renderLegend();
                const ms = document.getElementById('metric-select');
                if (ms) { ms.value = currentMetric; ms.addEventListener('change', () => { currentMetric = ms.value; renderLegend(); staticDirty = true; renderMap(); }); }
                const ex = document.getElementById('export-png');
                if (ex) ex.addEventListener('click', exportPNG);
                if (navEnabled) initNavControls();
                window.setInterval(updateStatus, 1000);
                const search = document.getElementById('node-search');
                document.getElementById('search-shortcut').textContent = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K';
                search.addEventListener('input', () => {
                    searchQuery = search.value.trim().toLowerCase();
                    const matches = searchQuery ? (mapData.nodes || []).filter(nodeMatchesSearch).length : 0;
                    document.getElementById('search-result').textContent = searchQuery ? `${matches} found` : '';
                    staticDirty = true;
                    renderMap();
                });
                window.addEventListener("keydown", event => {
                    if (((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') ||
                        (event.key === '/' && !/input|textarea|select/i.test(document.activeElement?.tagName || ''))) {
                        event.preventDefault(); search.focus();
                    }
                });
                document.getElementById('collapse-bundles').addEventListener('click', () => {
                    expandedBundles.clear();
                    staticDirty = true;
                    renderMap();
                });
                document.getElementById('zoom-fit').addEventListener('click', () => resetView('all'));
                document.getElementById('toggle-fullscreen').addEventListener('click', () => {
                    const action = document.fullscreenElement ? document.exitFullscreen?.() : document.getElementById('map-container').requestFullscreen?.();
                    action?.catch?.(() => {});
                });
            } else {
                showError(mapData.error || 'Failed to load map');
            }
        });

        // Pause RAF animation and SSE polling when the tab is backgrounded
        // to save CPU and backend resources in long-lived kiosk operation.
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (animationId) { cancelAnimationFrame(animationId); animationId = null; }
                if (typeof stopSSE === 'function' && sseEnabled) stopSSE();
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            } else if (mapData && !mapData.error) {
                if (!animationId) startAnimationLoop();
                if (sseEnabled) startSSE();
                else if (!pollTimer) startAutoUpdate();
            }
        });

        function syncOverlayCanvas() {
            // Position and size the overlay canvas to exactly match the main canvas.
            // The main canvas is flex-centered inside #map-container, so we mirror
            // its rendered offset rather than assuming top-left alignment.
            const rect = canvas.getBoundingClientRect();
            const containerRect = canvas.parentElement.getBoundingClientRect();
            overlayCanvas.style.left = (rect.left - containerRect.left) + 'px';
            overlayCanvas.style.top = (rect.top - containerRect.top) + 'px';
            overlayCanvas.width = canvas.width;
            overlayCanvas.height = canvas.height;
            overlayCanvas.style.width = rect.width + 'px';
            overlayCanvas.style.height = rect.height + 'px';
        }

        function initCanvas() {
            canvas = document.getElementById('map-canvas');
            ctx = canvas.getContext('2d');
            overlayCanvas = document.getElementById('overlay-canvas');
            overlayCtx = overlayCanvas.getContext('2d');

            // Set canvas size
            const container = document.getElementById('map-container');
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            // Remove the flex sibling before measuring the canvas offset for the particle layer.
            document.getElementById('loading').style.display = 'none';
            syncOverlayCanvas();
            minimap = document.getElementById('minimap');
            const bgUrl = mapData.options?.background_image;
            if (bgUrl) {
                bgImg = new Image();
                bgImg.onload = () => { staticDirty = true; renderMap(); };
                bgImg.src = bgUrl;
            }
        }

        // Avoid off-screen O(N+L) work on pan/zoom: compute the visible map
        // rect (world coords) once per frame and skip nodes/links entirely
        // outside it. A node is drawn iff its center is inside (plus a margin);
        // a link is drawn unless its segment bounding-box misses the rect
        // entirely, so links that cross the viewport still render.
        function worldViewRect() {
            const margin = 44 / viewScale; // room for the wider device cards and labels
            return {
                left: (0 - viewOffsetX) / viewScale - margin,
                right: (canvas.width - viewOffsetX) / viewScale + margin,
                top: (0 - viewOffsetY) / viewScale - margin,
                bottom: (canvas.height - viewOffsetY) / viewScale + margin,
            };
        }
        function nodeInView(n, v) {
            const x = (n.position?.x ?? n.x) || 0;
            const y = (n.position?.y ?? n.y) || 0;
            return x >= v.left && x <= v.right && y >= v.top && y <= v.bottom;
        }

        function topologyBounds() {
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            const include = (x, y) => {
                if (!Number.isFinite(Number(x)) || !Number.isFinite(Number(y))) return;
                minX = Math.min(minX, Number(x));
                minY = Math.min(minY, Number(y));
                maxX = Math.max(maxX, Number(x));
                maxY = Math.max(maxY, Number(y));
            };
            for (const node of (mapData.nodes || [])) {
                include(node.position?.x ?? node.x, node.position?.y ?? node.y);
            }
            for (const link of (mapData.links || [])) {
                for (const point of (link.style?.via_points || [])) include(point.x, point.y);
            }
            if (!Number.isFinite(minX)) {
                return { minX: 0, minY: 0, maxX: mapData.width || 800, maxY: mapData.height || 600 };
            }
            return { minX, minY, maxX, maxY };
        }

        function fitViewport() {
            const bounds = topologyBounds();
            const chromeVisible = !document.body.classList.contains('kiosk-mode') || document.body.classList.contains('show-chrome');
            const padding = {
                left: Math.min(54, canvas.width * .08),
                right: Math.min(54, canvas.width * .08),
                top: chromeVisible ? Math.min(canvas.width <= 1100 ? 152 : 98, canvas.height * .35) : 54,
                bottom: chromeVisible ? Math.min(78, canvas.height * .18) : 54,
            };
            const contentPadding = 72;
            const minX = bounds.minX - contentPadding;
            const minY = bounds.minY - contentPadding;
            const width = Math.max(160, bounds.maxX - bounds.minX + contentPadding * 2);
            const height = Math.max(120, bounds.maxY - bounds.minY + contentPadding * 2);
            const availableWidth = Math.max(1, canvas.width - padding.left - padding.right);
            const availableHeight = Math.max(1, canvas.height - padding.top - padding.bottom);

            const fitScale = Math.min(availableWidth / width, availableHeight / height, 1.35);
            const readableScale = Math.min(.85, Math.max(.55, canvas.width / 1600));
            isFocusedView = fitMode === 'readable' && fitScale < readableScale;
            baseScale = isFocusedView ? readableScale : fitScale;
            if (isFocusedView) {
                const topNodes = (mapData.nodes || []).filter(node => Math.abs((node.position?.y ?? node.y ?? 0) - bounds.minY) < 1);
                const middleX = (bounds.minX + bounds.maxX) / 2;
                const focus = topNodes.sort((a, b) =>
                    Math.abs((a.position?.x ?? a.x ?? 0) - middleX) - Math.abs((b.position?.x ?? b.x ?? 0) - middleX))[0];
                const focusX = focus?.position?.x ?? focus?.x ?? middleX;
                const focusY = focus?.position?.y ?? focus?.y ?? bounds.minY;
                baseOffsetX = canvas.width / 2 - focusX * baseScale;
                baseOffsetY = padding.top + Math.min(120, availableHeight * .25) - focusY * baseScale;
            } else {
                baseOffsetX = padding.left + (availableWidth - width * baseScale) / 2 - minX * baseScale;
                baseOffsetY = padding.top + (availableHeight - height * baseScale) / 2 - minY * baseScale;
            }
        }
        function linkInView(link, v) {
            const srcId = link.source ?? link.src ?? link.source_id;
            const dstId = link.target ?? link.dst ?? link.destination_id;
            const srcNode = nodeById.get(srcId);
            const dstNode = nodeById.get(dstId);
            if (!srcNode && !dstNode) return false;
            if (!srcNode) return nodeInView(dstNode, v);
            if (!dstNode) return nodeInView(srcNode, v);
            // Build the path point list (endpoints + via_points) and test
            // the union AABB against the view rect, so bent links whose path
            // dips into the viewport stay visible even with off-screen ends.
            const ax = srcNode.position?.x ?? srcNode.x ?? 0;
            const ay = srcNode.position?.y ?? srcNode.y ?? 0;
            const bx = dstNode.position?.x ?? dstNode.x ?? 0;
            const by = dstNode.position?.y ?? dstNode.y ?? 0;
            let viaPoints = (link.style && link.style.via_points) || [];
            const viaStyle = (link.style && link.style.via_style) || defaultLinkStyle.via_style || LLT_CONFIG.link_style || 'straight';
            if (viaStyle === 'curved' && viaPoints.length === 0) {
                const x1 = ax, y1 = ay, x2 = bx, y2 = by;
                const dx = x2 - x1;
                const dy = y2 - y1;
                const length = Math.hypot(dx, dy) || 1;
                const directionSeed = String(link.id ?? link.source ?? link.src ?? '').split('')
                    .reduce((sum, char) => sum + char.charCodeAt(0), 0);
                const side = directionSeed % 2 === 0 ? 1 : -1;
                viaPoints = [{
                    x: (x1 + x2) / 2 - (dy / length) * 32 * side,
                    y: (y1 + y2) / 2 + (dx / length) * 32 * side,
                }];
            }
            const pts = [{x: ax, y: ay}, ...viaPoints, {x: bx, y: by}];
            let minX = Math.min(ax, bx), maxX = Math.max(ax, bx);
            let minY = Math.min(ay, by), maxY = Math.max(ay, by);
            for (const p of viaPoints) {
                if (p.x < minX) minX = p.x; else if (p.x > maxX) maxX = p.x;
                if (p.y < minY) minY = p.y; else if (p.y > maxY) maxY = p.y;
            }
            // For curved (Catmull-Rom → cubic bezier) links, the control
            // points cp1/cp2 can extend beyond the point hull and cause the
            // rendered curve to bulge outside the AABB. Include them so
            // on-screen curve segments aren't incorrectly culled.
            if (viaStyle === 'curved' && pts.length > 2) {
                for (let i = 0; i < pts.length - 1; i++) {
                    const p0 = pts[Math.max(0, i - 1)];
                    const p1 = pts[i];
                    const p2 = pts[i + 1];
                    const p3 = pts[Math.min(pts.length - 1, i + 2)];
                    const cp1x = p1.x + (p2.x - p0.x) / 6;
                    const cp1y = p1.y + (p2.y - p0.y) / 6;
                    const cp2x = p2.x - (p3.x - p1.x) / 6;
                    const cp2y = p2.y - (p3.y - p1.y) / 6;
                    for (const cp of [{x: cp1x, y: cp1y}, {x: cp2x, y: cp2y}]) {
                        if (cp.x < minX) minX = cp.x; else if (cp.x > maxX) maxX = cp.x;
                        if (cp.y < minY) minY = cp.y; else if (cp.y > maxY) maxY = cp.y;
                    }
                }
            }
            return Math.max(minX, v.left) <= Math.min(maxX, v.right)
                && Math.max(minY, v.top) <= Math.min(maxY, v.bottom);
        }

        function isLightMapBackground(color) {
            const hex = String(color || '').trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (!hex) return false;
            const full = hex[1].length === 3 ? hex[1].split('').map(char => char + char).join('') : hex[1];
            const rgb = [0, 2, 4].map(index => parseInt(full.slice(index, index + 2), 16));
            return (rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722) >= 175;
        }

        function renderMap(skipMinimap = false) {
            if (!mapData || !mapData.nodes) return;

            // Clear geometry arrays for hover/click detection
            nodeGeoms.length = 0;
            linkGeoms.length = 0;
            linkGeomByLink.clear();
            nodeLabelPlans.clear();
            // Reset per-node label placement slots so link labels fan out
            // fresh each frame instead of accumulating across renders.
            linkLabelSlots.clear();
            rebuildParallelLinkGroups();
            // Reset the reserved-rect registry used to keep link labels from
            // overlapping node labels and each other (see reserveRect/overlapsAny).
            placedLabelRects.length = 0;
            livePortAnchors = window.LLTPortAnchors?.build(mapData.nodes || [], mapData.links || [], {
                nodeId: node => node.id,
                nodeX: node => (node.position?.x ?? node.x) || 0,
                nodeY: node => (node.position?.y ?? node.y) || 0,
                sourceId: link => link.source ?? link.src ?? link.source_id,
                targetId: link => link.target ?? link.dst ?? link.destination_id,
                sourcePort: link => link.port_id_a,
                targetPort: link => link.port_id_b,
                sourceLabel: link => link.source_port_name,
                targetLabel: link => link.destination_port_name,
                halfSize: liveNodeHalfSize,
            }) || null;

            // Clear main canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Draw background
            if (bgImg) {
                ctx.drawImage(bgImg, 0, 0, canvas.width, canvas.height);
            } else {
                const mapBackground = String(mapData.background || '#ffffff').toLowerCase();
                const useDarkBackground = isLightMapBackground(mapBackground);
                if (useDarkBackground) {
                    const backgroundGradient = ctx.createRadialGradient(canvas.width * .52, canvas.height * .48, 0, canvas.width * .52, canvas.height * .48, Math.max(canvas.width, canvas.height) * .75);
                    backgroundGradient.addColorStop(0, '#111e31');
                    backgroundGradient.addColorStop(1, '#0b0f19');
                    ctx.fillStyle = backgroundGradient;
                } else {
                    ctx.fillStyle = mapData.background;
                }
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                if (useDarkBackground) {
                    ctx.fillStyle = 'rgba(116, 158, 189, .16)';
                    for (let y = 14; y < canvas.height; y += 28) {
                        for (let x = 14; x < canvas.width; x += 28) ctx.fillRect(x, y, 1.5, 1.5);
                    }
                }
            }

            // Fit the actual topology, not a potentially oversized saved canvas.
            fitViewport();

            // Effective transform for rendering and hit testing
            viewScale = baseScale * userScale;
            viewOffsetX = baseOffsetX + userOffsetX;
            viewOffsetY = baseOffsetY + userOffsetY;

            ctx.save();
            ctx.translate(viewOffsetX, viewOffsetY);
            ctx.scale(viewScale, viewScale);
            // Compute the visible world rect ONCE per frame (avoids
            // 600+ redundant worldViewRect() calls on large maps).
            const vr = worldViewRect();

            // Reserve space for node labels BEFORE drawing links so link
            // traffic labels can route/fan around device names instead of
            // stacking on top of them (a common source of unreadable clutter
            // on hub nodes with many attached links).
            if (Array.isArray(mapData.nodes)) {
                for (const node of mapData.nodes) {
                    if (!nodeInView(node, vr)) continue;
                    const x = Number(node.position?.x ?? node.x) || 0, y = Number(node.position?.y ?? node.y) || 0;
                    reserveRect({ x: x - 42, y: y - 27, w: 84, h: 54 });
                }
                for (const node of mapData.nodes) {
                    if (nodeInView(node, vr)) reserveNodeLabelRect(node);
                }
            }

            // Draw static link parts (line stroke, color, width, labels, badges)
            if (Array.isArray(mapData.links)) {
                for (const link of mapData.links) {
                    if (isVisibleLink(link) && linkInView(link, vr)) {
                        ctx.globalAlpha = linkMatchesSearch(link) ? 1 : .12;
                        drawLink(link);
                    }
                }
            }

            // Draw nodes
            if (Array.isArray(mapData.nodes)) {
                for (const node of mapData.nodes) {
                    if (nodeInView(node, vr)) {
                        ctx.globalAlpha = nodeMatchesSearch(node) ? 1 : .16;
                        drawNode(node);
                    }
                }
            }
            ctx.globalAlpha = 1;

            ctx.restore();

            // Static layer is now current; only the overlay needs per-frame work.
            staticDirty = false;

            // Draw dynamic overlay (particles / dash animation) immediately so a
            // single renderMap() call (e.g. from pan/zoom) shows a complete frame.
            renderOverlay();

            // Update status and overlays
            updateStatus();
            updateNocSummary();
            if (!skipMinimap) drawMinimap();
        }

        // Draw only the dynamic layer (particles / animated dashes) onto the
        // overlay canvas. Runs every RAF tick; the main canvas is untouched.
        function renderOverlay() {
            if (!mapData || !mapData.links) return;
            overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
            overlayCtx.save();
            overlayCtx.translate(viewOffsetX, viewOffsetY);
            overlayCtx.scale(viewScale, viewScale);
            const vr = worldViewRect();
            if (Array.isArray(mapData.links)) {
                for (const link of mapData.links) {
                    if (isVisibleLink(link) && linkInView(link, vr) && linkMatchesSearch(link)) drawLinkDynamic(link, overlayCtx);
                }
            }
            if (!reducedMotion && Array.isArray(mapData.nodes)) {
                for (const node of mapData.nodes) {
                    if (node.status !== 'down' || !nodeInView(node, vr) || !nodeMatchesSearch(node)) continue;
                    const x = (node.position?.x ?? node.x) || 0;
                    const y = (node.position?.y ?? node.y) || 0;
                    overlayCtx.beginPath();
                    overlayCtx.roundRect(x - 43, y - 28, 86, 56, 11);
                    overlayCtx.strokeStyle = `rgba(239, 68, 68, ${.22 + .2 * (1 + Math.sin(animTick * .07))})`;
                    overlayCtx.lineWidth = 2;
                    overlayCtx.shadowColor = '#ef4444';
                    overlayCtx.shadowBlur = 16;
                    overlayCtx.stroke();
                    overlayCtx.shadowBlur = 0;
                }
            }
            overlayCtx.restore();
        }

        function initKioskMode() {
            if (!LLT_CONFIG.kioskEnabled) return;

            const body = document.body;
            body.classList.add('kiosk-mode');
            body.classList.add('show-chrome');

            let activityTimer = null;
            const hideChrome = () => body.classList.remove('show-chrome');
            const showChrome = () => {
                body.classList.add('show-chrome');
                window.clearTimeout(activityTimer);
                activityTimer = window.setTimeout(hideChrome, 3500);
            };

            document.addEventListener('mousemove', showChrome);
            document.addEventListener('click', showChrome);
            document.addEventListener('touchstart', showChrome);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    window.clearTimeout(activityTimer);
                    body.classList.toggle('show-chrome');
                    return;
                }
                showChrome();
            });

            const exitBtn = document.getElementById('kiosk-exit');
            if (exitBtn) {
                exitBtn.addEventListener('click', () => {
                    window.location.href = window.location.pathname;
                });
            }

            // Cycle to next map on a timer if requested.
            const cycleSeconds = LLT_CONFIG.cycleSeconds;
            const mapList = LLT_CONFIG.mapList || [];
            if (cycleSeconds && mapList.length > 1) {
                const currentId = String(mapId);
                const idx = mapList.findIndex(m => String(m.id) === currentId);
                const next = mapList[(idx + 1) % mapList.length];
                if (next) {
                    const nextUrl = new URL(window.location.pathname.replace(/\d+$/, String(next.id)) + window.location.search, window.location.origin);
                    nextUrl.searchParams.set('kiosk', '1');
                    nextUrl.searchParams.set('cycle', String(cycleSeconds));
                    nextUrl.searchParams.set('target', LLT_CONFIG.linkTarget === '_self' ? '_self' : '_blank');
                    window.setTimeout(() => { window.location.assign(nextUrl.toString()); }, cycleSeconds * 1000);
                }
            }
        }

        function initNavControls() {
            const controls = document.getElementById('controls');
            if (controls) {
                const group = document.getElementById('zoom-controls');
                group.style.display = 'inline-flex';
                group.style.gap = '4px';
                function createZoomButton(id, label, icon) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.id = id;
                    button.className = 'btn btn-light btn-sm';
                    button.title = label;
                    button.setAttribute('aria-label', label);
                    button.dataset.tooltip = label;
                    button.innerHTML = `<svg aria-hidden="true"><use href="#llt-icon-${icon}"></use></svg>`;
                    return button;
                }
                group.appendChild(createZoomButton('zoom-in', 'Zoom in', 'zoom-in'));
                group.appendChild(createZoomButton('zoom-out', 'Zoom out', 'zoom-out'));
                group.appendChild(createZoomButton('zoom-reset', 'Focus topology', 'reset'));
                const c = canvas;
                document.getElementById('zoom-in').addEventListener('click', () => zoomAt(c.width/2, c.height/2, 1.2));
                document.getElementById('zoom-out').addEventListener('click', () => zoomAt(c.width/2, c.height/2, 1/1.2));
                document.getElementById('zoom-reset').addEventListener('click', () => resetView('readable'));
            }
            // Wheel zoom
            canvas.addEventListener('wheel', (e) => {
                e.preventDefault();
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const factor = e.deltaY > 0 ? 0.9 : 1.1;
                zoomAt(x, y, factor);
            }, { passive: false });
            // Drag to pan
            canvas.addEventListener('mousedown', (e) => {
                isPanning = true; panLastX = e.clientX; panLastY = e.clientY; canvas.style.cursor = 'grabbing';
            });
            window.addEventListener('mousemove', (e) => {
                if (!isPanning) return;
                const dx = e.clientX - panLastX; const dy = e.clientY - panLastY;
                panLastX = e.clientX; panLastY = e.clientY;
                userOffsetX += dx; userOffsetY += dy;
                staticDirty = true;
                renderMap();
            });
            window.addEventListener('mouseup', () => { if (isPanning) { isPanning = false; canvas.style.cursor = 'default'; } });
            // Double-click zoom (Shift to zoom out)
            canvas.addEventListener('dblclick', (e) => {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const factor = e.shiftKey ? 0.9 : 1.1;
                zoomAt(x, y, factor);
            });
        }

        function zoomAt(cx, cy, factor) {
            const newUserScale = clamp(userScale * factor, MIN_ZOOM, MAX_ZOOM);
            factor = newUserScale / userScale;
            // world coords before zoom
            const wx = (cx - (baseOffsetX + userOffsetX)) / (baseScale * userScale);
            const wy = (cy - (baseOffsetY + userOffsetY)) / (baseScale * userScale);
            userScale = newUserScale;
            // adjust offsets to keep cursor stable
            const vx = wx * (baseScale * userScale) + baseOffsetX;
            const vy = wy * (baseScale * userScale) + baseOffsetY;
            userOffsetX = cx - vx;
            userOffsetY = cy - vy;
            staticDirty = true;
            renderMap();
        }

        function resetView(mode = 'readable') { fitMode = mode; userScale = 1; userOffsetX = 0; userOffsetY = 0; staticDirty = true; renderMap(); }
        function clamp(v,a,b){ return Math.max(a, Math.min(b, v)); }

        const nodeGeoms = [];
        const linkGeoms = [];
        const linkGeomByLink = new Map();
        const nodeLabelPlans = new Map();

        // Rects (in world coordinates) already claimed by a label this frame.
        // Used to keep link traffic labels from overlapping node labels or
        // each other, instead of relying solely on a fixed fan-out radius.
        const placedLabelRects = [];
        function rectsOverlap(a, b) {
            return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
        }
        function overlapsAny(rect) {
            for (let i = 0; i < placedLabelRects.length; i++) {
                if (rectsOverlap(rect, placedLabelRects[i])) return true;
            }
            return false;
        }
        function reserveRect(rect) {
            placedLabelRects.push(rect);
            return rect;
        }
        function itemInspected(item) {
            return Boolean(graphHoverTarget?.data === item || (searchQuery && nodeById.get(item.id) === item && nodeMatchesSearch(item)));
        }
        function showNodeDetails(node) { return viewScale >= 1.25 || itemInspected(node); }
        function hasCriticalAlert(item) {
            return Number(item.alerts?.count) > 0 && ['critical', 'severe'].includes(item.alerts.severity);
        }
        function showAlertBadge(item) { return hasCriticalAlert(item) || (viewScale >= .8 && Number(item.alerts?.count) > 0); }

        function reserveNodeLabelRect(node) {
            const x = Number(node.position?.x ?? node.x) || 0;
            const y = Number(node.position?.y ?? node.y) || 0;
            const plans = [];
            nodeLabelPlans.set(node, plans);
            const add = (text, cy, font, color) => {
                if (text === null || text === undefined || text === '') return;
                text = String(text); ctx.font = font;
                // Keep very long hostnames from spanning multiple device columns.
                while (text.length > 4 && ctx.measureText(text).width > 170) text = text.slice(0, -2) + '?';
                const width = ctx.measureText(text).width, size = parseInt(font, 10) || 10;
                const rect = { x: x - width / 2 - 5, y: cy - size - 2, w: width + 10, h: size + 6 };
                if (overlapsAny(rect)) return;
                reserveRect(rect);
                plans.push({ text, x, y: cy, font, color });
            };
            if (!dashboardMode && (viewScale >= .55 || itemInspected(node))) add(nodeDisplayName(node), y - 33, '11px "JetBrains Mono", monospace', '#eaf5ff');
            if (!showNodeDetails(node)) return;
            const traffic = liveNodeTraffic(node);
            if (traffic.sum_bps !== null && traffic.sum_bps !== undefined) add('? ' + humanBits(traffic.sum_bps), y + 40, '10px "JetBrains Mono", monospace', '#9eeaf1');
            if (nodeMetricsEnabled && node.metrics) {
                const values = [];
                if (node.metrics.cpu != null) values.push('CPU ' + Math.max(0, Math.min(100, Math.round(node.metrics.cpu))) + '%');
                if (node.metrics.mem != null) values.push('MEM ' + Math.max(0, Math.min(100, Math.round(node.metrics.mem))) + '%');
                if (values.length) add(values.join('  '), y + 56, '9px "JetBrains Mono", monospace', '#a9bfd3');
            }
        }
        function nodeDisplayName(node) {
            if (!node) return 'Device';
            if (!dashboardMode) return node.label || node.device_name || `Device ${node.id}`;
            return 'Device';
        }

        function getNodeType(node) {
            const label = (node.label || '').toLowerCase();
            const explicit = node.meta?.device_type;
            if (explicit && explicit !== 'auto') return explicit;
            if (label.includes('palo') || label.includes('pan-') || label.includes('panos')) return 'paloalto';
            if (label.includes('netscaler') || label.includes('adc') || label.includes('citrix')) return 'netscaler';
            if (label.includes('forti') || label.includes('fortigate')) return 'fortinet';
            if (label.includes('aruba') || label.includes('hpe')) return 'aruba';
            if (label.includes('brocade') || label.includes('extreme')) return 'brocade';
            if (label.includes('router') || label.includes('core')) return 'router';
            if (label.includes('switch')) return 'switch';
            if (label.includes('server') || label.includes('db') || label.includes('app') || label.includes('web') || label.includes('file')) return 'server';
            if (label.includes('firewall') || label.includes('fw')) return 'firewall';
            return 'default';
        }

        function liveNodeHalfSize(node) {
            return { width: 38, height: 23 };
        }

        function nodeCardTrim(x1, y1, x2, y2) {
            const length = Math.hypot(x2 - x1, y2 - y1) || 1;
            const horizontal = Math.abs(x2 - x1) / length;
            const vertical = Math.abs(y2 - y1) / length;
            return Math.min(horizontal ? 38 / horizontal : Infinity, vertical ? 23 / vertical : Infinity) + 4;
        }

        function drawLivePortAnchors(node) {
            if (!livePortAnchors || !showNodeDetails(node)) return;
            for (const anchor of livePortAnchors.forNode(node)) {
                ctx.beginPath(); ctx.arc(anchor.x, anchor.y, 2.7, 0, Math.PI * 2);
                ctx.fillStyle = '#59d1b2'; ctx.fill();
                ctx.strokeStyle = '#f8fbfd'; ctx.lineWidth = 1.1; ctx.stroke();
                if ((viewScale >= 1.55 || itemInspected(node)) && anchor.label) {
                    const horizontal = anchor.side === 'left' || anchor.side === 'right';
                    ctx.font = '8px Arial';
                    const width = ctx.measureText(anchor.label).width;
                    const x = anchor.x + (anchor.side === 'left' ? -7 - width / 2 : anchor.side === 'right' ? 7 + width / 2 : 0);
                    const y = anchor.y + (horizontal ? 3 : anchor.side === 'top' ? -7 : 14);
                    const rect = { x: x - width / 2 - 2, y: y - 9, w: width + 4, h: 12 };
                    if (overlapsAny(rect)) continue;
                    reserveRect(rect);
                    drawPillLabel(anchor.label, x, y, { font: '8px Arial', textColor: '#b9dfed', bgColor: 'rgba(13, 24, 39, .95)' });
                }
            }
        }

        function drawNode(node) {
            const x = (node.position?.x ?? node.x) || 0;
            const y = (node.position?.y ?? node.y) || 0;
            const nodeType = getNodeType(node);
            const color = getNodeColor(node);
            const radius = 23; // Card half-height for status and badges
            const status = node.status || 'unknown';

            // A compact glass device badge retains the existing port-anchor footprint.
            const mark = { paloalto: 'PA', netscaler: 'LB', fortinet: 'FW', aruba: 'SW', brocade: 'SW', router: 'RT', switch: 'SW', server: 'SV', firewall: 'FW', default: 'DV' }[nodeType] || 'DV';
            const deviceFill = ctx.createLinearGradient(x - 38, y - 23, x + 38, y + 23);
            deviceFill.addColorStop(0, '#1d344c');
            deviceFill.addColorStop(1, '#0d1b2d');
            ctx.shadowColor = color;
            ctx.shadowBlur = status === 'down' ? 12 : 4;
            ctx.fillStyle = deviceFill;
            ctx.strokeStyle = color;
            ctx.lineWidth = 1.4;
            ctx.beginPath();
            ctx.roundRect(x - 38, y - 23, 76, 46, 9);
            ctx.fill();
            ctx.stroke();
            ctx.shadowBlur = 0;
            ctx.fillStyle = '#e8f4ff';
            ctx.font = 'bold 11px "JetBrains Mono", monospace';
            ctx.textAlign = 'left';
            ctx.fillText(mark, x - 30, y + 4);
            ctx.strokeStyle = 'rgba(96, 129, 155, .42)';
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(x - 5, y - 15); ctx.lineTo(x - 5, y + 15); ctx.stroke();
            const miniBar = (label, value, offset) => {
                ctx.font = '7px "JetBrains Mono", monospace';
                ctx.fillStyle = '#8aa2b8';
                ctx.fillText(label, x + 2, y + offset - 3);
                ctx.fillStyle = '#263c52';
                ctx.beginPath(); ctx.roundRect(x + 2, y + offset, 28, 3, 2); ctx.fill();
                if (value !== null && value !== undefined && Number.isFinite(Number(value))) {
                    ctx.fillStyle = Number(value) >= 90 ? '#ef4444' : Number(value) >= 75 ? '#f59e0b' : '#10b981';
                    ctx.beginPath(); ctx.roundRect(x + 2, y + offset, 28 * Math.max(0, Math.min(100, Number(value))) / 100, 3, 2); ctx.fill();
                }
            };
            if (viewScale >= .8 || itemInspected(node)) {
                miniBar('CPU', node.metrics?.cpu, -7);
                miniBar('RAM', node.metrics?.mem, 10);
            }
            ctx.beginPath();
            ctx.arc(x + 35, y - 19, 3.5, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.shadowColor = color;
            ctx.shadowBlur = 8;
            ctx.fill();
            ctx.shadowBlur = 0;
            // Static status-based rings (drawn on main canvas).
            // Warning nodes (up but CPU/MEM high): yellow dashed ring.
            if (isWarningNode(node)) {
                ctx.beginPath();
                ctx.roundRect(x - 43, y - 28, 86, 56, 10);
                ctx.strokeStyle = 'rgba(245, 158, 11, 0.75)';
                ctx.lineWidth = 2;
                ctx.setLineDash([4, 3]);
                ctx.stroke();
                ctx.setLineDash([]);
            }
            // Unknown nodes: subtle gray dashed outline.
            else if (status === 'unknown') {
                ctx.beginPath();
                ctx.roundRect(x - 42, y - 27, 84, 54, 10);
                ctx.strokeStyle = 'rgba(142, 160, 184, 0.5)';
                ctx.lineWidth = 1.5;
                ctx.setLineDash([2, 2]);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            for (const label of nodeLabelPlans.get(node) || []) {
                drawPillLabel(label.text, label.x, label.y, { font: label.font, textColor: label.color, bgColor: 'rgba(13, 24, 39, .92)' });
            }

            // Alert badge (if any)
            if (showAlertBadge(node)) {
                const bx = x + 35;
                const by = y - 20;
                ctx.beginPath();
                ctx.arc(bx, by, Math.max(5, 3 / Math.max(.1, viewScale)), 0, Math.PI * 2);
                const sev = (node.alerts.severity || 'warning');
                ctx.fillStyle = (sev === 'severe' || sev === 'critical') ? '#dc3545' : '#ffc107';
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#fff';
                ctx.stroke();
            }

            drawLivePortAnchors(node);

            // store geometry for hover
            nodeGeoms.push({ x, y, r: radius, w: 38, h: 23, alertX: x + 35, alertY: y - 20, node });
        }

        function buildLinkPath(link, x1, y1, x2, y2, startTrim = 13, endTrim = startTrim) {
            let viaPoints = (link.style && link.style.via_points) || [];
            const viaStyle = (link.style && link.style.via_style) || defaultLinkStyle.via_style || LLT_CONFIG.link_style || 'straight';
            const siblings = bundleSiblings(link);
            if (viaStyle !== 'angled' && siblings.length > 1 && expandedBundles.has(linkEndpointKey(link))) {
                const offset = (siblings.indexOf(link) - (siblings.length - 1) / 2) * 34;
                const length = Math.hypot(x2 - x1, y2 - y1) || 1;
                const nx = -(y2 - y1) / length, ny = (x2 - x1) / length;
                const control = viaPoints.length ? viaPoints : [{ x: (x1 + x2) / 2, y: (y1 + y2) / 2 }];
                viaPoints = control.map(point => ({ x: point.x + nx * offset, y: point.y + ny * offset }));
            }
            let rawPoints = [{x: x1, y: y1}];
            for (const vp of viaPoints) { rawPoints.push({x: vp.x, y: vp.y}); }
            rawPoints.push({x: x2, y: y2});
            const sourceAnchor = livePortAnchors?.sourceFor(link);
            const targetAnchor = livePortAnchors?.targetFor(link);
            if (sourceAnchor && targetAnchor && window.LLTPortAnchors?.routePoints) {
                rawPoints = window.LLTPortAnchors.routePoints(link, sourceAnchor, targetAnchor, viaPoints);
            }

            // Straight links use Visio-style orthogonal routing. Explicit VIA
            // points and curved links retain the path authored by the user.
            if (viaStyle === 'straight' && viaPoints.length === 0) {
                rawPoints = orthogonalLinkPoints(rawPoints[0], rawPoints[1]);
            }

            // Flatten the curve into a dense polyline up front so the stroke,
            // flow particles, label anchor, and hover hit-testing all walk the
            // exact same path — otherwise particles/labels visibly drift off
            // the drawn bezier curve.
            if (viaStyle === 'curved' && rawPoints.length > 2) {
                return { points: trimLinkEndpoints(sampleCurvePoints(rawPoints), startTrim, endTrim), viaStyle: 'straight' };
            }
            return { points: trimLinkEndpoints(rawPoints, startTrim, endTrim), viaStyle };
        }

        function orthogonalLinkPoints(start, end) {
            if (Math.abs(end.x - start.x) >= Math.abs(end.y - start.y)) {
                const midX = (start.x + end.x) / 2;
                return [start, { x: midX, y: start.y }, { x: midX, y: end.y }, end];
            }
            const midY = (start.y + end.y) / 2;
            return [start, { x: start.x, y: midY }, { x: end.x, y: midY }, end];
        }

        function trimLinkEndpoints(points, startRadius = 13, endRadius = startRadius) {
            if (!Array.isArray(points) || points.length < 2) return points;
            const trimmed = points.map(point => ({ x: point.x, y: point.y }));
            const moveToward = (from, toward, radius) => {
                const dx = toward.x - from.x;
                const dy = toward.y - from.y;
                const length = Math.hypot(dx, dy) || 1;
                return { x: from.x + (dx / length) * radius, y: from.y + (dy / length) * radius };
            };
            trimmed[0] = moveToward(trimmed[0], trimmed[1], startRadius);
            const last = trimmed.length - 1;
            trimmed[last] = moveToward(trimmed[last], trimmed[last - 1], endRadius);
            return trimmed;
        }

        // Sample a Catmull-Rom-derived cubic bezier (matching traceLinkPath's
        // curve construction) into a dense polyline of world-space points.
        function sampleCurvePoints(points, segmentsPerPiece = 12) {
            const out = [points[0]];
            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[Math.max(0, i - 1)];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[Math.min(points.length - 1, i + 2)];
                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = p1.y + (p2.y - p0.y) / 6;
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = p2.y - (p3.y - p1.y) / 6;
                for (let s = 1; s <= segmentsPerPiece; s++) {
                    const t = s / segmentsPerPiece;
                    const mt = 1 - t;
                    out.push({
                        x: mt*mt*mt*p1.x + 3*mt*mt*t*cp1x + 3*mt*t*t*cp2x + t*t*t*p2.x,
                        y: mt*mt*mt*p1.y + 3*mt*mt*t*cp1y + 3*mt*t*t*cp2y + t*t*t*p2.y,
                    });
                }
            }
            return out;
        }

        function traceLinkPath(ctx, points, viaStyle) {
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            if (points.length === 2) {
                ctx.lineTo(points[1].x, points[1].y);
                return;
            }
            if (viaStyle === 'curved') {
                for (let i = 0; i < points.length - 1; i++) {
                    const p0 = points[Math.max(0, i - 1)];
                    const p1 = points[i];
                    const p2 = points[i + 1];
                    const p3 = points[Math.min(points.length - 1, i + 2)];
                    const cp1x = p1.x + (p2.x - p0.x) / 6;
                    const cp1y = p1.y + (p2.y - p0.y) / 6;
                    const cp2x = p2.x - (p3.x - p1.x) / 6;
                    const cp2y = p2.y - (p3.y - p1.y) / 6;
                    ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
                }
            } else {
                for (let i = 1; i < points.length; i++) {
                    ctx.lineTo(points[i].x, points[i].y);
                }
            }
        }

        function getPathPoint(points, fraction = 0.5) {
            fraction = Math.max(0, Math.min(1, fraction));
            let totalLen = 0;
            const segs = [];
            for (let i = 1; i < points.length; i++) {
                const dx = points[i].x - points[i-1].x;
                const dy = points[i].y - points[i-1].y;
                const len = Math.sqrt(dx*dx + dy*dy);
                segs.push(len);
                totalLen += len;
            }
            const targetLength = totalLen * fraction;
            let accum = 0;
            for (let i = 0; i < segs.length; i++) {
                if (accum + segs[i] >= targetLength) {
                    const t = (targetLength - accum) / Math.max(segs[i], 0.001);
                    return {
                        x: points[i].x + (points[i+1].x - points[i].x) * t,
                        y: points[i].y + (points[i+1].y - points[i].y) * t
                    };
                }
                accum += segs[i];
            }
            return points[Math.floor(points.length / 2)];
        }

        function getPathMidpoint(points) {
            return getPathPoint(points, 0.5);
        }

        const parallelLinkGroups = new Map();
        const expandedBundles = new Set();
        function linkEndpointKey(link) {
            const source = String(link.source ?? link.src ?? link.source_id ?? '');
            const target = String(link.target ?? link.dst ?? link.destination_id ?? '');
            return [source, target].sort().join('|');
        }
        function rebuildParallelLinkGroups() {
            parallelLinkGroups.clear();
            for (const link of (mapData.links || [])) {
                const key = linkEndpointKey(link);
                if (!parallelLinkGroups.has(key)) parallelLinkGroups.set(key, []);
                parallelLinkGroups.get(key).push(link);
            }
        }
        function bundleSiblings(link) {
            return parallelLinkGroups.get(linkEndpointKey(link)) || [link];
        }
        function isVisibleLink(link) {
            const siblings = bundleSiblings(link);
            return siblings.length < 2 || expandedBundles.has(linkEndpointKey(link)) || siblings[0] === link;
        }
        function displayLinkLive(link) {
            const siblings = bundleSiblings(link);
            if (siblings.length < 2 || expandedBundles.has(linkEndpointKey(link))) return link.live || {};
            const sourceId = String(link.source ?? link.src ?? link.source_id);
            return siblings.reduce((sum, item) => {
                const reversed = String(item.source ?? item.src ?? item.source_id) !== sourceId;
                sum.in_bps += Number(item.live?.[reversed ? 'out_bps' : 'in_bps']) || 0;
                sum.out_bps += Number(item.live?.[reversed ? 'in_bps' : 'out_bps']) || 0;
                return sum;
            }, { in_bps: 0, out_bps: 0 });
        }
        function displayLinkPct(link) {
            const siblings = bundleSiblings(link);
            if (siblings.length < 2 || expandedBundles.has(linkEndpointKey(link))) return getLinkPct(link, getLinkMetric(link));
            const values = siblings.map(item => getLinkPct(item, getLinkMetric(item))).filter(value => value !== null);
            return values.length ? Math.max(...values) : null;
        }
        function parallelLabelFraction(link) {
            const siblings = parallelLinkGroups.get(linkEndpointKey(link)) || [link];
            if (siblings.length < 2) return 0.5;
            const index = Math.max(0, siblings.indexOf(link));
            return 0.25 + (index / Math.max(1, siblings.length - 1)) * 0.5;
        }

        // Anchor point for a link's traffic label: the path midpoint, fanned
        // out around the link's source node by a per-node slot counter. This
        // keeps labels for multiple links converging on the same node (a
        // hub/star topology) from stacking on each other.
        const linkLabelSlots = new Map();
        function getLinkLabelAnchor(link, points) {
            const mid = getPathPoint(points, parallelLabelFraction(link));
            if (points.length < 2) return mid;
            const a = points[0];
            const b = points[points.length - 1];
            const dx = b.x - a.x;
            const dy = b.y - a.y;
            const len = Math.hypot(dx, dy) || 1;
            const nx = -dy / len;
            const ny = dx / len;

            const nodeKey = link.source ?? link.src ?? link.source_id ?? 'link-' + (link.id ?? '');
            const slot = linkLabelSlots.get(nodeKey) || 0;
            linkLabelSlots.set(nodeKey, slot + 1);

            const side = (slot % 2 === 0) ? 1 : -1;
            const ring = Math.floor(slot / 2);
            const angle = ring * 0.6; // radians, spreads consecutive labels apart
            const distance = 18 + ring * 13;
            const cos = Math.cos(angle);
            const sin = Math.sin(angle);
            const rx = nx * cos - ny * sin;
            const ry = nx * sin + ny * cos;
            return { x: mid.x + rx * distance * side, y: mid.y + ry * distance * side, nx: rx, ny: ry, side, ring };
        }

        // Starting from a candidate anchor, nudge a label box outward along
        // its fan-out direction until it no longer overlaps an already
        // placed label (node name or another link's traffic box). Gives up
        // after a bounded number of attempts to avoid runaway drift.
        function resolveLabelPlacement(anchor, w, h, lineHeight) {
            const dirX = anchor.nx ?? 0;
            const dirY = anchor.ny ?? -1;
            const side = anchor.side ?? 1;
            let x = anchor.x, y = anchor.y;
            const rectAt = (cx, cy) => ({
                x: cx - w / 2 - 4,
                y: cy - lineHeight + 1 - 2,
                w: w + 8,
                h,
            });
            let rect = rectAt(x, y);
            let step = 0;
            while (overlapsAny(rect) && step < 8) {
                step++;
                const extra = 12 * step;
                x = anchor.x + dirX * extra * side;
                y = anchor.y + dirY * extra * side;
                rect = rectAt(x, y);
            }
            if (overlapsAny(rect)) return null;
            reserveRect(rect);
            return { x, y };
        }

        function shouldDrawLinkLabel(link, pct) {
            return viewScale >= 1.1 || itemInspected(link);
        }

        function drawLink(link) {
            const srcId = link.source ?? link.src ?? link.source_id;
            const dstId = link.target ?? link.dst ?? link.destination_id;
            const sourceNode = nodeById.get(srcId);
            const targetNode = nodeById.get(dstId);

            if (!sourceNode || !targetNode) return;

            const sourceAnchor = livePortAnchors?.sourceFor(link) || null;
            const targetAnchor = livePortAnchors?.targetFor(link) || null;
            const x1 = sourceAnchor?.x ?? ((sourceNode.position?.x ?? sourceNode.x) || 0);
            const y1 = sourceAnchor?.y ?? ((sourceNode.position?.y ?? sourceNode.y) || 0);
            const x2 = targetAnchor?.x ?? ((targetNode.position?.x ?? targetNode.x) || 0);
            const y2 = targetAnchor?.y ?? ((targetNode.position?.y ?? targetNode.y) || 0);

            const { points, viaStyle } = buildLinkPath(link, x1, y1, x2, y2,
                sourceAnchor ? 0 : nodeCardTrim(x1, y1, x2, y2),
                targetAnchor ? 0 : nodeCardTrim(x2, y2, x1, y1));
            const metric = getLinkMetric(link);
            const pct = displayLinkPct(link);
            const linkStyle = link.style || {};
            const width = Math.max(0.5, linkStyle.width || link.width || defaultLinkStyle.width || 2);

            // Keep a quiet static route under the independently animated traffic.
            {
                ctx.save();
                traceLinkPath(ctx, points, viaStyle);
                const color = getLinkColor(pct);
                ctx.strokeStyle = color;
                ctx.shadowColor = color;
                const emphasized = itemInspected(link) || (pct !== null && pct >= 90);
                ctx.shadowBlur = emphasized ? 5 : 0;
                ctx.globalAlpha *= emphasized ? 1 : .85;
                ctx.lineWidth = Math.max(1.2, Math.min(2.5, width));
                ctx.stroke();
                ctx.restore();
            }
            // Always show both traffic directions on active links so the map
            // makes the device-to-device flow visible without opening a tooltip.
            const live = displayLinkLive(link);
            const linkIn = Number(live.in_bps) || 0;
            const linkOut = Number(live.out_bps) || 0;
            const portA = link.source_port_name || (link.port_id_a ? 'Port ' + link.port_id_a : 'Port A');
            const portB = link.destination_port_name || (link.port_id_b ? 'Port ' + link.port_id_b : 'Port B');
            if (shouldDrawLinkLabel(link, pct) && (linkIn > 0 || linkOut > 0 || link.port_id_a || link.port_id_b || (metric !== null && metric !== undefined && currentMetric === 'percent'))) {
                const anchor = getLinkLabelAnchor(link, points);
                // Zoomed out: a single short token (percent, or bandwidth if no
                // capacity is configured) so hub nodes with many links stay
                // readable. Zoomed in close, expand to full port + direction
                // detail — full detail was the main source of overlap.
                const showDetail = viewScale >= 1.55 || itemInspected(link);
                let labels;
                if (showDetail) {
                    ctx.font = '9px "JetBrains Mono", monospace';
                    const portLabel = portA + ' -> ' + portB;
                    let trafficLine = '\u2193 ' + humanBits(linkIn) + '  \u2191 ' + humanBits(linkOut);
                    if (currentMetric === 'percent' && LLT_CONFIG.show_percentages !== false && pct !== null) {
                        trafficLine += '  ' + formatPct(pct);
                    }
                    labels = [portLabel, trafficLine];
                } else {
                    ctx.font = '10px "JetBrains Mono", monospace';
                    let compact;
                    if (currentMetric === 'percent' && LLT_CONFIG.show_percentages !== false && pct !== null) {
                        compact = (viewScale >= .75 ? humanBits(Math.max(linkIn, linkOut)) + ' / ' : '') + formatPct(pct);
                    } else {
                        compact = humanBits(Math.max(linkIn, linkOut));
                    }
                    labels = [compact];
                }
                ctx.textAlign = 'center';
                const lineHeight = 11;
                const paddingX = 4;
                const paddingY = 2;
                const widest = Math.max(...labels.map(label => ctx.measureText(label).width));
                const boxHeight = labels.length * lineHeight + paddingY * 2;
                const pos = resolveLabelPlacement(anchor, widest, boxHeight, lineHeight);
                if (pos) {
                ctx.fillStyle = 'rgba(12, 23, 38, .94)';
                ctx.beginPath();
                ctx.roundRect(pos.x - widest / 2 - paddingX, pos.y - lineHeight + 1 - paddingY, widest + paddingX * 2, boxHeight, 4);
                ctx.fill();
                ctx.strokeStyle = getLinkColor(pct);
                ctx.lineWidth = 1;
                ctx.stroke();
                ctx.fillStyle = '#e7f6ff';
                labels.forEach((label, index) => {
                    const y = pos.y + index * lineHeight;
                    ctx.fillText(label, pos.x, y);
                });
                }
            }
            const siblings = bundleSiblings(link);
            if (siblings.length > 1 && !expandedBundles.has(linkEndpointKey(link)) && (viewScale >= .8 || itemInspected(link))) {
                const mid = getPathMidpoint(points);
                const text = `${siblings.length} links`;
                ctx.font = 'bold 11px "JetBrains Mono", monospace';
                const pos = resolveLabelPlacement({ x: mid.x, y: mid.y - 7, ny: -1 }, ctx.measureText(text).width, 19, 11);
                if (pos) drawPillLabel(text, pos.x, pos.y, {
                    font: 'bold 11px "JetBrains Mono", monospace',
                    bgColor: 'rgba(15, 23, 42, .95)', textColor: '#a5f3fc', paddingX: 4, paddingY: 4,
                });
            }
            // Link alert badge (diamond)
            if (showAlertBadge(link)) {
                const mid = getPathMidpoint(points);
                const size = Math.max(5, 3 / Math.max(.1, viewScale));
                ctx.save();
                ctx.translate(mid.x + 10, mid.y - 10);
                ctx.rotate(Math.PI / 4);
                const sev = (link.alerts.severity || 'warning');
                ctx.fillStyle = (sev === 'severe' || sev === 'critical') ? '#dc3545' : '#ffc107';
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.rect(-size, -size, size * 2, size * 2);
                ctx.fill();
                ctx.stroke();
                ctx.restore();
            }
            // store geometry for hover
            storeLinkGeom(link, x1, y1, x2, y2, pct, points, live, siblings);
        }

        // Reuse the exact sampled path rendered on the static canvas for moving traffic.
        function drawLinkDynamic(link, octx) {
            if (!flowAnimationEnabled) return;
            const geometry = linkGeomByLink.get(link);
            if (!geometry) return;
            drawFlowParticles({ id: link.id, live: displayLinkLive(link) }, geometry.x1, geometry.y1,
                geometry.x2, geometry.y2, geometry.pct, geometry.points, octx);
        }

        function drawFlowParticles(link, x1, y1, x2, y2, pct, pathPoints, drawCtx) {
            if (!flowAnimationEnabled) return;
            const inBps = Math.max(0, Number(link.live?.in_bps) || 0);
            const outBps = Math.max(0, Number(link.live?.out_bps) || 0);
            if (!(inBps > 0 || outBps > 0)) return;
            const points = pathPoints || [{ x: x1, y: y1 }, { x: x2, y: y2 }];
            const length = points.slice(1).reduce((sum, point, index) => sum + Math.hypot(point.x - points[index].x, point.y - points[index].y), 0);
            const screenScale = Math.max(.05, viewScale);
            const screenLength = length * screenScale;
            if (screenLength < 8) return;
            const speed = (36 + Math.min(100, Math.max(0, Number(pct) || 0)) * .5) * particleSpeed;
            // Bound the total visual activity: many long paths must not turn
            // shared corridors into solid glowing bands. Offset each circuit's
            // phase so particles do not all arrive at the core simultaneously.
            const budget = Math.max(1, Math.floor(100 / Math.max(1, linkGeoms.length)));
            const particleCount = Math.max(1, Math.min(6, budget, Math.ceil(screenLength / 220 * particleDensity)));
            let seed = 0;
            for (const char of String(link.id ?? '')) seed = (seed * 31 + char.charCodeAt(0)) >>> 0;
            const phase = (animTick / 60 * speed / screenLength + (seed % 997) / 997) % 1;
            const drawDirection = (direction, color, phaseOffset) => {
                drawCtx.save();
                drawCtx.fillStyle = color;
                drawCtx.shadowColor = color;
                drawCtx.shadowBlur = 3;
                for (let index = 0; index < particleCount; index++) {
                    let t = (phase + phaseOffset + index / particleCount) % 1;
                    if (direction < 0) t = 1 - t;
                    const point = getPathPoint(points, t);
                    const ahead = getPathPoint(points, Math.min(1, t + .001));
                    const behind = getPathPoint(points, Math.max(0, t - .001));
                    const angle = Math.atan2(ahead.y - behind.y, ahead.x - behind.x) + (direction < 0 ? Math.PI : 0);
                    const dx = Math.cos(angle), dy = Math.sin(angle);
                    const size = 2.2 / screenScale, tail = 4.5 / screenScale;
                    drawCtx.beginPath();
                    drawCtx.moveTo(point.x + dx * size, point.y + dy * size);
                    drawCtx.lineTo(point.x - dx * tail - dy * size, point.y - dy * tail + dx * size);
                    drawCtx.lineTo(point.x - dx * tail + dy * size, point.y - dy * tail - dx * size);
                    drawCtx.closePath();
                    drawCtx.fill();
                }
                drawCtx.restore();
            };
            const trafficColor = getLinkColor(pct);
            if (outBps > 0) drawDirection(1, trafficColor, 0);
            if (inBps > 0) drawDirection(-1, trafficColor, outBps > 0 ? .5 / particleCount : 0);
        }

        const defaultNodeStyle = mapData.options?.default_node_style || {};
        const defaultLinkStyle = mapData.options?.default_link_style || {};

        // Reusable threshold check: returns true if a CPU/MEM value exceeds
        // the warning threshold (second element of LLT_CONFIG.thresholds).
        function warnThreshold(v) {
            return typeof v === 'number' && v >= ((LLT_CONFIG.thresholds && LLT_CONFIG.thresholds[1]) || 80);
        }

        function isWarningNode(node) {
            const status = node.status || 'unknown';
            return status === 'up' && (warnThreshold(node.metrics?.cpu) || warnThreshold(node.metrics?.mem));
        }

        function getNodeColor(node) {
            const status = node.status || 'unknown';
            if (status === 'down') return '#ef4444';
            if (isWarningNode(node)) return '#f59e0b';
            if (status === 'up') return '#10b981';
            return '#8294ac';
        }

        function trafficNumber(value) {
            if (value === null || value === undefined || value === '' || typeof value === 'boolean') return null;
            if (typeof value === 'string' && !value.trim()) return null;
            const number = Number(value);
            return Number.isFinite(number) && number >= 0 ? number : null;
        }

        function getLinkMetric(link) {
            const live = link.live || {};
            const incoming = trafficNumber(live.in_bps), outgoing = trafficNumber(live.out_bps);
            if (currentMetric === 'in') return incoming;
            if (currentMetric === 'out') return outgoing;
            if (incoming === null && outgoing === null) return null;
            return currentMetric === 'sum' ? (incoming || 0) + (outgoing || 0) : Math.max(incoming || 0, outgoing || 0);
        }

        function getLinkPct(link, metricBps) {
            const live = link.live || {};
            const pct = trafficNumber(live.pct);
            if (pct !== null) return Math.min(100, pct);
            const bandwidth = trafficNumber(link.bandwidth_bps) || trafficNumber(link.bandwidth) || trafficNumber(live.bandwidth_bps);
            const incoming = trafficNumber(live.in_bps), outgoing = trafficNumber(live.out_bps);
            if (!bandwidth || (incoming === null && outgoing === null)) return null;
            // Full duplex utilization is independent of the selected label metric.
            return Math.min(100, Math.max(incoming || 0, outgoing || 0) / bandwidth * 100);
        }

        // A nonzero pct that rounds to 0 (e.g. a low-traffic port on a 10G
        // link) looked like a bug ("everything shows 0%") — show "<1%"
        // instead of silently rounding tiny-but-real utilization away.
        function formatPct(pct) {
            if (pct === null || pct === undefined) return null;
            if (pct > 0 && pct < 1) return '<1%';
            return Math.round(pct) + '%';
        }

        function getLinkColor(pct) {
            if (!Number.isFinite(pct)) return '#64748b';
            if (pct >= 90) return '#ef4444';
            if (pct >= 71) return '#f59e0b';
            if (pct >= 31) return '#10b981';
            return '#06b6d4';
        }

        function needsAnimation() {
            return !document.hidden &&
                ((flowAnimationEnabled && hasActiveTraffic) || (!reducedMotion && (mapData.nodes || []).some(node => node.status === 'down')));
        }
        function startAnimationLoop() {
            if (animationId || !needsAnimation()) return;
            let previousTime = null;
            function tick(timestamp) {
                animationId = null;
                if (!needsAnimation()) return;
                if (previousTime !== null) animTick += Math.min(100, Math.max(0, timestamp - previousTime)) / (1000 / 60);
                previousTime = timestamp;
                if (staticDirty) renderMap(true);
                else renderOverlay();
                animationId = requestAnimationFrame(tick);
            }
            animationId = requestAnimationFrame(tick);
        }

        function positionVizMenu() {
            const menu = document.getElementById('viz-menu');
            if (menu.style.display !== 'block') return;
            menu.style.left = '0px';
            menu.style.top = 'calc(100% + 8px)';
            menu.style.maxHeight = Math.max(40, window.innerHeight - 16) + 'px';
            const rect = menu.getBoundingClientRect();
            const dx = Math.max(8 - rect.left, Math.min(0, window.innerWidth - 8 - rect.right));
            const dy = Math.max(8 - rect.top, Math.min(0, window.innerHeight - 8 - rect.bottom));
            menu.style.left = dx + 'px';
            menu.style.top = `calc(100% + ${8 + dy}px)`;
        }
        window.addEventListener('resize', positionVizMenu);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                document.getElementById('viz-menu').style.display = 'none';
                document.getElementById('viz-settings').setAttribute('aria-expanded', 'false');
            }
        });

        // Live updates via SSE (fallback to polling)
        function startLiveUpdates() {
            updateTransportButton();
            if (sseEnabled) {
                startSSE();
            } else {
                startAutoUpdate();
            }
            const btn = document.getElementById('toggle-transport');
            btn.addEventListener('click', () => {
                if (currentTransport === 'sse') {
                    stopSSE();
                    sseEnabled = false;
                    startAutoUpdate();
                } else {
                    stopPolling();
                    sseEnabled = true;
                    startSSE();
                }
            });

            const dashboardSelect = document.getElementById('dashboard-mode');
            dashboardSelect.value = dashboardMode ? '1' : '0';
            dashboardSelect.addEventListener('change', () => {
                dashboardMode = dashboardSelect.value === '1';
                const nextUrl = new URL(window.location.href);
                if (dashboardMode) nextUrl.searchParams.set('dashboard', '1');
                else nextUrl.searchParams.delete('dashboard');
                window.history.replaceState(null, '', nextUrl);
                document.getElementById('graph-popup').style.display = 'none';
                graphHoverTarget = null;
                staticDirty = true;
                renderMap();
                updateNocSummary();
            });

            // Flow animation controls
            document.getElementById('toggle-flow').addEventListener('click', () => {
                flowAnimationEnabled = !flowAnimationEnabled;
                const btn = document.getElementById('toggle-flow');
                if (flowAnimationEnabled) {
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                }
                btn.setAttribute('aria-pressed', String(flowAnimationEnabled));
                // Refresh the overlay immediately and restart RAF if appropriate.
                staticDirty = true;
                renderMap();
                if (!needsAnimation() && animationId) { cancelAnimationFrame(animationId); animationId = null; }
                startAnimationLoop();
            });

            // Visualization settings menu toggle
            document.getElementById('viz-settings').addEventListener('click', (e) => {
                e.stopPropagation();
                const menu = document.getElementById('viz-menu');
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                document.getElementById('viz-settings').setAttribute('aria-expanded', String(menu.style.display === 'block'));
                if (menu.style.display === 'block') positionVizMenu();
            });
            document.getElementById('viz-menu').addEventListener('click', e => e.stopPropagation());

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                document.getElementById('viz-menu').style.display = 'none';
                document.getElementById('viz-settings').setAttribute('aria-expanded', 'false');
            });

            document.getElementById('particle-density').addEventListener('input', (e) => {
                particleDensity = parseFloat(e.target.value);
                document.getElementById('density-value').textContent = particleDensity.toFixed(1);
            });

            document.getElementById('particle-speed').addEventListener('input', (e) => {
                particleSpeed = parseFloat(e.target.value);
                document.getElementById('speed-value').textContent = particleSpeed.toFixed(1);
            });


            // Start animation loop — only needed when something actually animates.
            // When reduced-motion is preferred and flow is disabled, render once
            // and rely on live-update polls / manual interactions to re-render.
            startAnimationLoop();
        }

        function startSSE() {
            try {
                stopPolling();
                if (eventSourceRef) { try { eventSourceRef.close(); } catch {} }
                const url = `${baseUrl}/plugin/LibreLiveTopology/api/maps/${mapId}/sse?interval=${intervalSec}&max=${sseMax}`;
                const es = new EventSource(url);
                eventSourceRef = es;
                currentTransport = 'sse';
                updateTransportButton();
                es.onmessage = (e) => {
                    try {
                        sseReconnectAttempts = 0; // Reset on successful message
                        const live = JSON.parse(e.data);
                        applyLiveUpdate(live);
                    } catch {}
                };
                es.onerror = () => {
                    es.close();
                    eventSourceRef = null;
                    // Try to reconnect if SSE was enabled
                    if (!document.hidden && sseEnabled && sseReconnectAttempts < maxReconnectAttempts) {
                        sseReconnectAttempts += 1;
                        sseReconnectTimer = setTimeout(() => {
                            sseReconnectTimer = null;
                            if (sseEnabled && !document.hidden) startSSE();
                        }, reconnectDelay);
                    } else if (!document.hidden) {
                        // Fall back to polling after max attempts
                        currentTransport = 'poll';
                        sseEnabled = false;
                        sseReconnectAttempts = 0;
                        startAutoUpdate();
                    }
                };
            } catch (e) {
                currentTransport = 'poll';
                sseEnabled = false;
                startAutoUpdate();
            }
        }
        function stopSSE() {
            if (sseReconnectTimer) { clearTimeout(sseReconnectTimer); sseReconnectTimer = null; }
            if (eventSourceRef) {
                try { eventSourceRef.close(); } catch {}
                eventSourceRef = null;
            }
        }

        function applyLiveUpdate(live) {
            lastDataUpdate = Date.now();
            // Attach link live data by id
            if (live && live.links && Array.isArray(mapData.links)) {
                mapData.links.forEach(l => {
                    const id = l.id ?? l.link_id ?? null;
                    if (id && live.links[id]) {
                        l.live = live.links[id];
                    }
                });
            }
            // Update node status by id
            if (live && live.nodes && Array.isArray(mapData.nodes)) {
                mapData.nodes.forEach(n => {
                    const id = n.id ?? n.node_id ?? null;
                    if (id && live.nodes[id]) {
                        n.status = live.nodes[id].status || n.status;
                        if (live.nodes[id].metrics) n.metrics = live.nodes[id].metrics;
                        // Attach aggregated traffic and expose a simple value for label
                        if (live.nodes[id].traffic) {
                            n.traffic = live.nodes[id].traffic;
                            const sum = Number(live.nodes[id].traffic.sum_bps || 0);
                            n.current_value = isFinite(sum) ? sum : null;
                        }
                    }
                });
            }
            // Alert overlays
            if (live && live.alerts) {
                if (live.alerts.nodes && Array.isArray(mapData.nodes)) {
                    mapData.nodes.forEach(n => {
                        const id = n.id ?? n.node_id ?? null;
                        n.alerts = (id && live.alerts.nodes[id]) ? live.alerts.nodes[id] : { count: 0, severity: 'ok' };
                    });
                }
                if (live.alerts.links && Array.isArray(mapData.links)) {
                    mapData.links.forEach(l => {
                        const id = l.id ?? l.link_id ?? null;
                        l.alerts = (id && live.alerts.links[id]) ? live.alerts.links[id] : { count: 0, severity: 'ok' };
                    });
                }
            }
            if (graphHoverTarget && graphPopupPosition && graphPopup.style.display === 'block') {
                showGraphPopup(graphHoverTarget, graphPopupPosition.x, graphPopupPosition.y);
            }
            // Nodes may have been added/removed by the live update; keep the
            // lookup map in sync before re-rendering.
            rebuildNodeIndex();
            backfillNodeTrafficFromLinks();
            recordTrendSamples();
            staticDirty = true;
            // Compute whether any link has active traffic so the RAF loop
            // can pause when the map is idle (zero bps on every link).
            hasActiveTraffic = Array.isArray(mapData.links) &&
                mapData.links.some(l => (l.live?.in_bps > 0 || l.live?.out_bps > 0));
            renderMap();
            // Restart the animation loop if traffic appeared or a down-node
            // pulse is needed while it was paused.
            const hasDownNode = Array.isArray(mapData.nodes) &&
                mapData.nodes.some(n => (n.status || 'unknown') === 'down');
            if ((hasActiveTraffic || hasDownNode) && !animationId) {
                startAnimationLoop();
            }
        }
        function updateStatus() {
            const statusBar = document.getElementById('status-bar');
            const lastUpdated = document.getElementById('last-updated');

            if (lastDataUpdate) {
                const seconds = Math.floor((Date.now() - lastDataUpdate) / 1000);
                if (seconds < 5) {
                    lastUpdated.textContent = 'Just now';
                } else if (seconds < 60) {
                    lastUpdated.textContent = `${seconds}s ago`;
                } else {
                    const mins = Math.floor(seconds / 60);
                    lastUpdated.textContent = `${mins}m ago`;
                }
            } else {
                lastUpdated.textContent = 'Waiting...';
            }
            const rtts = (mapData.links || []).map(link => link.live?.latency_ms)
                .filter(value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)))
                .map(Number);
            document.getElementById('live-ping').textContent = rtts.length
                ? `RTT ${(rtts.reduce((sum, value) => sum + value, 0) / rtts.length).toFixed(1)} ms`
                : 'RTT N/A';
            statusBar.classList.toggle('is-stale', !lastDataUpdate || Date.now() - lastDataUpdate > Math.max(30, intervalSec * 2) * 1000);
            statusBar.style.display = 'flex';
        }

        function renderLegend() {
            const rows = document.getElementById('legend-rows');
            if (!rows) return;
            rows.innerHTML = '';
            const items = [
                { c: '#64748b', l: 'Unknown' },
                { c: '#06b6d4', l: '0–30%' },
                { c: '#10b981', l: '31–70%' },
                { c: '#f59e0b', l: '71–89%' },
                { c: '#ef4444', l: '90%+' }
            ];
            items.forEach(it => {
                const div = document.createElement('div');
                div.className = 'legend-row';
                div.innerHTML = `<span class="legend-swatch" style="--swatch:${it.c}"></span><span>${it.l}</span>`;
                rows.appendChild(div);
            });
            const metricLabel = document.createElement('div');
            metricLabel.className = 'legend-metric';
            metricLabel.textContent = `Metric: ${currentMetric}`;
            rows.appendChild(metricLabel);
        }

        function updateNocSummary() {
            const nodes = mapData.nodes || [];
            const links = mapData.links || [];
            const down = nodes.filter(node => node.status === 'down');
            document.getElementById('topology-summary').textContent = `${isFocusedView ? 'Focus · ' : ''}${nodes.length} devices · ${links.length} links · ${down.length} down`;
            const critical = [];
            const warning = [];
            for (const node of nodes) {
                const alerts = node.alerts || {};
                if (alerts.count > 0) {
                    const list = ['critical', 'severe'].includes(alerts.severity) ? critical : warning;
                    list.push({ count: alerts.count, label: nodeDisplayName(node) });
                }
            }
            for (const link of links) {
                const alerts = link.alerts || {};
                if (alerts.count > 0) {
                    const list = ['critical', 'severe'].includes(alerts.severity) ? critical : warning;
                    const source = nodeById.get(link.src ?? link.source ?? link.source_id);
                    list.push({ count: alerts.count, label: source?.label || 'Link' });
                }
            }
            const pill = document.getElementById('alert-pill');
            const hotLinks = links.filter(link => { const pct = getLinkPct(link, getLinkMetric(link)); return pct !== null && pct >= 90; });
            pill.classList.toggle('is-critical', critical.length > 0 || down.length > 0 || hotLinks.length > 0);
            pill.parentElement?.classList.toggle('has-critical', critical.length > 0 || down.length > 0 || hotLinks.length > 0);
            pill.classList.toggle('is-warning', critical.length === 0 && down.length === 0 && hotLinks.length === 0 && warning.length > 0);
            if (critical.length) {
                pill.textContent = `${critical.reduce((sum, item) => sum + Number(item.count || 0), 0)} critical alerts · ${critical[0].label}`;
            } else if (down.length) {
                pill.textContent = `${down.length} devices down · ${nodeDisplayName(down[0])}`;
            } else if (hotLinks.length) {
                const source = nodeById.get(hotLinks[0].src ?? hotLinks[0].source ?? hotLinks[0].source_id);
                pill.textContent = `${hotLinks.length} critical links · ${source?.label || 'High utilization'}`;
            } else if (warning.length) {
                pill.textContent = `${warning.reduce((sum, item) => sum + Number(item.count || 0), 0)} active alerts · ${warning[0].label}`;
            } else {
                pill.textContent = 'No active alerts';
            }
        }

        function exportPNG() {
            try {
                const out = document.createElement('canvas');
                out.width = canvas.width; out.height = canvas.height;
                const octx = out.getContext('2d');
                // Composite the static main canvas then the dynamic overlay.
                octx.drawImage(canvas, 0, 0);
                octx.drawImage(overlayCanvas, 0, 0);
                const a = document.createElement('a');
                a.href = out.toDataURL('image/png');
                a.download = `librelivetopology-${mapId}.png`;
                a.click();
            } catch (e) { console.error('Export failed', e); }
        }

        let pollTimer = null;
        function stopPolling() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }

        function startAutoUpdate() {
            stopPolling();
            currentTransport = 'poll';
            updateTransportButton();
            fetchLiveUpdate();
            pollTimer = setInterval(() => {
                fetchLiveUpdate();
            }, intervalSec * 1000);
        }

        function fetchLiveUpdate() {
            fetch(`${baseUrl}/plugin/LibreLiveTopology/api/maps/${mapId}/live`)
                .then(response => {
                    if (!response.ok) { console.warn('Live update failed: HTTP ' + response.status); return null; }
                    return response.json();
                })
                .then(live => {
                    if (live && !live.error) applyLiveUpdate(live);
                })
                .catch(error => {
                    console.error('Error fetching live update:', error);
                });
        }

        function fetchMapData() {
            fetch(`${baseUrl}/plugin/LibreLiveTopology/api/maps/${mapId}/json`)
                .then(response => {
                    if (!response.ok) { console.warn('Map data fetch failed: HTTP ' + response.status); return null; }
                    return response.json();
                })
                .then(data => {
                    if (data && !data.error) {
                        mapData = data;
                        applyTieredEmbedLayout();
                        rebuildNodeIndex();
                        lastDataUpdate = Date.now();
                        staticDirty = true;
                        renderMap();
                    }
                })
                .catch(error => {
                    console.error('Error updating map:', error);
                });
        }

        function showError(message) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('map-container').innerHTML = `
                <div class="error">
                    <div>${escapeHtml(message)}</div>
                </div>
            `;
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (canvas) {
                const container = document.getElementById('map-container');
                canvas.width = container.clientWidth;
                canvas.height = container.clientHeight;
                syncOverlayCanvas();
                staticDirty = true;
                renderMap();
            }
        });

        // Hover tooltip for link bandwidth
        function storeLinkGeom(link, x1, y1, x2, y2, pct, pathPoints, live = link.live || {}, siblings = [link]) {
            const inBps = live.in_bps ?? 0;
            const outBps = live.out_bps ?? 0;
            const bandwidth = link.bandwidth_bps || link.bandwidth || null;
            const points = pathPoints || [{x:x1,y:y1},{x:x2,y:y2}];
            const geometry = {x1,y1,x2,y2,pct,inBps,outBps,bandwidth,link,points,siblings};
            linkGeoms.push(geometry);
            linkGeomByLink.set(link, geometry);
        }

        function distToSegment(px, py, x1, y1, x2, y2) {
            const dx = x2 - x1, dy = y2 - y1;
            const len2 = dx*dx + dy*dy;
            if (len2 === 0) return Math.hypot(px - x1, py - y1);
            let t = ((px - x1)*dx + (py - y1)*dy) / len2;
            t = Math.max(0, Math.min(1, t));
            const projX = x1 + t*dx, projY = y1 + t*dy;
            return Math.hypot(px - projX, py - projY);
        }

        function distToPath(px, py, points) {
            let minDist = Infinity;
            for (let i = 1; i < points.length; i++) {
                const d = distToSegment(px, py, points[i-1].x, points[i-1].y, points[i].x, points[i].y);
                if (d < minDist) minDist = d;
            }
            return minDist;
        }

        function humanBits(v) {
            if (scale === 'bytes') {
                if (v >= 8e9) return (v/8e9).toFixed(2) + ' GB/s';
                if (v >= 8e6) return (v/8e6).toFixed(2) + ' MB/s';
                if (v >= 8e3) return (v/8e3).toFixed(2) + ' KB/s';
                return (v/8).toFixed(0) + ' B/s';
            }
            if (v >= 1e9) return (v/1e9).toFixed(2) + ' Gb/s';
            if (v >= 1e6) return (v/1e6).toFixed(2) + ' Mb/s';
            if (v >= 1e3) return (v/1e3).toFixed(2) + ' Kb/s';
            return v + ' b/s';
        }

        // Draw single-line text centered at (x, y) on a small rounded pill
        // background so labels stay legible over busy map backgrounds and
        // other nearby text, matching the dark NOC theme used elsewhere.
        function drawPillLabel(text, x, y, opts = {}) {
            if (!text) return;
            const font = opts.font || '10px Arial';
            ctx.font = font;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'alphabetic';
            const paddingX = opts.paddingX ?? 4;
            const paddingY = opts.paddingY ?? 2;
            const width = ctx.measureText(text).width;
            const fontSize = parseInt(font.match(/\d+(?=px)/)?.[0], 10) || 10;
            const height = fontSize + paddingY * 2;
            ctx.fillStyle = opts.bgColor || 'rgba(255,255,255,0.9)';
            ctx.beginPath();
            ctx.roundRect(x - width / 2 - paddingX, y - fontSize + 1, width + paddingX * 2, height, 4);
            ctx.fill();
            ctx.fillStyle = opts.textColor || '#17212b';
            ctx.fillText(text, x, y);
        }


        function updateTransportButton() {
            const btn = document.getElementById('toggle-transport');
            if (!btn) return;
            if (currentTransport === 'sse') {
                btn.textContent = 'LIVE · SSE';
            } else if (currentTransport === 'poll') {
                btn.textContent = 'LIVE · POLL';
            } else {
                btn.textContent = 'CONNECTING';
            }
            btn.title = `${currentTransport.toUpperCase()} updates every ${intervalSec}s · click to switch transport`;
        }

        // --- Graph hover popup state ---
        let graphHoverTimer = null;
        let graphPopupPosition = null;
        let graphHoverTarget = null; // { type: 'node'|'link', id, data }
        let graphHoverImg = null; // in-flight Image, aborted on new hover
        let touchInspectorKey = null;
        const graphBaseUrl = '{{ url("graph") }}';
        const graphPopup = document.getElementById('graph-popup');

        function hideGraphPopup() {
            if (graphHoverTimer) { clearTimeout(graphHoverTimer); graphHoverTimer = null; }
            if (graphHoverTarget) staticDirty = true;
            graphHoverTarget = null;
            if (graphHoverImg) { graphHoverImg.src = ''; graphHoverImg = null; }
            if (graphPopup) graphPopup.style.display = 'none';
        }

        function sparklineMarkup(target) {
            if (target.type === 'bundle') return '<span class="inspector-trend-fallback">Expand group for individual trends</span>';
            const samples = trendHistory.get(target.type + ':' + target.id) || [];
            if (samples.length < 2) return '<span class="inspector-trend-fallback">Collecting live trend…</span>';
            const values = samples.map(sample => sample.value);
            const min = Math.min(...values);
            const span = Math.max(1, Math.max(...values) - min);
            const first = samples[0].t;
            const duration = Math.max(1, samples[samples.length - 1].t - first);
            const points = samples.map(sample => `${((sample.t - first) / duration * 300).toFixed(1)},${(48 - (sample.value - min) / span * 38).toFixed(1)}`).join(' ');
            return `<svg viewBox="0 0 300 56" preserveAspectRatio="none" aria-hidden="true"><polyline fill="none" stroke="#00f2fe" stroke-width="2" vector-effect="non-scaling-stroke" points="${points}"/></svg>`;
        }

        function portGraphUrl(link, now = Math.floor(Date.now() / 1000)) {
            const portId = link.port_id_a || link.port_id_b;
            if (!/^\d+$/.test(String(portId ?? ''))) return null;
            return `${graphBaseUrl}?type=port_bits&id=${portId}&from=${now - 900}&to=${now}`;
        }

        function showGraphPopup(target, pageX, pageY) {
            graphPopupPosition = { x: pageX, y: pageY };
            if (!target || !graphPopup) return;
            const item = target.data;
            const isLink = target.type !== 'node';
            const group = target.type === 'bundle' ? target.group : null;
            const src = isLink ? nodeById.get(item.src ?? item.source ?? item.source_id) : null;
            const dst = isLink ? nodeById.get(item.dst ?? item.target ?? item.destination_id) : null;
            const title = isLink
                ? group ? `${nodeDisplayName(src)} ↔ ${nodeDisplayName(dst)} · ${group.length} links`
                    : `${nodeDisplayName(src)}:${item.source_port_name || 'port'} ↔ ${nodeDisplayName(dst)}:${item.destination_port_name || 'port'}`
                : nodeDisplayName(item);
            const live = isLink ? (group ? displayLinkLive(item) : (item.live || {})) : liveNodeTraffic(item);
            // With only the destination mapped, the graph is relative to that port.
            const graphAtDestination = isLink && !group && !item.port_id_a && !!item.port_id_b;
            const inBps = Number(live[graphAtDestination ? 'out_bps' : 'in_bps']) || 0;
            const outBps = Number(live[graphAtDestination ? 'in_bps' : 'out_bps']) || 0;
            const pct = isLink ? (group ? displayLinkPct(item) : getLinkPct(item, getLinkMetric(item))) : null;
            const status = isLink ? (pct === null ? 'Unknown' : pct >= 90 ? 'Critical' : pct >= 71 ? 'High load' : 'Healthy')
                : item.status === 'down' ? 'Down' : isWarningNode(item) ? 'High load' : item.status === 'up' ? 'Healthy' : 'Unknown';
            const statusClass = status === 'Down' || status === 'Critical' ? 'down' : status === 'High load' ? 'warn' : '';
            const packetLoss = group ? null : isLink ? live.packet_loss ?? live.drop_rate : item.packet_loss ?? item.metrics?.packet_loss;
            const latency = group ? null : isLink ? live.latency_ms : item.latency_ms ?? item.metrics?.latency_ms;
            const bgp = group ? null : isLink ? live.bgp_status : item.bgp_status ?? item.metrics?.bgp_status ?? item.meta?.bgp_status;
            const numericMetric = (value, unit, digits) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value).toFixed(digits) + unit : 'N/A';
            const metricCell = (label, value) => `<div class="inspector-metric"><span>${label}</span><strong>${escapeHtml(value)}</strong></div>`;
            graphPopup.innerHTML = `
                <div class="inspector-head"><div><div class="inspector-kicker">${group ? 'Parallel link group' : isLink ? 'Interface link' : 'Network device'}</div><div class="inspector-title">${escapeHtml(title)}</div></div><span class="inspector-status ${statusClass}">${status}</span></div>
                <div class="inspector-traffic"><span>↓ RX <strong>${escapeHtml(humanBits(inBps))}</strong></span><span>↑ TX <strong>${escapeHtml(humanBits(outBps))}</strong></span></div>
                <div class="inspector-trend">${sparklineMarkup(target)}</div>
                <div class="inspector-trend-caption">${isLink && !group ? 'RX/TX at graphed port &middot; ' : ''}Throughput · last 15 minutes</div>
                <div class="inspector-metrics">
                    ${metricCell('Throughput', humanBits(inBps + outBps))}
                    ${metricCell('Utilization', pct === null ? 'N/A' : formatPct(pct))}
                    ${metricCell('Packet loss / drop', numericMetric(packetLoss, '%', 2))}
                    ${metricCell('Ping RTT', numericMetric(latency, ' ms', 1))}
                    ${metricCell('BGP', bgp === null || bgp === undefined || bgp === '' ? 'N/A' : String(bgp))}
                    ${metricCell('Active alerts', String(group ? group.reduce((sum, link) => sum + Number(link.alerts?.count || 0), 0) : (item.alerts?.count ?? 0)))}
                </div>
                <div class="inspector-foot">${group ? 'Click the line to expand individual circuits' : isLink ? (item.port_id_a || item.port_id_b ? 'Click to open LibreNMS port graphs' : 'No port graph mapped') : (item.device_id ? 'Click to open device in LibreNMS' : 'No LibreNMS device mapped')}</div>`;
            graphPopup.style.display = 'block';
            const box = graphPopup.getBoundingClientRect();
            graphPopup.style.left = clamp(pageX + 14, 6, Math.max(6, window.innerWidth - box.width - 6)) + 'px';
            graphPopup.style.top = clamp(pageY + 14, 6, Math.max(6, window.innerHeight - box.height - 6)) + 'px';

            const graphId = isLink ? item.port_id_a || item.port_id_b : item.device_id;
            if (dashboardMode || group || !graphsEnabled || !/^\d+$/.test(String(graphId ?? ''))) return;
            const now = Math.floor(Date.now() / 1000);
            const url = isLink ? portGraphUrl(item, now) : `${graphBaseUrl}?type=device_bits&id=${graphId}&from=${now - 900}&to=${now}`;
            if (graphHoverImg) { graphHoverImg.src = ''; graphHoverImg = null; }
            const img = new Image();
            graphHoverImg = img;
            img.alt = 'LibreNMS throughput trend for the last 15 minutes';
            img.onload = () => {
                if (graphHoverTarget !== target || graphHoverImg !== img) return;
                graphHoverImg = null;
                graphPopup.querySelector('.inspector-trend')?.replaceChildren(img);
            };
            img.onerror = () => { if (graphHoverImg === img) graphHoverImg = null; };
            img.src = url;
        }

        document.getElementById('map-canvas').addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const mx = (x - viewOffsetX) / Math.max(0.0001, viewScale);
            const my = (y - viewOffsetY) / Math.max(0.0001, viewScale);
            // Node hover first
            let nbest = null; let nd = 1e9;
            for (const g of nodeGeoms) {
                const d = Math.max(Math.abs(mx - g.x) - g.w, Math.abs(my - g.y) - g.h);
                if (d < nd && d < 8) { nd = d; nbest = g; }
            }
            let best = null, bestDist = 12; // threshold px
            if (!nbest) {
                for (const g of linkGeoms) {
                    if (g.points && g.points.length > 2) {
                        const pts = g.points.map(p => ({
                            x: p.x * viewScale + viewOffsetX,
                            y: p.y * viewScale + viewOffsetY
                        }));
                        const d = distToPath(x, y, pts);
                        if (d < bestDist) { bestDist = d; best = g; }
                    } else {
                        const lx1 = g.x1 * viewScale + viewOffsetX;
                        const ly1 = g.y1 * viewScale + viewOffsetY;
                        const lx2 = g.x2 * viewScale + viewOffsetX;
                        const ly2 = g.y2 * viewScale + viewOffsetY;
                        const d = distToSegment(x, y, lx1, ly1, lx2, ly2);
                        if (d < bestDist) { bestDist = d; best = g; }
                    }
                }
            }
            let newTarget = null;
            if (nbest) {
                const n = nbest.node;
                newTarget = { type: 'node', id: n.id, data: n };
            } else if (best) {
                const link = best.link;
                if (link) {
                    const key = linkEndpointKey(link);
                    const collapsed = best.siblings.length > 1 && !expandedBundles.has(key);
                    newTarget = { type: collapsed ? 'bundle' : 'link', id: collapsed ? key : link.id, data: link, group: collapsed ? best.siblings : null };
                }
            }
            // Graph popup: schedule or cancel based on hover target
            if (newTarget) {
                const targetKey = newTarget.type + ':' + newTarget.id;
                const prevKey = graphHoverTarget ? graphHoverTarget.type + ':' + graphHoverTarget.id : null;
                if (targetKey !== prevKey) {
                    hideGraphPopup();
                    graphHoverTarget = newTarget;
                    staticDirty = true;
                    renderMap(true);
                    const px = e.pageX, py = e.pageY;
                    graphHoverTimer = setTimeout(() => {
                        if (graphHoverTarget === newTarget) showGraphPopup(newTarget, px, py);
                    }, 180);
                }
            } else {
                hideGraphPopup();
            }
        });
        // Hide graph popup when leaving the canvas
        document.getElementById('map-canvas').addEventListener('mouseleave', hideGraphPopup);
        // Click: node → device page; else link → port graphs
        document.getElementById('map-canvas').addEventListener('click', (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const mx = (x - viewOffsetX) / Math.max(0.0001, viewScale);
            const my = (y - viewOffsetY) / Math.max(0.0001, viewScale);
            // The first touch inspects; a second tap on the same item follows
            // the existing LibreNMS device / port graph click-through.
            if (e.pointerType === 'touch') {
                let target = null;
                const nodeGeom = nodeGeoms.find(g => Math.abs(mx - g.x) <= g.w + 4 && Math.abs(my - g.y) <= g.h + 4);
                if (nodeGeom) {
                    target = { type: 'node', id: nodeGeom.node.id, data: nodeGeom.node };
                } else {
                    const linkGeom = linkGeoms.find(g => {
                        const points = g.points.map(p => ({ x: p.x * viewScale + viewOffsetX, y: p.y * viewScale + viewOffsetY }));
                        return distToPath(x, y, points) < 12;
                    });
                    if (linkGeom) {
                        const key = linkEndpointKey(linkGeom.link);
                        const collapsed = linkGeom.siblings.length > 1 && !expandedBundles.has(key);
                        target = { type: collapsed ? 'bundle' : 'link', id: collapsed ? key : linkGeom.link.id, data: linkGeom.link, group: collapsed ? linkGeom.siblings : null };
                    }
                }
                const key = target ? target.type + ':' + target.id : null;
                if (target && key !== touchInspectorKey) {
                    hideGraphPopup();
                    touchInspectorKey = key;
                    graphHoverTarget = target;
                    staticDirty = true;
                    renderMap(true);
                    showGraphPopup(target, e.clientX, e.clientY);
                    return;
                }
                touchInspectorKey = null;
            }
            // Alert badge hit detection (before node/link) — opens device alerts.
            for (const g of nodeGeoms) {
                const n = g.node;
                if (showAlertBadge(n)) {
                    const bx = g.alertX;
                    const by = g.alertY;
                    if (Math.hypot(mx - bx, my - by) < 10) {
                        const did = n.device_id || n.deviceId || n.deviceid;
                        if (did) {
                            window.open(deviceBaseUrl + '/' + did + '/tab=alerts/', LLT_CONFIG.linkTarget || '_blank');
                        }
                        return;
                    }
                }
            }
            // Link alert badge hit detection (diamond at midpoint offset).
            for (const g of linkGeoms) {
                const link = g.link;
                if (link && showAlertBadge(link)) {
                    const mid = getPathMidpoint(g.points);
                    const bx = mid.x + 10;
                    const by = mid.y - 10;
                    if (Math.hypot(mx - bx, my - by) < 12) {
                        const srcId = link.source ?? link.src ?? link.source_id;
                        const srcNode = nodeById.get(srcId);
                        const did = srcNode && (srcNode.device_id || srcNode.deviceId || srcNode.deviceid);
                        if (did) {
                            window.open(deviceBaseUrl + '/' + did + '/tab=alerts/', LLT_CONFIG.linkTarget || '_blank');
                        }
                        return;
                    }
                }
            }
            for (const g of nodeGeoms) {
                if (Math.abs(mx - g.x) <= g.w + 4 && Math.abs(my - g.y) <= g.h + 4) {
                    const n = g.node;
                    const did = n.device_id || n.deviceId || n.deviceid;
                    if (did) {
                        const url = deviceBaseUrl + '/' + did;
                        window.open(url, LLT_CONFIG.linkTarget || '_blank');
                        return;
                    }
                }
            }
            // Else link
            let best = null, bestDist = 10;
            for (const g of linkGeoms) {
                if (g.points && g.points.length > 2) {
                    const pts = g.points.map(p => ({
                        x: p.x * viewScale + viewOffsetX,
                        y: p.y * viewScale + viewOffsetY
                    }));
                    const d = distToPath(x, y, pts);
                    if (d < bestDist) { bestDist = d; best = g; }
                } else {
                    const lx1 = g.x1 * viewScale + viewOffsetX;
                    const ly1 = g.y1 * viewScale + viewOffsetY;
                    const lx2 = g.x2 * viewScale + viewOffsetX;
                    const ly2 = g.y2 * viewScale + viewOffsetY;
                    const d = distToSegment(x, y, lx1, ly1, lx2, ly2);
                    if (d < bestDist) { bestDist = d; best = g; }
                }
            }
            if (best && best.link) {
                const bundleKey = linkEndpointKey(best.link);
                if (best.siblings.length > 1 && !expandedBundles.has(bundleKey)) {
                    expandedBundles.add(bundleKey);
                    hideGraphPopup();
                    staticDirty = true;
                    renderMap();
                    return;
                }
                const url = portGraphUrl(best.link);
                if (url) window.open(url, LLT_CONFIG.linkTarget || '_blank');
            }
        });
        function drawMinimap() {
            if (!minimap || !Array.isArray(mapData.nodes) || mapData.nodes.length === 0) return;

            // Calculate actual bounds from node positions
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            mapData.nodes.forEach(n => {
                const nx = (n.position?.x ?? n.x) || 0;
                const ny = (n.position?.y ?? n.y) || 0;
                minX = Math.min(minX, nx);
                minY = Math.min(minY, ny);
                maxX = Math.max(maxX, nx);
                maxY = Math.max(maxY, ny);
            });

            // Frame the displayed topology, including disconnected components.
            const padding = 50;
            const originX = minX - padding;
            const originY = minY - padding;
            const mw = Math.max(1, maxX - minX + padding * 2);
            const mh = Math.max(1, maxY - minY + padding * 2);

            const w = minimap.width, h = minimap.height;
            const s = Math.min(w/mw, h/mh);
            // Center the map in minimap
            const offsetX = (w - mw * s) / 2;
            const offsetY = (h - mh * s) / 2;
            const ctxm = minimap.getContext('2d');
            ctxm.clearRect(0,0,w,h);
            ctxm.fillStyle = '#0d1b2c'; ctxm.fillRect(0,0,w,h);

            // Draw map boundary
            ctxm.strokeStyle = '#2a4055';
            ctxm.strokeRect(offsetX, offsetY, mw * s, mh * s);

            // Draw nodes
            mapData.nodes.forEach(n => {
                const x = offsetX + (((n.position?.x ?? n.x)||0) - originX) * s;
                const y = offsetY + (((n.position?.y ?? n.y)||0) - originY) * s;
                ctxm.fillStyle = getNodeColor(n);
                ctxm.beginPath();
                ctxm.arc(x, y, 3, 0, Math.PI * 2);
                ctxm.fill();
            });

            // Draw viewport rectangle (what's currently visible)
            if (viewScale > 0 && canvas) {
                const vpLeft = ((-viewOffsetX / viewScale) - originX) * s + offsetX;
                const vpTop = ((-viewOffsetY / viewScale) - originY) * s + offsetY;
                const vpWidth = (canvas.width / viewScale) * s;
                const vpHeight = (canvas.height / viewScale) * s;
                ctxm.strokeStyle = 'rgba(0, 242, 254, 0.8)';
                ctxm.lineWidth = 2;
                ctxm.strokeRect(vpLeft, vpTop, vpWidth, vpHeight);
                ctxm.lineWidth = 1;
            }

            // Border
            ctxm.strokeStyle = '#365069'; ctxm.strokeRect(0,0,w,h);
        }
    </script>
</body>
</html>
