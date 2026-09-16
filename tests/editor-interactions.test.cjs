const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = name => fs.readFileSync(path.join(__dirname, '../resources/js/', name), 'utf8');

function editor() {
    const events = new Map(), frames = [], timers = new Map(), calls = [];
    let nextTimer = 0, resize;
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, {
            value: '', style: {}, disabled: false, textContent: '',
            classList: { toggle() {}, add() {}, remove() {} },
            addEventListener(type, callback) { events.set(id + ':' + type, callback); },
        });
        return elements.get(id);
    };
    const ctx = new Proxy({}, {
        get(target, key) { return target[key] ?? ((...args) => calls.push([key, ...args])); },
    });
    const canvas = Object.assign(element('map-canvas'), {
        width: 800, height: 600,
        parentElement: { clientWidth: 820, clientHeight: 620 },
        getContext: () => ctx,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 }),
    });
    const state = {
        canvas, ctx, mapId: 1, mapWidth: 800, mapHeight: 600,
        nodes: [], links: [], selectedNodes: [], selectedNode: null,
        viewScale: 1, viewOffsetX: 0, viewOffsetY: 0,
        dragOffset: { x: 0, y: 0 }, snapToGrid: false, gridSize: 20,
        editorConfig: { link_style: 'straight' }, undoStack: [], redoStack: [],
        hasUnsavedChanges: false, telemetry: {}, uris: { replay: '/replay' },
        replay: { active: false, timestamp: null, requestId: 0 },
        simulation: { active: false, failedLinks: new Set(), result: null },
        audio: { enabled: false },
    };
    const context = vm.createContext({
        window: { LLT: { EditorState: state }, devicePixelRatio: 2, matchMedia: () => ({ matches: false }) },
        document: {
            getElementById: element, querySelector: () => null,
            querySelectorAll: () => [element('map-width'), element('map-title')],
            addEventListener(type, callback) { events.set('document:' + type, callback); },
        },
        ResizeObserver: class { constructor(callback) { resize = callback; } observe() {} },
        requestAnimationFrame(callback) { frames.push(callback); return frames.length; },
        setTimeout(callback) { timers.set(++nextTimer, callback); return nextTimer; },
        clearTimeout(id) { timers.delete(id); },
        getDefaultNodeStyle: () => ({}), getDefaultLinkStyle: () => ({}),
        populateNodeProperties() {}, renderNodesList() {}, renderLinksList() {},
        updateToolbarState() {}, updateStatusCounts() {},
        saveState() { state.undoStack.push('undo'); },
    });
    vm.runInContext(source('topology-ports.js'), context);
    vm.runInContext(source('editor-canvas.js'), context);
    vm.runInContext(source('editor-advanced.js'), context);
    return { state, context, canvas, calls, elements, element, events, frames, timers, resize: () => resize() };
}

function pointer(x, y, extra = {}) { return { clientX: x, clientY: y, button: 0, ...extra }; }

test('grid drag accumulates small mouse moves and keeps the original grab offset', () => {
    const { state, context } = editor();
    const node = { id: 1, x: 100, y: 100 };
    state.nodes = [node]; state.snapToGrid = true;
    context.handleMouseDown(pointer(104, 104));
    for (let x = 105; x <= 137; x++) context.handleMouseMove(pointer(x, 104));
    assert.equal(node.x, 140);
    assert.equal(node.y, 100);
    assert.equal(state.dragOffset.x, 4);
    assert.equal(state.undoStack.length, 1);
});

test('group drag keeps spacing when a selected node reaches the map edge', () => {
    const { state, context } = editor();
    const a = { id: 1, x: 600, y: 100 }, b = { id: 2, x: 750, y: 140 };
    state.nodes = [a, b]; state.selectedNodes = [a, b]; state.selectionMode = true;
    context.handleMouseDown(pointer(600, 100));
    context.handleMouseMove(pointer(790, 130));
    assert.equal(b.x, 776);
    assert.equal(b.x - a.x, 150);
    assert.equal(b.y - a.y, 40);
    context.handleMouseMove(pointer(610, 100));
    assert.equal(a.x, 610);
    assert.equal(b.x - a.x, 150);
});

test('removing the drag anchor from a selection and an empty marquee clear stale selection', () => {
    const { state, context } = editor();
    const node = { id: 1, x: 100, y: 100 };
    state.nodes = [node]; state.selectedNode = node; state.selectedNodes = [node];
    context.handleMouseDown(pointer(100, 100, { shiftKey: true }));
    assert.equal(state.isDragging, false);
    assert.equal(state.selectedNode, null);
    state.selectedNode = node; state.selectionMode = true;
    context.handleMouseDown(pointer(400, 400));
    context.handleMouseUp(pointer(450, 450));
    assert.equal(state.selectedNode, null);
    assert.equal(state.selectedNodes.length, 0);
});

