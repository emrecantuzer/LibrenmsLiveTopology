(function (root) {
    'use strict';

    function build(nodes, links, options = {}) {
        const nodeId = options.nodeId || (node => node.id);
        const nodeX = options.nodeX || (node => Number(node.x) || 0);
        const nodeY = options.nodeY || (node => Number(node.y) || 0);
        const sourceId = options.sourceId || (link => link.srcId ?? link.src ?? link.source);
        const targetId = options.targetId || (link => link.dstId ?? link.dst ?? link.target);
        const sourcePort = options.sourcePort || (link => link.portA ?? link.port_id_a);
        const targetPort = options.targetPort || (link => link.portB ?? link.port_id_b);
        const sourceLabel = options.sourceLabel || (link => link.sourcePortName ?? link.source_port_name);
        const targetLabel = options.targetLabel || (link => link.destinationPortName ?? link.destination_port_name);
        const halfSize = options.halfSize || (() => ({ width: 18, height: 15 }));
        const nodeMap = new Map((nodes || []).map(node => [String(nodeId(node)), node]));
        const requests = [];

        function sideToward(from, to) {
            const dx = nodeX(to) - nodeX(from);
            const dy = nodeY(to) - nodeY(from);
            if (Math.abs(dx) >= Math.abs(dy)) return dx >= 0 ? 'right' : 'left';
            return dy >= 0 ? 'bottom' : 'top';
        }

        for (const [linkIndex, link] of (links || []).entries()) {
            const sourceNode = nodeMap.get(String(sourceId(link)));
            const targetNode = nodeMap.get(String(targetId(link)));
            if (!sourceNode || !targetNode) continue;
            const via = (link.style?.via_points || []).filter(p => Number.isFinite(Number(p.x)) && Number.isFinite(Number(p.y)));
            const first = via.find(p => Math.hypot(Number(p.x) - nodeX(sourceNode), Number(p.y) - nodeY(sourceNode)) > .01);
            const last = via.slice().reverse().find(p => Math.hypot(Number(p.x) - nodeX(targetNode), Number(p.y) - nodeY(targetNode)) > .01);
            // VIA geometry determines the exit side; the remote device may be
            // on the opposite side of a manually routed or redundant circuit.
            const sourceToward = first || targetNode, targetToward = last || sourceNode;
            requests.push({ link, linkIndex, end: 'source', node: sourceNode, toward: sourceToward, side: sideToward(sourceNode, sourceToward), port: sourcePort(link), label: sourceLabel(link) });
            requests.push({ link, linkIndex, end: 'target', node: targetNode, toward: targetToward, side: sideToward(targetNode, targetToward), port: targetPort(link), label: targetLabel(link) });
        }

        const groups = new Map();
        for (const request of requests) {
            const key = String(nodeId(request.node)) + '|' + request.side;
            if (!groups.has(key)) groups.set(key, []);
            const entries = groups.get(key);
            const portKey = String(request.port ?? request.label ?? ('link:' + (request.link.id ?? request.linkIndex) + ':' + request.end));
            let entry = entries.find(candidate => candidate.portKey === portKey);
            if (!entry) {
                entry = { portKey, node: request.node, side: request.side, port: request.port, label: request.label, requests: [] };
                entries.push(entry);
            }
            entry.requests.push(request);
        }

        const sourceAnchors = new WeakMap();
        const targetAnchors = new WeakMap();
        const nodeAnchors = new Map();
        for (const entries of groups.values()) {
            const order = entry => {
                const point = entry.requests[0].toward;
                return entry.side === 'left' || entry.side === 'right' ? nodeY(point) : nodeX(point);
            };
            entries.sort((a, b) => order(a) - order(b) || String(a.label ?? a.port ?? a.portKey).localeCompare(String(b.label ?? b.port ?? b.portKey), 'en', { numeric: true }));
            entries.forEach((entry, index) => {
                const size = halfSize(entry.node);
                const fraction = (index + 1) / (entries.length + 1);
                const x = nodeX(entry.node);
                const y = nodeY(entry.node);
                const anchor = {
                    node: entry.node,
                    side: entry.side,
                    port: entry.port,
                    label: entry.label || (entry.port ? 'Port ' + entry.port : 'Port'),
                    x: entry.side === 'left' ? x - size.width : entry.side === 'right' ? x + size.width : x - size.width + size.width * 2 * fraction,
                    y: entry.side === 'top' ? y - size.height : entry.side === 'bottom' ? y + size.height : y - size.height + size.height * 2 * fraction,
                };
                const key = String(nodeId(entry.node));
                if (!nodeAnchors.has(key)) nodeAnchors.set(key, []);
                nodeAnchors.get(key).push(anchor);
                for (const request of entry.requests) {
                    (request.end === 'source' ? sourceAnchors : targetAnchors).set(request.link, anchor);
                }
            });
        }

        return {
            sourceFor: link => sourceAnchors.get(link) || null,
            targetFor: link => targetAnchors.get(link) || null,
            forNode: node => nodeAnchors.get(String(nodeId(node))) || [],
        };
    }

    function routePoints(link, source, target, viaPoints = link.style?.via_points || []) {
        const via = viaPoints.map(p => ({ x: Number(p.x), y: Number(p.y) }));
        const points = [{ x: source.x, y: source.y }];
        if (link.style?.via_style === 'angled' && via.length) {
            const first = via[0];
            points.push(source.side === 'left' || source.side === 'right'
                ? { x: first.x, y: source.y } : { x: source.x, y: first.y });
            points.push(...via);
            const last = via[via.length - 1];
            points.push(target.side === 'left' || target.side === 'right'
                ? { x: last.x, y: target.y } : { x: target.x, y: last.y });
        } else points.push(...via);
        points.push({ x: target.x, y: target.y });
        return points.filter((p, i) => !i || p.x !== points[i - 1].x || p.y !== points[i - 1].y);
    }

    root.LLTPortAnchors = { build, routePoints };
})(window);
