/* Advanced editor features: historical replay, client-only what-if mode, latency styling, audio. */
var S = window.LLT.EditorState;

function advancedDebounce(fn, wait) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

function applyReplayMap(map) {
    const entries = collection => Array.isArray(collection)
        ? collection.map(value => [value.id, value]) : Object.entries(collection || {});
    S.nodes = entries(map.nodes).map(([key, node]) => ({
        id: node.id ?? key, dbId: node.id ?? key, label: node.label || 'Node',
        x: Number(node.x) || 0, y: Number(node.y) || 0,
        deviceId: node.device_id || null, deviceName: node.device_name || null,
        status: node.status || null, interfaceId: node.meta?.interface_id || null,
        deviceType: node.meta?.device_type || 'auto',
        topologyRole: node.meta?.topology_role || 'auto', meta: node.meta || {},
    }));
    S.links = entries(map.links).map(([key, link]) => ({
        id: link.id ?? key, dbId: link.id ?? key, srcId: link.src ?? link.src_node_id,
        dstId: link.dst ?? link.dst_node_id, portA: link.port_id_a || null,
        portB: link.port_id_b || null, bw: link.bandwidth_bps || null,
        sourcePortName: link.source_port_name || null,
        destinationPortName: link.destination_port_name || null,
        style: link.style || {}
    }));
    // Version snapshots nest map metadata; live JSON flattens dimensions.
    const width = Number(map.map?.width ?? map.width ?? map.options?.width);
    const height = Number(map.map?.height ?? map.height ?? map.options?.height);
    if (Number.isFinite(width) && width > 0) S.mapWidth = width;
    if (Number.isFinite(height) && height > 0) S.mapHeight = height;
    S.selectedNode = null;
    S.selectedNodes = [];
    S.linkStart = null;
    S.isDragging = false;
    S.marquee = null;
    S.undoStack = [];
    S.redoStack = [];
    if (typeof populateDefaultStyles === 'function') populateDefaultStyles(map.options || {});
    refreshReplayEditor();
}

function refreshReplayEditor() {
    const width = document.getElementById('map-width'), height = document.getElementById('map-height');
    if (width) width.value = S.mapWidth;
    if (height) height.value = S.mapHeight;
    if (typeof S.fitCanvasToWrap === 'function') S.fitCanvasToWrap();
    if (typeof populateNodeProperties === 'function') populateNodeProperties(S.selectedNode);
    if (typeof renderNodesList === 'function') renderNodesList();
    if (typeof renderLinksList === 'function') renderLinksList();
    if (typeof updateToolbarState === 'function') updateToolbarState();
    if (typeof updateZoomDisplay === 'function') updateZoomDisplay();
    renderEditor();
}

function beginReplay(timestamp) {
    if (!S.replay.draft) {
        const fields = {};
        for (const id of ['map-name', 'map-title', 'map-tags', 'default-node-color', 'default-node-label-color',
            'default-link-color', 'default-link-width', 'default-link-via-style']) {
            const field = document.getElementById(id);
            if (field) fields[id] = field.value;
        }
        S.replay.draft = JSON.parse(JSON.stringify({
            nodes: S.nodes, links: S.links, mapWidth: S.mapWidth, mapHeight: S.mapHeight,
            viewScale: S.viewScale, viewOffsetX: S.viewOffsetX, viewOffsetY: S.viewOffsetY,
            undoStack: S.undoStack, redoStack: S.redoStack, telemetry: S.telemetry,
            hasUnsavedChanges: S.hasUnsavedChanges, fields,
            selectedId: S.selectedNode?.id, selectedIds: S.selectedNodes.map(node => node.id),
        }));
        S.undoStack = [];
        S.redoStack = [];
        S.replay.controls = Array.from(document.querySelectorAll?.('.editor-sidebar input, .editor-sidebar select') || [])
            .map(element => ({ element, disabled: element.disabled }));
        for (const { element } of S.replay.controls) element.disabled = true;
    }
    S.replay.active = true;
    S.replay.loading = true;
    S.replay.timestamp = timestamp;
    S.isDragging = false;
    S.linkStart = null;
    S.marquee = null;
    S.replay.requestId = (S.replay.requestId || 0) + 1;
    const label = document.getElementById('advanced-mode-label');
    if (label) label.textContent = 'Loading replay (read only)';
    return S.replay.requestId;
}

