const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../resources/js/editor-nodes.js'), 'utf8');

test('editor layered layout keeps a wide fanout and separate components within canvas bounds', () => {
    const nodes = [{ id: 'core', label: 'core-01', deviceType: 'router', x: 0, y: 0 }];
    const links = [];
    for (let index = 1; index <= 100; index++) {
        nodes.push({ id: `access-${index}`, label: `access-${index}`, x: 0, y: 0 });
        links.push({ srcId: 'core', dstId: `access-${index}` });
    }
    for (let index = 1; index <= 20; index++) {
        nodes.push({ id: `isolated-${index}`, label: `isolated-${index}`, x: 0, y: 0 });
    }
    const state = { nodes, links, mapWidth: 800, mapHeight: 600 };
    const context = vm.createContext({
        window: { LLT: { EditorState: state } },
        document: { getElementById() { return null; } },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../resources/js/topology-layout.js'), 'utf8'), context);
    vm.runInContext(source, context);
    vm.runInContext('runLayeredNetworkLayout()', context);
    assert.ok(state.mapWidth <= 4096 && state.mapHeight <= 4096);
    assert.ok(new Set(nodes.slice(1, 101).map(node => node.y)).size > 2);
    for (const [index, node] of nodes.entries()) {
        assert.ok(node.x >= 0 && node.x <= state.mapWidth && node.y >= 0 && node.y <= state.mapHeight,
            `${node.id} stays inside the saved map`);
        for (const other of nodes.slice(index + 1)) {
            assert.ok(Math.abs(node.x - other.x) >= 76 || Math.abs(node.y - other.y) >= 46,
                `${node.id} and ${other.id} do not overlap`);
        }
    }
});