test('ResizeObserver redraws the resized canvas with the original map dimensions', () => {
    const { state, context, canvas, frames, resize, calls } = editor();
    context.initCanvas();
    calls.length = 0;
    canvas.parentElement.clientWidth = 420;
    canvas.parentElement.clientHeight = 320;
    resize(); resize();
    assert.equal(frames.length, 1);
    frames.shift()();
    assert.equal(canvas.width, 800);
    assert.equal(canvas.height, 600);
    assert.equal(state.mapWidth, 800);
    assert.equal(state.mapHeight, 600);
    assert.ok(calls.some(([name]) => name === 'clearRect'));
    assert.ok(calls.some(([name]) => name === 'restore'));
});

test('simulation picks the visible Bezier curve instead of its straight control chords', () => {
    const { state, context, calls } = editor();
    state.nodes = [{ id: 1, x: 100, y: 500 }, { id: 2, x: 700, y: 500 }];
    const link = { id: 'curve', srcId: 1, dstId: 2, style: { via_style: 'curved', via_points: [{ x: 400, y: 100 }] } };
    state.links = [link];
    context.drawLink(link);
    const start = calls.find(([name]) => name === 'moveTo').slice(1);
    const curve = calls.find(([name]) => name === 'bezierCurveTo').slice(1);
    const midpoint = {
        x: (start[0] + 3 * curve[0] + 3 * curve[2] + curve[4]) / 8,
        y: (start[1] + 3 * curve[1] + 3 * curve[3] + curve[5]) / 8,
    };
    assert.equal(context.nearestLinkAt(midpoint), link);
    assert.equal(context.nearestLinkAt({ x: (start[0] + 400) / 2, y: (start[1] + 100) / 2 }), null);
});

test('link picking and port labels use CSS screen scale, independent of DPR and world zoom', () => {
    const { state, context, canvas, calls } = editor();
    canvas.getBoundingClientRect = () => ({ width: 400, height: 300, left: 0, top: 0 });
    state.links = [{ _segs: [{ x1: 0, y1: 100, x2: 100, y2: 100 }] }];
    assert.equal(context.nearestLinkAt({ x: 40, y: 125 }), state.links[0]);
    state.nodes = [{ id: 1, x: 100, y: 100 }, { id: 2, x: 300, y: 100 }];
    state.links = [{ srcId: 1, dstId: 2, sourcePortName: 'eth-selected', destinationPortName: 'eth-other' }];
    state.viewScale = 2;
    context.renderEditor();
    assert.ok(!calls.some(([name, label]) => name === 'fillText' && String(label).startsWith('eth-')));
    calls.length = 0;
    state.selectedNode = state.nodes[0];
    context.renderEditor();
    assert.ok(calls.some(([name, label]) => name === 'fillText' && label === 'eth-selected'));
    assert.ok(!calls.some(([name, label]) => name === 'fillText' && label === 'eth-other'));
});

test('angled editor routes leave assigned port sides along orthogonal segments', () => {
    const { state, context } = editor();
    state.nodes = [{ id: 1, x: 100, y: 100 }, { id: 2, x: 500, y: 400 }];
    const link = { id: 1, srcId: 1, dstId: 2, style: { via_style: 'angled', via_points: [{ x: 200, y: 100 }, { x: 200, y: 400 }] } };
    state.links = [link];
    context.renderEditor();
    for (const segment of link._segs) assert.ok(segment.x1 === segment.x2 || segment.y1 === segment.y2);
});

test('replay accepts keyed snapshots and nested dimensions while preserving node and port metadata', () => {
    const { state, context } = editor();
    context.applyReplayMap({
        map: { width: 1400, height: 900 },
        nodes: { 11: { id: 11, x: 200, y: 300, meta: { device_type: 'router', topology_role: 'core', interface_id: 21 } } },
        links: { 33: { id: 33, src_node_id: 11, dst_node_id: 12, source_port_name: 'eth1', destination_port_name: 'eth2' } },
    });
    assert.equal(state.nodes[0].deviceType, 'router');
    assert.equal(state.nodes[0].topologyRole, 'core');
    assert.equal(state.nodes[0].meta.interface_id, 21);
    assert.equal(state.links[0].srcId, 11);
    assert.equal(state.links[0].sourcePortName, 'eth1');
    assert.equal(state.mapWidth, 1400);
    assert.equal(state.mapHeight, 900);
    context.applyReplayMap({ options: { width: 1000, height: 700 } });
    assert.equal(state.mapWidth, 1000);
    assert.equal(state.mapHeight, 700);
});

test('returning live restores unsaved graph, dimensions, view, selection, form and undo history', async () => {
    const { state, context, element } = editor();
    state.nodes = [{ id: 'draft', label: 'unsaved', x: 300, y: 200, meta: { keep: true } }];
    state.selectedNode = state.nodes[0]; state.selectedNodes = [state.nodes[0]];
    state.undoStack = ['before']; state.redoStack = ['after']; state.hasUnsavedChanges = true;
    state.viewScale = 1.5; state.viewOffsetX = 13;
    element('map-title').value = 'Draft title';
    element('map-title').disabled = true;
    context.fetch = async () => ({ ok: true, json: async () => ({ map: { options: { width: 1200, height: 500 }, nodes: [] } }) });
    assert.equal(await context.loadReplay(100), true);
    assert.equal(state.mapWidth, 1200);
    assert.equal(element('map-width').disabled, true);
    state.viewScale = 3; element('map-title').value = 'Historical';
    context.setLiveMode();
    assert.equal(state.nodes[0].label, 'unsaved');
    assert.equal(state.selectedNode, state.nodes[0]);
    assert.equal(state.selectedNodes[0], state.nodes[0]);
    assert.equal(state.undoStack[0], 'before'); assert.equal(state.redoStack[0], 'after');
    assert.equal(state.hasUnsavedChanges, true);
    assert.equal(state.mapWidth, 800); assert.equal(state.mapHeight, 600);
    assert.equal(state.viewScale, 1.5); assert.equal(state.viewOffsetX, 13);
    assert.equal(element('map-title').value, 'Draft title');
    assert.equal(element('map-title').disabled, true);
    assert.equal(element('map-width').disabled, false);
});