async function loadReplay(timestamp, requestId) {
    if (!S.uris.replay) return false;
    if (requestId === undefined) requestId = beginReplay(timestamp);
    const current = () => S.replay.active && requestId === S.replay.requestId;
    if (!current()) return false;
    try {
        const response = await fetch(S.uris.replay + '?timestamp=' + encodeURIComponent(timestamp), {
            headers: { Accept: 'application/json' }
        });
        if (!current()) return false;
        if (!response.ok) throw new Error('Replay request failed: HTTP ' + response.status);
        const payload = await response.json();
        if (!current()) return false;
        S.telemetry = payload.links || {};
        applyReplayMap(payload.map || {});
        S.replay.loading = false;
        const label = document.getElementById('advanced-mode-label');
        if (label) label.textContent = 'Replay (read only): ' + new Date(timestamp * 1000).toLocaleString();
        return true;
    } catch (error) {
        if (!current()) return false;
        setLiveMode();
        throw error;
    }
}

function setLiveMode() {
    S.replay.requestId = (S.replay.requestId || 0) + 1;
    S.replay.active = false;
    S.replay.loading = false;
    S.replay.timestamp = null;
    const draft = S.replay.draft;
    S.replay.draft = null;
    for (const { element, disabled } of S.replay.controls || []) element.disabled = disabled;
    S.replay.controls = null;
    if (draft) {
        const { fields, selectedId, selectedIds, ...state } = draft;
        Object.assign(S, state);
        S.selectedNode = S.nodes.find(node => node.id === selectedId) || null;
        S.selectedNodes = S.nodes.filter(node => selectedIds.includes(node.id));
        for (const [id, value] of Object.entries(fields)) {
            const field = document.getElementById(id);
            if (field) field.value = value;
        }
        const indicator = document.getElementById('unsaved-indicator');
        if (indicator) indicator.style.display = S.hasUnsavedChanges ? 'inline' : 'none';
        refreshReplayEditor();
    }
    const label = document.getElementById('advanced-mode-label');
    if (label) label.textContent = 'Live mode';
    const status = document.getElementById('advanced-status');
    if (status) status.textContent = '';
}

function toggleSimulation() {
    S.simulation.active = !S.simulation.active;
    const label = document.getElementById('advanced-mode-label');
    if (label) label.textContent = S.simulation.active
        ? 'Simulation: click a link to fail it'
        : (S.replay.active ? 'Replay mode' : 'Live mode');
    const button = document.getElementById('simulation-toggle');
    if (button) button.classList.toggle('active', S.simulation.active);
    renderEditor();
}

function nearestLinkAt(point) {
    let nearest = null;
    let best = 14 / (typeof editorScreenScale === 'function' ? editorScreenScale() : Math.max(0.25, S.viewScale));
    for (const link of S.links) {
        for (const segment of (link._segs || [])) {
            const dx = segment.x2 - segment.x1;
            const dy = segment.y2 - segment.y1;
            const length = dx * dx + dy * dy || 1;
            const t = Math.max(0, Math.min(1, ((point.x - segment.x1) * dx + (point.y - segment.y1) * dy) / length));
            const x = segment.x1 + t * dx;
            const y = segment.y1 + t * dy;
            const distance = Math.hypot(point.x - x, point.y - y);
            if (distance < best) { best = distance; nearest = link; }
        }
    }
    return nearest;
}

function toggleFailedLink(link) {
    const id = String(link.id);
    if (S.simulation.failedLinks.has(id)) S.simulation.failedLinks.delete(id);
    else S.simulation.failedLinks.add(id);
    S.simulation.result = buildSimulationResult();
    const status = document.getElementById('advanced-status');
    if (status) status.textContent = S.simulation.failedLinks.size + ' failed link(s), live data unchanged';
    renderEditor();
}

