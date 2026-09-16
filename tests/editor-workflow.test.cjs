const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function setup() {
    const fields = new Map(Object.entries({ 'map-name': 'backbone', 'map-title': 'Backbone', 'map-width': '800', 'map-height': '600' })
        .map(([id, value]) => [id, { value, style: {}, addEventListener() {} }]));
    const notices = [], calls = [], responses = [];
    const state = { nodes: [{ id: 'draft-1', label: 'core', x: 100, y: 100, topologyRole: 'core', meta: { rack: 'A' } }],
        links: [], mapId: null, mapWidth: 800, mapHeight: 600, mapDataLoaded: true,
        undoStack: [], redoStack: [], selectedNodes: [], hasUnsavedChanges: true,
        uris: { map: '/map', maps: '/maps', editor: '/editor' }, editorConfig: {} };
    const context = vm.createContext({
        window: { LLT: { EditorState: state }, location: { href: '' } },
        document: { getElementById: id => fields.get(id) || null, addEventListener() {} }, console,
        LLTLoading: { show() {}, hide() {} }, LLTToast: Object.fromEntries(['info', 'success', 'warning', 'error'].map(key => [key, value => notices.push(value)])),
        getCsrfToken: () => 'token', renderEditor() {}, renderNodesList() {}, renderLinksList() {}, populateNodeProperties() {},
        fetch: async (url, options) => { calls.push({ url, options }); return responses.shift(); },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../resources/js/editor-ui.js'), 'utf8'), context);
    return { state, context, notices, calls, responses, fields, run: code => vm.runInContext(code, context) };
}
const response = (body, ok = true) => ({ ok, json: async () => body });

test('first Save persists the drawn topology and role before redirecting', async () => {
    const h = setup();
    h.responses.push(response({ success: true, map: { id: 7 }, redirect: '/editor/7' }), response({ success: true }));
    await h.run('saveMap()');
    assert.equal(h.calls.length, 2);
    assert.equal(h.calls[1].url, '/maps/7/save');
    const saved = JSON.parse(h.calls[1].options.body);
    assert.equal(saved.nodes[0].id, 'draft-1');
    assert.equal(saved.nodes[0].meta.topology_role, 'core');
    assert.equal(saved.nodes[0].meta.rack, 'A');
    assert.equal(h.context.window.location.href, '/editor/7');
    assert.equal(h.state.hasUnsavedChanges, false);
});

test('failed initial content save retains the draft and created map ID for retry', async () => {
    const h = setup();
    h.responses.push(response({ success: true, map: { id: 7 } }), response({ success: false, message: 'Temporary failure' }, false));
    await h.run('saveMap()');
    assert.equal(h.state.mapId, 7);
    assert.equal(h.state.nodes[0].id, 'draft-1');
    assert.equal(h.state.hasUnsavedChanges, true);
    assert.equal(h.state.saveInProgress, false);
    assert.equal(h.context.window.location.href, '');
});

test('discovery cannot discard unsaved work, run twice concurrently, or mutate replay', async () => {
    const h = setup(); h.state.mapId = 7;
    h.run('autoDiscoverMap()');
    assert.equal(h.calls.length, 0);
    assert.match(h.notices[0], /Save your changes/);
    h.state.hasUnsavedChanges = false; h.state.replay = { active: true };
    h.run('autoDiscoverMap(); saveMap()');
    assert.equal(h.calls.length, 0);
    h.state.replay.active = false;
    h.responses.push(response({ success: true, message: '0 nodes added' }), response({ nodes: [{ id: 1, label: 'core', x: 777, y: 333 }], links: [], options: {} }));
    const pending = h.run('autoDiscoverMap()');
    h.run('autoDiscoverMap()');
    await pending;
    assert.equal(h.calls.filter(call => call.url.endsWith('autodiscover')).length, 1);
    assert.equal(h.state.nodes[0].x, 777);
    assert.equal(h.state.discoveryInProgress, false);
});

test('undo and redo restore layout dimensions with graph positions', () => {
    const h = setup();
    h.run('saveState()');
    h.state.mapWidth = 2000; h.state.mapHeight = 1600; h.state.nodes[0].x = 1800;
    h.run('undo()');
    assert.equal(h.state.mapWidth, 800); assert.equal(h.state.mapHeight, 600); assert.equal(h.state.nodes[0].x, 100);
    h.run('redo()');
    assert.equal(h.state.mapWidth, 2000); assert.equal(h.state.nodes[0].x, 1800);
    assert.equal(h.fields.get('map-width').value, 2000);
    assert.equal(h.state.hasUnsavedChanges, true);
});
