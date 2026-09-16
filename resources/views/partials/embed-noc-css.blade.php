@verbatim
:root {
    color-scheme: dark;
    --noc-bg: #0b0f19;
    --noc-panel: rgba(13, 22, 37, .86);
    --noc-border: #1e293b;
    --noc-text: #e6f0fb;
    --noc-muted: #8ca0b8;
    --noc-cyan: #00f2fe;
    --noc-green: #10b981;
    --noc-amber: #f59e0b;
    --noc-red: #ef4444;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
}

* { box-sizing: border-box; }
html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; }
body { background: var(--noc-bg); color: var(--noc-text); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
button, input, select { font: inherit; }
button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--noc-cyan); outline-offset: 2px; }

#map-container { width: 100%; height: 100%; background: var(--noc-bg); overflow: hidden; }
.embed-icon-defs { position: absolute; width: 0; height: 0; overflow: hidden; }
#map-canvas { cursor: grab; }
#map-canvas:active { cursor: grabbing; }

.embed-nav-bar {
    position: absolute; z-index: 1001; inset: 10px 12px auto; width: auto; min-height: 54px;
    display: grid; grid-template-columns: minmax(250px, auto) minmax(100px, 1fr);
    gap: 18px; align-items: center; padding: 8px 12px;
    color: var(--noc-text); background: var(--noc-panel);
    border: 1px solid var(--noc-border); border-radius: 14px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, .36), inset 0 1px rgba(255, 255, 255, .035);
    backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
}
.embed-nav-left { display: flex; gap: 10px; align-items: center; min-width: 0; }
.embed-brand-mark { width: 32px; height: 32px; display: grid; place-items: center; flex: none; color: var(--noc-cyan); border-radius: 9px; border: 1px solid rgba(0, 242, 254, .36); background: rgba(0, 242, 254, .08); box-shadow: 0 0 20px rgba(0, 242, 254, .12); }
.embed-brand-mark svg { width: 19px; height: 19px; stroke: currentColor; stroke-width: 1.6; }
.embed-brand-text { color: #f5fbff; font-size: 11px; font-weight: 800; letter-spacing: .09em; line-height: 1.2; white-space: nowrap; }
.embed-brand-text span { color: var(--noc-cyan); }
.embed-brand-text small { display: block; color: var(--noc-muted); font-size: 7px; letter-spacing: .19em; margin-top: 3px; }
.status-bar { position: static; display: flex; align-items: center; gap: 8px; padding: 0; margin-left: 8px; color: var(--noc-muted); background: none; box-shadow: none; white-space: nowrap; }
.status-bar #toggle-transport { border: 1px solid rgba(16, 185, 129, .38); background: rgba(16, 185, 129, .11); color: #65edbd; border-radius: 99px; padding: 5px 9px; font-family: "JetBrains Mono", ui-monospace, monospace; font-weight: 700; font-size: 10px; letter-spacing: .03em; }
.status-bar #toggle-transport::before { content: ""; display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 6px; background: currentColor; box-shadow: 0 0 8px currentColor; vertical-align: 1px; }
.status-bar.is-stale #toggle-transport { color: #fbbf24; background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .34); }
.embed-live-ping { color: #98e2ec; font: 10px "JetBrains Mono", ui-monospace, monospace; }
.embed-updated { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 10px; }
.embed-breadcrumb { min-width: 0; display: flex; justify-content: flex-end; align-items: center; gap: 8px; color: var(--noc-muted); font-size: 11px; overflow: hidden; white-space: nowrap; }
.embed-breadcrumb .embed-nav-link { color: var(--noc-muted); text-decoration: none; flex: none; }
.embed-breadcrumb .embed-nav-link:hover { color: var(--noc-cyan); }
.embed-breadcrumb-separator { color: #456078; }
.embed-breadcrumb strong { color: #dfe9f6; font-weight: 650; overflow: hidden; text-overflow: ellipsis; }
.embed-nav-demo { flex: none; color: #fbbf24; border: 1px solid rgba(245, 158, 11, .35); background: rgba(245, 158, 11, .1); border-radius: 99px; padding: 3px 6px; font-size: 8px; }

.embed-controls {
    position: absolute; z-index: 1001; top: 76px; left: 14px; right: auto;
    display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    width: 270px; padding: 10px; border: 1px solid #334155; border-radius: 12px;
    background: rgba(15, 23, 42, .75); box-shadow: 0 14px 36px rgba(0, 0, 0, .32);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
}
.embed-controls .btn, .embed-viz-menu .btn { color: #a9bfd4; background: rgba(29, 43, 63, .7); border: 1px solid #26394f; border-radius: 8px; min-width: 31px; min-height: 31px; padding: 5px 8px; font-size: 11px; font-weight: 650; line-height: 1.2; white-space: nowrap; }
.embed-controls > .btn, .embed-zoom-controls .btn, .embed-more > .btn { width: 31px; height: 31px; padding: 6px; display: inline-grid; place-items: center; position: relative; }
.embed-controls .btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.embed-controls .btn[data-tooltip]:hover::after, .embed-zoom-controls .btn[data-tooltip]:hover::after {
    content: attr(data-tooltip); position: absolute; z-index: 5; left: 50%; top: calc(100% + 7px);
    transform: translateX(-50%); padding: 5px 7px; color: #e8f4ff; background: #142338;
    border: 1px solid #34506a; border-radius: 5px; font: 10px "JetBrains Mono", ui-monospace, monospace;
    white-space: nowrap; pointer-events: none;
}
.embed-controls .btn:hover, .embed-viz-menu .btn:hover { color: #fff; border-color: #3b6477; background: rgba(35, 62, 80, .8); }
.embed-controls .btn-primary { color: var(--noc-cyan); border-color: rgba(0, 242, 254, .35); background: rgba(0, 242, 254, .1); }
.embed-controls .btn-secondary { color: var(--noc-muted); }
.embed-search { order: -2; flex: 1 0 100%; width: 100%; min-width: 0; height: 34px; display: flex; align-items: center; gap: 6px; padding: 0 8px; color: var(--noc-muted); background: rgba(5, 11, 23, .67); border: 1px solid #334155; border-radius: 8px; }
.embed-search:focus-within { border-color: var(--noc-cyan); }
.embed-search svg { width: 14px; height: 14px; flex: none; stroke: currentColor; stroke-width: 1.8; }
.embed-search input { width: 100%; min-width: 0; padding: 0; border: 0; outline: none; background: none; color: var(--noc-text); font-size: 11px; }
.embed-search input::placeholder { color: #688097; }
.embed-search input::-webkit-search-cancel-button { filter: invert(1); }
.embed-search kbd { color: #91a9bc; border: 1px solid #314358; border-radius: 3px; padding: 0 4px; font-size: 9px; white-space: nowrap; }
.embed-search-result { order: -1; display: none; flex: 1 0 100%; color: var(--noc-cyan); font: 10px "JetBrains Mono", ui-monospace, monospace; white-space: nowrap; }
.embed-search-result:not(:empty) { display: block; }
.embed-zoom-controls { display: inline-flex; gap: 5px; border: 0; padding: 0; }
.embed-more { position: relative; }
.embed-viz-menu { display: none; position: absolute; left: 0; right: auto; top: calc(100% + 8px); width: min(268px, calc(100vw - 16px)); box-sizing: border-box; overflow-y: auto; z-index: 50; padding: 14px; color: var(--noc-text); background: rgba(13, 22, 37, .97); border: 1px solid #2a4055; border-radius: 12px; box-shadow: 0 18px 48px rgba(0, 0, 0, .48); backdrop-filter: blur(18px); }
.embed-viz-section { color: var(--noc-text); border-color: #2a4055; padding-bottom: 9px; margin-bottom: 10px; font-size: 12px; }
.embed-viz-label { display: block; margin: 0 0 5px; color: var(--noc-muted); font-size: 10px; font-weight: 600; }
.embed-viz-menu select, .embed-viz-menu input[type="range"] { width: 100%; accent-color: var(--noc-cyan); }
.embed-viz-menu select { margin-bottom: 12px; padding: 7px; color: var(--noc-text); background: #0b1524; border: 1px solid #2a4055; border-radius: 6px; font-size: 11px; }
.embed-viz-row { margin-bottom: 10px; }
.embed-nav-edit { display: block; padding: 8px 0; color: var(--noc-cyan); text-decoration: none; font-size: 11px; }
.embed-viz-menu #export-png { width: 100%; }
.embed-viz-menu #collapse-bundles { width: 100%; margin: 3px 0; }

.embed-legend { position: absolute; left: 12px; bottom: 12px; display: flex; align-items: center; gap: 14px; max-width: calc(100% - 24px); padding: 9px 12px; color: var(--noc-text); background: var(--noc-panel); border: 1px solid var(--noc-border); border-radius: 11px; box-shadow: 0 12px 30px rgba(0, 0, 0, .3); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
.embed-legend-title { margin: 0; color: #829bb4; font-size: 9px; font-weight: 800; letter-spacing: .11em; white-space: nowrap; }
#legend-rows { display: flex; align-items: center; gap: 11px; flex-wrap: wrap; }
.legend-row { display: flex; gap: 5px; align-items: center; color: #c1d4e5; font: 10px "JetBrains Mono", ui-monospace, monospace; white-space: nowrap; }
.legend-swatch { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--swatch); box-shadow: 0 0 8px var(--swatch); }
.legend-metric { color: #648098; font: 9px "JetBrains Mono", ui-monospace, monospace; text-transform: uppercase; }
.embed-bottom-right { position: absolute; right: 12px; bottom: 12px; display: flex; align-items: center; gap: 7px; max-width: min(45vw, 410px); z-index: 1000; }
.embed-topology-summary { padding: 8px 10px; color: #8da6bd; background: var(--noc-panel); border: 1px solid var(--noc-border); border-radius: 9px; font: 10px "JetBrains Mono", ui-monospace, monospace; white-space: nowrap; backdrop-filter: blur(16px); }
.embed-alert-pill { max-width: 235px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 8px 11px; color: #79deb9; background: rgba(11, 38, 37, .89); border: 1px solid rgba(16, 185, 129, .3); border-radius: 9px; font: 10px "JetBrains Mono", ui-monospace, monospace; box-shadow: 0 0 18px rgba(16, 185, 129, .07); }
.embed-alert-pill.is-critical { color: #ffb3b3; background: rgba(63, 20, 31, .9); border-color: rgba(239, 68, 68, .48); box-shadow: 0 0 18px rgba(239, 68, 68, .12); }
.embed-alert-pill.is-warning { color: #fbd27d; background: rgba(67, 45, 15, .9); border-color: rgba(245, 158, 11, .37); }

.embed-minimap { top: auto; right: 12px; bottom: 53px; width: 132px; height: 99px; background: rgba(12, 23, 39, .94); border: 1px solid #2b4056; border-radius: 10px; box-shadow: 0 12px 30px rgba(0, 0, 0, .25); }
.loading, .error { background: var(--noc-bg); color: var(--noc-muted); }
.embed-graph-popup { z-index: 1002; width: min(328px, calc(100vw - 20px)); max-width: none; padding: 13px; color: var(--noc-text); background: rgba(12, 21, 36, .96); border: 1px solid #30465e; border-radius: 13px; box-shadow: 0 20px 45px rgba(0, 0, 0, .52), 0 0 24px rgba(0, 242, 254, .06); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); pointer-events: none; }
.inspector-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
.inspector-kicker { color: var(--noc-cyan); font: 9px "JetBrains Mono", ui-monospace, monospace; letter-spacing: .12em; text-transform: uppercase; }
.inspector-title { margin: 5px 0 0; color: #f2f8ff; font-size: 12px; line-height: 1.4; font-weight: 700; overflow-wrap: anywhere; }
.inspector-status { flex: none; color: var(--noc-green); font: 10px "JetBrains Mono", ui-monospace, monospace; text-transform: uppercase; }
.inspector-status.warn { color: var(--noc-amber); }.inspector-status.down { color: var(--noc-red); }
.inspector-traffic { display: flex; justify-content: space-between; gap: 8px; margin: 12px 0 8px; color: #d6e7f6; font: 10px "JetBrains Mono", ui-monospace, monospace; }
.inspector-traffic strong { color: #f1f9ff; font-size: 11px; }
.inspector-trend { height: 56px; display: grid; place-items: center; overflow: hidden; background: rgba(4, 15, 28, .78); border: 1px solid #1e354b; border-radius: 8px; }
.inspector-trend svg { width: 100%; height: 100%; }
.inspector-trend img { display: block; width: 100%; height: 100%; max-width: none; max-height: none; object-fit: cover; object-position: center; opacity: .8; }
.inspector-trend-fallback { color: #668099; font-size: 10px; }
.inspector-trend-caption { margin-top: 5px; color: #708aa2; font-size: 9px; }
.inspector-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; margin-top: 11px; }
.inspector-metric { padding: 7px; background: rgba(24, 41, 60, .64); border: 1px solid #243a50; border-radius: 7px; min-width: 0; }
.inspector-metric span { display: block; margin-bottom: 4px; color: #7994ab; font-size: 9px; }
.inspector-metric strong { display: block; color: #e7f2fb; font: 11px "JetBrains Mono", ui-monospace, monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inspector-foot { margin-top: 9px; color: #7791a8; font-size: 9px; }

body.kiosk-mode .embed-bottom-right { display: none; }
body.kiosk-mode .embed-bottom-right.has-critical { display: flex; }
body.kiosk-mode .embed-bottom-right.has-critical .embed-topology-summary { display: none; }
body.kiosk-mode.show-chrome .embed-bottom-right { display: flex; }
body.kiosk-mode.show-chrome .embed-nav-bar { display: grid !important; }
body.kiosk-mode.show-chrome .embed-legend { display: flex !important; }
body.kiosk-mode.show-chrome .status-bar { display: flex !important; }
.kiosk-exit { background: rgba(14, 25, 40, .95); border: 1px solid #30465e; border-radius: 9px; }

@media (max-width: 1100px) {
    .embed-nav-bar { gap: 7px 12px; }
    .embed-breadcrumb { justify-self: end; }
    .embed-bottom-right { bottom: 53px; }
}
@media (max-width: 640px) {
    .embed-nav-bar { inset: 6px 6px auto; min-height: 0; padding: 8px; gap: 8px; border-radius: 11px; grid-template-columns: minmax(0, 1fr) minmax(0, auto); }
    .embed-brand-text small, .embed-updated, .embed-live-ping, .embed-search kbd { display: none; }
    .embed-nav-left { gap: 6px; }
    .status-bar { margin-left: 2px; }
    .status-bar #toggle-transport { font-size: 9px; padding: 4px 6px; }
    .embed-breadcrumb { font-size: 10px; }
    .embed-breadcrumb .embed-nav-link, .embed-breadcrumb-separator, .embed-nav-demo { display: none; }
    .embed-controls { top: 63px; left: 6px; width: min(270px, calc(100vw - 12px)); }
    .embed-controls .btn { min-width: 29px; min-height: 29px; font-size: 10px; }
    .embed-viz-menu { width: min(268px, calc(100vw - 16px)); }
    .embed-search { flex: 1 0 100%; width: 100%; min-width: 75px; }
    .embed-search-result { display: none; }
    .embed-minimap, .embed-topology-summary { display: none; }
    .embed-legend { bottom: 6px; left: 6px; right: 6px; max-width: none; justify-content: center; padding: 7px; gap: 7px; }
    .embed-legend-title, .legend-metric { display: none; }
    #legend-rows { gap: 6px; }
    .legend-row { font-size: 9px; }
    .embed-bottom-right { top: auto; right: 6px; bottom: 42px; max-width: 75vw; }
    .embed-alert-pill { font-size: 9px; }
}
@media (max-width: 380px) {
    .embed-brand-text { display: none; }
    .embed-controls .embed-search { min-width: 70px; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
@endverbatim
