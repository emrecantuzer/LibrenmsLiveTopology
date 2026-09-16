const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const context = vm.createContext({ window: {} });
for (const file of ['topology-layout.js', 'topology-ports.js']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../resources/js', file), 'utf8'), context);
}
const { layout } = context.window.LLTTopologyLayout;
const ports = context.window.LLTPortAnchors;

function star(count) {
    return { nodes: Array.from({ length: count }, (_, id) => ({ id, label: id ? 'access-' + id : 'core-01' })),
        links: Array.from({ length: count - 1 }, (_, id) => ({ id, src: 0, dst: id + 1 })) };
}

function crosses(a, b, node) {
    let lo = 0, hi = 1;
    for (const [axis, half] of [['x', 38], ['y', 23]]) {
        const delta = b[axis] - a[axis], min = node[axis] - half, max = node[axis] + half;
        if (Math.abs(delta) < 1e-9) { if (a[axis] < min || a[axis] > max) return false; }
        else {
            let t0 = (min - a[axis]) / delta, t1 = (max - a[axis]) / delta;
            if (t0 > t1) [t0, t1] = [t1, t0];
            lo = Math.max(lo, t0); hi = Math.min(hi, t1);
            if (lo > hi) return false;
        }
    }
    return true;
}

test('20, 101 and 500 devices fit without overlapping cards or routes through unrelated cards', () => {
    for (const count of [20, 101, 500]) {
        const { nodes, links } = star(count), result = layout(nodes, links);
        assert.equal(result.overflow, false);
        assert.ok(result.width <= 4096 && result.height <= 4096);
        result.positions.forEach((position, i) => Object.assign(nodes[i], position));
        result.routes.forEach(route => { links[route.index].style = route; });
        assert.equal(result.routes.length, links.length);
        for (const [i, node] of nodes.entries()) {
            assert.ok(node.x >= 38 && node.x + 38 <= result.width && node.y >= 23 && node.y + 23 <= result.height);
            for (const other of nodes.slice(i + 1)) assert.ok(Math.abs(node.x - other.x) >= 76 || Math.abs(node.y - other.y) >= 46);
        }
        const anchors = ports.build(nodes, links, { halfSize: () => ({ width: 38, height: 23 }) });
        for (const link of links) {
            const points = ports.routePoints(link, anchors.sourceFor(link), anchors.targetFor(link));
            for (const point of points) assert.ok(point.x >= 0 && point.y >= 0 && point.x <= result.width && point.y <= result.height);
            for (const node of nodes) {
                if (node.id === link.src || node.id === link.dst) continue;
                assert.ok(!points.some((point, i) => i && crosses(points[i - 1], point, node)), `${count}: ${link.src}->${link.dst} crosses ${node.id}`);
            }
        }
    }
});

test('role metadata keeps a redundant core pair together and supports collapsed core', () => {
    const nodes = [{ id: 1, label: '10.0.0.1', meta: { topology_role: 'core' } },
        { id: 2, label: '10.0.0.2', meta: { topology_role: 'core' } },
        { id: 3, label: 'dist-01' }, { id: 4, label: 'access-01' }];
    const links = [{ src: 1, dst: 2 }, { src: 1, dst: 3 }, { src: 2, dst: 3 }, { src: 3, dst: 4 }];
    const p = layout(nodes, links).positions;
    assert.equal(p[0].y, p[1].y); assert.ok(p[2].y > p[0].y && p[3].y > p[2].y);
    const collapsed = layout([nodes[0], nodes[3]], [{ src: 1, dst: 4 }]);
    assert.equal(collapsed.positions[1].y - collapsed.positions[0].y, 156);
});

test('parallel circuit count and input order do not change device ranking or placement', () => {
    const nodes = Array.from({ length: 5 }, (_, id) => ({ id, label: '10.0.0.' + id }));
    const links = [{ id: 1, src: 0, dst: 1 }, { id: 2, src: 0, dst: 2 }, { id: 3, src: 0, dst: 3 }, { id: 4, src: 1, dst: 4 }];
    const original = layout(nodes, links);
    const parallel = layout(nodes, links.concat(Array.from({ length: 20 }, (_, id) => ({ id: 10 + id, src: 1, dst: 4 }))));
    assert.deepEqual(original.positions, parallel.positions);
    const reversed = layout(nodes.slice().reverse(), links.slice().reverse()).positions.slice().sort((a, b) => a.id - b.id);
    assert.deepEqual(original.positions, reversed);
    assert.equal(parallel.routes.length, 24);
});

test('unrepresentable topology is rejected without mutating existing positions or routes', () => {
    const fixture = star(2000), before = JSON.stringify(fixture);
    assert.equal(layout(fixture.nodes, fixture.links).overflow, true);
    assert.equal(JSON.stringify(fixture), before);
});

test('VIA path chooses the device exit side and anonymous parallel links get separate anchors', () => {
    const nodes = [{ id: 1, x: 100, y: 100 }, { id: 2, x: 300, y: 100 }];
    const links = [1, 2].map(id => ({ id, src: 1, dst: 2, style: { via_style: 'angled', via_points: [{ x: 100, y: 200 }, { x: 300, y: 200 }] } }));
    const a = ports.build(nodes, links);
    assert.equal(a.sourceFor(links[0]).side, 'bottom');
    assert.equal(a.targetFor(links[0]).side, 'bottom');
    assert.notEqual(a.sourceFor(links[0]).x, a.sourceFor(links[1]).x);
    const points = ports.routePoints(links[0], a.sourceFor(links[0]), a.targetFor(links[0]));
    assert.equal(points[0].x, points[1].x);
});