test('stale replay responses cannot replace newer timestamps or a restored live draft', async () => {
    const { state, context } = editor();
    const pending = [];
    context.fetch = () => new Promise(resolve => pending.push(resolve));
    const first = context.loadReplay(100), second = context.loadReplay(200);
    pending[1]({ ok: true, json: async () => ({ map: { nodes: [{ id: 'new', x: 100, y: 100 }] } }) });
    assert.equal(await second, true);
    pending[0]({ ok: true, json: async () => ({ map: { nodes: [{ id: 'old' }] } }) });
    assert.equal(await first, false);
    assert.equal(state.nodes[0].id, 'new');
    const third = context.loadReplay(300);
    context.setLiveMode();
    pending[2]({ ok: true, json: async () => ({ map: { nodes: [{ id: 'late' }] } }) });
    assert.equal(await third, false);
    assert.equal(state.nodes.length, 0);
    assert.equal(state.replay.active, false);
});

test('returning live cancels debounced replay before the request starts', async () => {
    const { state, context, events, timers } = editor();
    let fetches = 0;
    context.fetch = async () => { fetches++; throw new Error('Unexpected request'); };
    events.get('document:DOMContentLoaded')();
    events.get('replay-slider:input')({ target: { value: '100' } });
    assert.equal(state.replay.loading, true);
    context.setLiveMode();
    for (const callback of timers.values()) await callback();
    assert.equal(fetches, 0);
    assert.equal(state.replay.active, false);
});

test('replay failure restores the draft and permits editing again', async () => {
    const { state, context } = editor();
    state.nodes = [{ id: 'draft' }];
    context.fetch = async () => ({ ok: false, status: 503 });
    await assert.rejects(context.loadReplay(100), /HTTP 503/);
    assert.equal(state.nodes[0].id, 'draft');
    assert.equal(state.replay.active, false);
    assert.equal(state.replay.loading, false);
});

test('replay allows node inspection while blocking dragging and link creation', () => {
    const { state, context } = editor();
    const node = { id: 1, x: 100, y: 100 };
    state.nodes = [node]; state.replay.active = true; state.linkMode = true;
    context.handleMouseDown(pointer(100, 100));
    context.handleMouseMove(pointer(200, 200));
    assert.equal(state.selectedNode, node);
    assert.equal(state.isDragging, false);
    assert.equal(node.x, 100);
    assert.equal(state.links.length, 0);
    assert.equal(state.undoStack.length, 0);
});

test('Fit frames node labels and routed links without moving or resizing the map', () => {
    const { state, context } = editor();
    state.nodes = [{ id: 1, label: 'Long core device hostname', x: 8000, y: 100 }, { id: 2, x: 12000, y: 400 }];
    const via = [{ x: 10000, y: -700 }, { x: 11000, y: -700 }];
    state.links = [{ srcId: 1, dstId: 2, style: { via_style: 'angled', via_points: via } }];
    const positions = state.nodes.map(node => ({ x: node.x, y: node.y }));
    assert.equal(context.fitEditorTopology(), true);
    assert.ok(state.viewScale < 0.25, 'oversized legacy coordinates fit below the normal zoom minimum');
    for (const point of [...positions, ...via, { x: 8000 - 'Long core device hostname'.length * 3.5 - 8, y: 66 }]) {
        const x = point.x * state.viewScale + state.viewOffsetX;
        const y = point.y * state.viewScale + state.viewOffsetY;
        assert.ok(x >= 0 && x <= 800 && y >= 0 && y <= 600, `${JSON.stringify(point)} is visible`);
    }
    assert.deepEqual(state.nodes.map(node => ({ x: node.x, y: node.y })), positions);
    assert.equal(state.mapWidth, 800); assert.equal(state.mapHeight, 600);
    assert.equal(state.hasUnsavedChanges, false);
});

test('Fit handles an empty map and limits enlargement for a single node', () => {
    const { state, context } = editor();
    assert.equal(context.fitEditorTopology(), false);
    state.nodes = [{ id: 1, x: 100, y: 100 }];
    assert.equal(context.fitEditorTopology(), true);
    assert.ok(state.viewScale <= 4);
    assert.equal(state.nodes[0].x * state.viewScale + state.viewOffsetX, 400);
    assert.equal(state.nodes[0].y * state.viewScale + state.viewOffsetY, 300);
});
