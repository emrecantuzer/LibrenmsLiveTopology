(function (root) {
    'use strict';

    // A deterministic physical-topology layout. Parallel circuits count as one
    // neighbour for ranking, but remain separate, individually routed links.
    const sourceId = link => link.srcId ?? link.src ?? link.source;
    const targetId = link => link.dstId ?? link.dst ?? link.target;
    const compare = (a, b) => String(a).localeCompare(String(b), 'en', { numeric: true });

    function roleOf(node) {
        const explicit = node.topologyRole || node.meta?.topology_role || node.role;
        if (['core', 'distribution', 'access'].includes(explicit)) return explicit;
        const name = String(node.label || node.deviceName || '').toLowerCase();
        if (/(^|[\s_.-])(core|spine)([\s_.-]|\d|$)/.test(name)) return 'core';
        if (/(^|[\s_.-])(distribution|dist|aggregation|agg)([\s_.-]|\d|$)/.test(name)) return 'distribution';
        if (/(^|[\s_.-])(access|leaf|edge|server)([\s_.-]|\d|$)/.test(name)) return 'access';
        return 'auto';
    }

    function rankedComponents(nodes, links) {
        const byId = new Map(nodes.map((node, i) => [String(node.id), i]));
        const neighbours = nodes.map(() => new Set());
        for (const link of links) {
            const a = byId.get(String(sourceId(link))), b = byId.get(String(targetId(link)));
            if (a === undefined || b === undefined || a === b) continue;
            neighbours[a].add(b); neighbours[b].add(a);
        }
        const stable = (a, b) => compare(nodes[a].id, nodes[b].id);
        const adjacency = neighbours.map(group => [...group].sort(stable));
        const roles = nodes.map(roleOf), visited = new Set(), components = [];
        for (const start of nodes.map((_, i) => i).sort(stable)) {
            if (visited.has(start)) continue;
            const component = [start]; visited.add(start);
            for (let head = 0; head < component.length; head++) {
                for (const neighbour of adjacency[component[head]]) {
                    if (!visited.has(neighbour)) { visited.add(neighbour); component.push(neighbour); }
                }
            }
            let roots = component.filter(i => roles[i] === 'core');
            if (!roots.length) {
                // No role metadata: choose the best-connected transit device.
                // A router icon alone does not make an edge router the core.
                let candidates = component.filter(i => roles[i] !== 'access');
                if (!candidates.length) candidates = component;
                roots = [candidates.slice().sort((a, b) => adjacency[b].length - adjacency[a].length || stable(a, b))[0]];
            }
            const rank = new Map(roots.map(i => [i, 0])), queue = roots.slice().sort(stable);
            for (let head = 0; head < queue.length; head++) {
                const current = queue[head];
                for (const next of adjacency[current]) {
                    if (rank.has(next)) continue;
                    rank.set(next, rank.get(current) + 1); queue.push(next);
                }
            }
            // Only add a distribution tier when one actually exists. Two-tier
            // collapsed-core and spine/leaf networks should remain two-tier.
            const hasDistribution = component.some(i => roles[i] === 'distribution' && !roots.includes(i));
            for (const i of component) {
                if (!roots.includes(i) && roles[i] === 'access' && hasDistribution) rank.set(i, Math.max(2, rank.get(i)));
            }
            const layers = [];
            for (const i of component) { const level = rank.get(i); (layers[level] ||= []).push(i); }
            for (const layer of layers) if (layer) layer.sort(stable);
            // Barycentre sweeps place shared downstream neighbours under their
            // uplinks, including dual-homed access devices and redundant cores.
            for (let pass = 0; pass < 6; pass++) {
                const order = new Map();
                layers.forEach(layer => layer?.forEach((i, p) => order.set(i, (p + .5) / layer.length)));
                const depths = layers.map((_, i) => i);
                if (pass % 2) depths.reverse();
                for (const depth of depths) {
                    const layer = layers[depth]; if (!layer) continue;
                    const score = i => {
                        const adjacent = adjacency[i].filter(n => pass % 2 ? rank.get(n) > depth : rank.get(n) < depth);
                        return adjacent.length ? adjacent.reduce((sum, n) => sum + order.get(n), 0) / adjacent.length : order.get(i);
                    };
                    layer.sort((a, b) => score(a) - score(b) || stable(a, b));
                    layer.forEach((i, p) => order.set(i, (p + .5) / layer.length));
                }
            }
            components.push({ nodes: component, layers: layers.filter(Boolean) });
        }
        return { components: components.sort((a, b) => b.nodes.length - a.nodes.length || stable(a.nodes[0], b.nodes[0])), byId };
    }

    class Heap {
        constructor() { this.items = []; }
        push(item) {
            const a = this.items; let i = a.length; a.push(item);
            while (i > 0) { const p = (i - 1) >> 1; if (a[p].f <= item.f) break; a[i] = a[p]; i = p; }
            a[i] = item;
        }
        pop() {
            const a = this.items, first = a[0], last = a.pop();
            if (a.length) {
                let i = 0;
                while (i * 2 + 1 < a.length) {
                    let c = i * 2 + 1; if (c + 1 < a.length && a[c + 1].f < a[c].f) c++;
                    if (a[c].f >= last.f) break; a[i] = a[c]; i = c;
                }
                a[i] = last;
            }
            return first;
        }
    }

    function routeLinks(nodes, links, slots, byId, cols, rows, spacingX, spacingY, margin) {
        const gw = cols * 2 + 1, gh = rows * 2 + 1;
        const vertex = p => (p.row * 2 + 1) * gw + p.col * 2 + 1;
        const blocked = new Set(slots.map(vertex)), usage = new Map();
        const edgeKey = (a, b) => a < b ? a + ':' + b : b + ':' + a;
        const point = v => ({ x: margin + ((v % gw) - 1) * spacingX / 2, y: margin + (Math.floor(v / gw) - 1) * spacingY / 2 });
        const routes = [];
        const ordered = links.map((link, index) => ({ link, index })).sort((a, b) =>
            compare(sourceId(a.link), sourceId(b.link)) || compare(targetId(a.link), targetId(b.link)) || compare(a.link.id ?? a.index, b.link.id ?? b.index));
        for (const { link, index } of ordered) {
            const ai = byId.get(String(sourceId(link))), bi = byId.get(String(targetId(link)));
            if (ai === undefined || bi === undefined || ai === bi) continue;
            const start = vertex(slots[ai]), goal = vertex(slots[bi]);
            const heuristic = v => Math.abs(v % gw - goal % gw) * spacingX / 2 + Math.abs(Math.floor(v / gw) - Math.floor(goal / gw)) * spacingY / 2;
            const open = new Heap(), distance = new Map(), previous = new Map();
            // State includes the entering direction so bend penalties do not
            // discard a better route through the same mesh intersection.
            open.push({ v: start, dir: 0, g: 0, f: heuristic(start), key: start * 3 });
            distance.set(start * 3, 0);
            let end = null;
            while (open.items.length) {
                const current = open.pop();
                if (current.g !== distance.get(current.key)) continue;
                if (current.v === goal) { end = current.key; break; }
                const x = current.v % gw, y = Math.floor(current.v / gw);
                const next = [];
                if (y + 1 < gh) next.push([current.v + gw, 2, spacingY / 2]);
                if (x + 1 < gw) next.push([current.v + 1, 1, spacingX / 2]);
                if (x > 0) next.push([current.v - 1, 1, spacingX / 2]);
                if (y > 0) next.push([current.v - gw, 2, spacingY / 2]);
                for (const [v, dir, step] of next) {
                    if (blocked.has(v) && v !== goal && v !== start) continue;
                    const key = v * 3 + dir;
                    const g = current.g + step + (current.dir && current.dir !== dir ? 30 : 0)
                        + Math.min(60, (usage.get(edgeKey(current.v, v)) || 0) * 6);
                    if (g >= (distance.get(key) ?? Infinity)) continue;
                    distance.set(key, g); previous.set(key, current.key);
                    open.push({ v, dir, key, g, f: g + heuristic(v) });
                }
            }
            if (end === null) continue;
            const vertices = [];
            for (let key = end; key !== undefined; key = previous.get(key)) vertices.push(Math.floor(key / 3));
            vertices.reverse();
            for (let i = 1; i < vertices.length; i++) {
                const key = edgeKey(vertices[i - 1], vertices[i]); usage.set(key, (usage.get(key) || 0) + 1);
            }
            const points = vertices.map(point);
            // Keep the first/last corridor point for perpendicular port exits.
            const simplified = points.filter((p, i) => i <= 1 || i >= points.length - 2 ||
                (points[i - 1].x !== points[i + 1].x && points[i - 1].y !== points[i + 1].y));
            routes.push({ id: link.id, index, via_style: 'angled', via_points: simplified.slice(1, -1) });
        }
        return routes;
    }

    function layout(nodes, links, options = {}) {
        nodes = nodes || []; links = links || [];
        const maxDimension = options.maxDimension || 4096;
        const spacingX = Math.max(160, options.nodeSpacing || 180), spacingY = Math.max(130, options.layerSpacing || 156);
        const margin = Math.max(100, spacingX / 2 + 10, spacingY / 2 + 10);
        const maxCols = Math.floor((maxDimension - margin * 2) / spacingX) + 1;
        const maxRows = Math.floor((maxDimension - margin * 2) / spacingY) + 1;
        const { components, byId } = rankedComponents(nodes, links);
        const initialCols = Math.min(maxCols, Math.max(4, Math.ceil(Math.sqrt(nodes.length * spacingY / spacingX * 1.5))));
        let packed = null;
        for (let cols = initialCols; cols <= maxCols; cols++) {
            const occupied = Array.from({ length: maxRows }, () => new Uint8Array(cols));
            const slots = new Array(nodes.length); let usedRows = 0, failed = false;
            for (const component of components) {
                const bands = component.layers.flatMap(layer => {
                    const rows = []; for (let i = 0; i < layer.length; i += cols) rows.push(layer.slice(i, i + cols)); return rows;
                });
                const width = Math.max(1, ...bands.map(band => band.length)), height = bands.length;
                let origin = null;
                for (let y = 0; y <= maxRows - height && !origin; y++) {
                    for (let x = 0; x <= cols - width && !origin; x++) {
                        let free = true;
                        for (let r = y; r < y + height && free; r++) for (let c = x; c < x + width; c++) if (occupied[r][c]) { free = false; break; }
                        if (free) origin = { x, y };
                    }
                }
                if (!origin) { failed = true; break; }
                for (let r = origin.y; r < origin.y + height; r++) occupied[r].fill(1, origin.x, origin.x + width);
                bands.forEach((band, row) => band.forEach((i, col) => {
                    slots[i] = { col: origin.x + Math.floor((width - band.length) / 2) + col, row: origin.y + row };
                }));
                usedRows = Math.max(usedRows, origin.y + height);
            }
            if (!failed) { packed = { cols, rows: usedRows, slots }; break; }
        }
        if (!packed) return { overflow: true, positions: [], routes: [], width: maxDimension, height: maxDimension };
        const { cols, rows, slots } = packed;
        const positions = nodes.map((node, i) => ({ id: node.id, x: margin + slots[i].col * spacingX, y: margin + slots[i].row * spacingY }));
        return {
            overflow: false,
            width: Math.max(800, margin * 2 + Math.max(0, cols - 1) * spacingX),
            height: Math.max(600, margin * 2 + Math.max(0, rows - 1) * spacingY),
            positions,
            routes: routeLinks(nodes, links, slots, byId, cols, rows, spacingX, spacingY, margin),
        };
    }

    root.LLTTopologyLayout = { layout, roleOf };
})(typeof window !== 'undefined' ? window : globalThis);