function buildSimulationResult() {
    const result = {};
    for (const link of S.links) {
        if (S.simulation.failedLinks.has(String(link.id))) continue;
        const data = S.telemetry?.[link.id] || {};
        let risk = Number(data.pct ?? data.utilization ?? 0);
        const source = findNodeById(link.srcId);
        const target = findNodeById(link.dstId);
        if (S.links.some(other => S.simulation.failedLinks.has(String(other.id)) &&
            (other.srcId === source?.id || other.dstId === source?.id || other.srcId === target?.id || other.dstId === target?.id))) {
            risk += 35;
        }
        result[link.id] = { risk: Math.min(100, risk) };
    }
    return result;
}

class NetworkAudio {
    async enable() {
        this.context = new AudioContext();
        await this.context.resume();
        this.oscillator = this.context.createOscillator();
        this.gain = this.context.createGain();
        this.oscillator.type = 'sine';
        this.gain.gain.value = 0.0001;
        this.oscillator.connect(this.gain).connect(this.context.destination);
        this.oscillator.start();
    }
    update(score) {
        if (!this.context) return;
        const now = this.context.currentTime;
        this.oscillator.frequency.setTargetAtTime(160 + score * 2.2, now, 0.5);
        this.gain.gain.setTargetAtTime(0.0001 + (100 - score) * 0.00012, now, 0.5);
    }
    disable() {
        if (!this.context) return;
        this.gain.gain.setTargetAtTime(0.0001, this.context.currentTime, 0.2);
    }
}

function updateNetworkAudio() {
    if (!S.audio.enabled || !S.audio.engine) return;
    const values = Object.values(S.telemetry || {});
    const score = values.length
        ? values.reduce((sum, item) => sum + Number(item.health_score ?? (100 - Number(item.pct || 0))), 0) / values.length
        : 100;
    S.audio.engine.update(Math.max(0, Math.min(100, score)));
}

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('advanced-panel');
    const slider = document.getElementById('replay-slider');
    const setStatus = message => { const el = document.getElementById('advanced-status'); if (el) el.textContent = message; };

    document.getElementById('replay-toggle')?.addEventListener('click', () => {
        if (panel) panel.hidden = !panel.hidden;
    });
    document.getElementById('simulation-toggle')?.addEventListener('click', toggleSimulation);
    document.getElementById('live-mode-btn')?.addEventListener('click', setLiveMode);
    document.getElementById('clear-simulation-btn')?.addEventListener('click', () => {
        S.simulation.failedLinks.clear();
        S.simulation.result = null;
        S.simulation.active = false;
        setStatus('Simulation cleared; live data unchanged');
        renderEditor();
    });
    const requestReplay = advancedDebounce(async (timestamp, requestId) => {
        try { if (await loadReplay(timestamp, requestId)) setStatus('Historical RRD data loaded'); }
        catch (error) { setStatus(error.message); }
    }, 300);
    slider?.addEventListener('input', event => {
        if (!S.uris.replay) return;
        const timestamp = Number(event.target.value);
        requestReplay(timestamp, beginReplay(timestamp));
    });
    document.getElementById('audio-toggle')?.addEventListener('click', async event => {
        if (!S.audio.enabled) {
            S.audio.engine = new NetworkAudio();
            await S.audio.engine.enable();
            S.audio.enabled = true;
            event.currentTarget.innerHTML = '<i class="fas fa-volume-up"></i>';
            updateNetworkAudio();
        } else {
            S.audio.engine.disable();
            S.audio.enabled = false;
            event.currentTarget.innerHTML = '<i class="fas fa-volume-mute"></i>';
        }
    });
    S.canvas?.addEventListener('click', event => {
        if (!S.simulation.active) return;
        const link = nearestLinkAt(getCanvasPoint(event));
        if (link) toggleFailedLink(link);
    });

    const animate = () => {
        if (S.simulation.active || Object.keys(S.telemetry || {}).length) {
            renderEditor();
            updateNetworkAudio();
        }
        requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
});
