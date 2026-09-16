/**
 * LibreLiveTopology editor — canvas rendering and interaction.
 *
 * Renders the map onto the <canvas> (nodes, links, grid, minimap), handles
 * zoom / pan / snapping, hit-testing, and all mouse input on the canvas,
 * including starting a link between two nodes in link mode.
 *
 * Pure function declarations here are global and callable from the other
 * editor modules; all shared mutable state is read/written through the
 * `S` alias for `window.LLT.EditorState` (defined in editor-state.js, which
 * must load before this file).
 */
var S = window.LLT.EditorState;
const MIN_ZOOM = 0.25;
const MAX_ZOOM = 4;
let editorPortAnchors = null;

function initCanvas() {
    S.canvas = document.getElementById('map-canvas');
    if (!S.canvas) return;
    S.ctx = S.canvas.getContext('2d');
    // Capture the original map (world) dimensions before fitCanvasToWrap
    // changes the canvas buffer to match the display.
    S.mapWidth = S.canvas.width;
    S.mapHeight = S.canvas.height;

    S.canvas.addEventListener('mousedown', handleMouseDown);
    S.canvas.addEventListener('mousemove', handleMouseMove);
    S.canvas.addEventListener('mouseup', handleMouseUp);
    S.canvas.addEventListener('mouseleave', handleMouseUp);
    S.canvas.addEventListener('wheel', handleWheel, { passive: false });
    S.canvas.addEventListener('contextmenu', e => e.preventDefault());

    // Fit canvas to its container while preserving the map's aspect ratio.
    // CSS alone can't do "largest box with aspect ratio X inside container"
    // for a <canvas> (not a replaced element, ignores object-fit), so a
    // ResizeObserver sets the display width/height on resize.
    const wrap = S.canvas.parentElement;
    function fitCanvasToWrap() {
        if (!wrap || !S.canvas) return;
        const pad = 20; // .editor-canvas-wrap padding (10px each side)
        const availW = wrap.clientWidth - pad;
        const availH = wrap.clientHeight - pad;
        if (availW <= 0 || availH <= 0) return;
        const bufRatio = S.mapWidth / S.mapHeight;
        let dispW = availW;
        let dispH = dispW / bufRatio;
        if (dispH > availH) { dispH = availH; dispW = dispH * bufRatio; }
        dispW = Math.round(dispW);
        dispH = Math.round(dispH);
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        // Set the canvas buffer to display size × DPR for crisp text.
        // The world coordinate system stays at S.mapWidth × S.mapHeight;
        // renderEditor scales the context to map world→buffer.
        const bufferWidth = Math.round(dispW * dpr);
        const bufferHeight = Math.round(dispH * dpr);
        if (S.canvas.width !== bufferWidth) S.canvas.width = bufferWidth;
        if (S.canvas.height !== bufferHeight) S.canvas.height = bufferHeight;
        S.canvas.style.width = dispW + 'px';
        S.canvas.style.height = dispH + 'px';
    }
    S.fitCanvasToWrap = fitCanvasToWrap;
    fitCanvasToWrap();
    if (typeof ResizeObserver !== 'undefined') {
        let rafId = null;
        new ResizeObserver(() => {
            if (rafId) return; // coalesce bursts into one rAF
            rafId = requestAnimationFrame(() => {
                rafId = null;
                fitCanvasToWrap();
                renderEditor();
            });
        }).observe(wrap);
    }

    renderEditor();
    updateZoomDisplay();
}

/** Convert a mouse event's clientX/Y to canvas-internal pixel coords.
 *  Needed because CSS may scale the canvas display size ≠ its buffer size. */
/** Convert a mouse event's clientX/Y to world coordinates.
 *  Needed because CSS may scale the canvas display size ≠ its buffer size,
 *  and the buffer may differ from the map's world dimensions. */
function getCanvasPoint(event) {
    const rect = S.canvas.getBoundingClientRect();
    const scaleX = S.canvas.width / rect.width;
    const scaleY = S.canvas.height / rect.height;
    const bufX = (event.clientX - rect.left) * scaleX;
    const bufY = (event.clientY - rect.top) * scaleY;
    // Undo the bufScale applied in renderEditor to get world coords.
    const bufScaleX = S.canvas.width / S.mapWidth;
    const bufScaleY = S.canvas.height / S.mapHeight;
    return {
        x: (bufX / bufScaleX - S.viewOffsetX) / S.viewScale,
        y: (bufY / bufScaleY - S.viewOffsetY) / S.viewScale,
    };
}

// ========== Zoom and Pan Handlers ==========
function handleWheel(event) {
    event.preventDefault();
    const rect = S.canvas.getBoundingClientRect();
    const bufScaleX = S.canvas.width / rect.width;
    const bufScaleY = S.canvas.height / rect.height;
    const bufX = (event.clientX - rect.left) * bufScaleX;
    const bufY = (event.clientY - rect.top) * bufScaleY;
    // Convert buffer coords to world coords for the zoom math.
    const wScaleX = S.canvas.width / S.mapWidth;
    const wScaleY = S.canvas.height / S.mapHeight;
    const mouseX = bufX / wScaleX;
    const mouseY = bufY / wScaleY;

    // Calculate zoom factor
    const zoomFactor = event.deltaY > 0 ? 0.9 : 1.1;
    const newScale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, S.viewScale * zoomFactor));

    // Zoom centered on mouse position
    const scaleChange = newScale / S.viewScale;
    S.viewOffsetX = mouseX - (mouseX - S.viewOffsetX) * scaleChange;
    S.viewOffsetY = mouseY - (mouseY - S.viewOffsetY) * scaleChange;
    S.viewScale = newScale;

    renderEditor();
    updateZoomDisplay();
}

function zoomIn() {
    const newScale = Math.min(MAX_ZOOM, S.viewScale * 1.25);
    const centerX = S.mapWidth / 2;
    const centerY = S.mapHeight / 2;
    const scaleChange = newScale / S.viewScale;
    S.viewOffsetX = centerX - (centerX - S.viewOffsetX) * scaleChange;
    S.viewOffsetY = centerY - (centerY - S.viewOffsetY) * scaleChange;
    S.viewScale = newScale;
    renderEditor();
    updateZoomDisplay();
}

function zoomOut() {
    const newScale = Math.max(MIN_ZOOM, S.viewScale / 1.25);
    const centerX = S.mapWidth / 2;
    const centerY = S.mapHeight / 2;
    const scaleChange = newScale / S.viewScale;
    S.viewOffsetX = centerX - (centerX - S.viewOffsetX) * scaleChange;
    S.viewOffsetY = centerY - (centerY - S.viewOffsetY) * scaleChange;
    S.viewScale = newScale;
    renderEditor();
    updateZoomDisplay();
}

function resetZoom() {
    S.viewScale = 1;
    S.viewOffsetX = 0;
    S.viewOffsetY = 0;
    renderEditor();
    updateZoomDisplay();
}

function fitEditorTopology() {
    if (!S.canvas || !S.nodes.length) return false;
    let left = Infinity, right = -Infinity, top = Infinity, bottom = -Infinity;
    const include = (x, y, halfWidth = 0, halfHeight = 0) => {
        if (!Number.isFinite(x) || !Number.isFinite(y)) return;
        left = Math.min(left, x - halfWidth); right = Math.max(right, x + halfWidth);
        top = Math.min(top, y - halfHeight); bottom = Math.max(bottom, y + halfHeight);
    };
    // Include the node cards and their labels when centering the viewport.
    S.ctx?.save();
    if (S.ctx) S.ctx.font = '12px "Segoe UI", Arial, sans-serif';
    for (const node of S.nodes) {
        const label = String(node.label || 'Node');
        const measured = S.ctx?.measureText(label)?.width;
        const labelWidth = Number.isFinite(measured) ? measured : label.length * 7;
        include(Number(node.x), Number(node.y), Math.max(27, labelWidth / 2 + 8), 34);
    }
    S.ctx?.restore();
    const defaults = getDefaultLinkStyle();
    for (const link of S.links) {
        const points = (link.style?.via_points || []).map(p => ({ x: Number(p.x), y: Number(p.y) }))
            .filter(p => Number.isFinite(p.x) && Number.isFinite(p.y));
        const source = findNodeById(link.srcId), target = findNodeById(link.dstId);
        if (!source || !target) continue;
        const curved = (link.style?.via_style || defaults.via_style || S.editorConfig.link_style) === 'curved';
        if (curved && !points.length) {
            const dx = target.x - source.x, dy = target.y - source.y, length = Math.hypot(dx, dy) || 1;
            points.push({ x: (source.x + target.x) / 2 - dy / length * 36,
                y: (source.y + target.y) / 2 + dx / length * 36 });
        }
        for (const point of points) include(point.x, point.y, 8, 8);
        if (curved) {
            const curve = [source, ...points, target];
            for (let i = 0; i < curve.length - 1; i++) {
                const p0 = curve[Math.max(0, i - 1)], p1 = curve[i];
                const p2 = curve[i + 1], p3 = curve[Math.min(curve.length - 1, i + 2)];
                include(Number(p1.x) + (p2.x - p0.x) / 6, Number(p1.y) + (p2.y - p0.y) / 6, 8, 8);
                include(Number(p2.x) - (p3.x - p1.x) / 6, Number(p2.y) - (p3.y - p1.y) / 6, 8, 8);
            }
        }
    }
    if (!Number.isFinite(left)) return false;
    const rect = S.canvas.getBoundingClientRect();
    const padX = Math.min(S.mapWidth * 0.2, 28 * S.mapWidth / (rect.width || S.mapWidth));
    const padY = Math.min(S.mapHeight * 0.2, 28 * S.mapHeight / (rect.height || S.mapHeight));
    // Legacy maps can extend beyond their saved dimensions, so fitting must
    // permit a scale below the usual wheel zoom minimum.
    S.viewScale = Math.min(MAX_ZOOM, (S.mapWidth - 2 * padX) / Math.max(1, right - left),
        (S.mapHeight - 2 * padY) / Math.max(1, bottom - top));
    S.viewOffsetX = S.mapWidth / 2 - (left + right) / 2 * S.viewScale;
    S.viewOffsetY = S.mapHeight / 2 - (top + bottom) / 2 * S.viewScale;
    renderEditor();
    updateZoomDisplay();
    return true;
}

function updateZoomDisplay() {
    const display = document.getElementById('zoom-level');
    if (display) display.textContent = Math.round(S.viewScale * 100) + '%';
}

function handleMouseDown(event) {
    if (!S.canvas) return;

    // Middle-click or right-click for panning
    if (event.button === 1 || event.button === 2) {
        S.isPanning = true;
        S.panStart = { clientX: event.clientX, clientY: event.clientY, offsetX: S.viewOffsetX, offsetY: S.viewOffsetY };
        S.canvas.style.cursor = 'grabbing';
        return;
    }

    if (S.simulation?.active) return;
    const readOnly = S.replay?.active || S.replay?.loading;

    const { x, y } = getCanvasPoint(event);
    const node = getNodeAt(x, y);

    if (S.linkMode && !readOnly) {
        if (!node) return;
        if (!S.linkStart) {
            S.linkStart = node;
            updateLinkModeUI();
            renderEditor(); // Highlight the selected node
            return;
        }

        if (S.linkStart.id !== node.id) {
            saveState(); // Save for undo
            S.links.push({
                id: `link-${Date.now()}`,
                dbId: null,
                srcId: S.linkStart.id,
                dstId: node.id,
                portA: null,
                portB: null,
                bw: null,
                style: {},
            });
            S.linkStart = null;
            updateLinkModeUI();
            renderEditor();
            renderLinksList();
            renderNodesList();
            LLTToast.success('Link created!', { duration: 1500 });
        }
        return;
    }

    if (node) {
        // Multi-select: shift/ctrl-click toggles membership in the set; a
        // plain click in selection mode keeps the set (anchoring drag), a
        // plain click otherwise resets to a single selection.
        if (event.shiftKey || event.ctrlKey) {
            const i = S.selectedNodes.indexOf(node);
            if (i >= 0) {
                S.selectedNodes.splice(i, 1);
                if (S.selectedNode === node) {
                    S.selectedNode = S.selectedNodes[0] || null;
                }
            } else {
                S.selectedNodes.push(node);
                S.selectedNode = node;
            }
        } else if (S.selectionMode) {
            if (S.selectedNodes.indexOf(node) < 0) {
                S.selectedNodes.push(node);
            }
            S.selectedNode = node;
        } else {
            S.selectedNodes = [node];
            S.selectedNode = node;
        }
        S.isDragging = !readOnly && S.selectedNodes.includes(node);
        S.dragOffset = { x: x - node.x, y: y - node.y };
        if (S.isDragging) saveState(); // Save for undo
        populateNodeProperties(S.selectedNode);
        updateToolbarState();
        renderNodesList();
    } else if (S.selectionMode) {
        // Selection mode on empty click starts a rubber-band marquee instead
        // of clearing the selection.
        S.marquee = { startX: x, startY: y, x: x, y: y };
        S.isDragging = false;
    } else {
        S.selectedNodes = [];
        S.selectedNode = null;
        populateNodeProperties(null);
        updateToolbarState();
        renderNodesList();
    }

    renderEditor();
}

function handleMouseMove(event) {
    // Handle panning
    if (S.isPanning) {
        const rect = S.canvas.getBoundingClientRect();
        const scaleX = S.mapWidth / rect.width;
        const scaleY = S.mapHeight / rect.height;
        S.viewOffsetX = S.panStart.offsetX + (event.clientX - S.panStart.clientX) * scaleX;
        S.viewOffsetY = S.panStart.offsetY + (event.clientY - S.panStart.clientY) * scaleY;
        renderEditor();
        return;
    }

    // Rubber-band marquee: track the drag rectangle live.
    if (S.selectionMode && S.marquee && !S.isDragging) {
        const pt = getCanvasPoint(event);
        S.marquee.x = pt.x;
        S.marquee.y = pt.y;
        renderEditor();
        return;
    }

    if (!S.isDragging || !S.selectedNode || !S.canvas || S.replay?.active || S.replay?.loading) return;
    const { x, y } = getCanvasPoint(event);
    const nodeRadius = 24;
    // Calculate new position for the anchor (dragged) node
    let newX = x - S.dragOffset.x;
    let newY = y - S.dragOffset.y;

    // Apply grid snapping if enabled
    if (S.snapToGrid) {
        newX = snapPosition(newX);
        newY = snapPosition(newY);
        // Keep the original grab offset: small pointer moves must accumulate
        // until the next grid cell instead of resetting on every mousemove.
    }

    // Constrain anchor to map bounds
    newX = Math.max(nodeRadius, Math.min(S.mapWidth - nodeRadius, newX));
    newY = Math.max(nodeRadius, Math.min(S.mapHeight - nodeRadius, newY));

    // Compute delta from the anchor's current position and apply to all
    // selected nodes so group-drag keeps the selection together.
    const group = (S.selectedNodes.length > 0) ? S.selectedNodes : [S.selectedNode];
    const dx = Math.max(nodeRadius - Math.min(...group.map(n => n.x)),
        Math.min(S.mapWidth - nodeRadius - Math.max(...group.map(n => n.x)), newX - S.selectedNode.x));
    const dy = Math.max(nodeRadius - Math.min(...group.map(n => n.y)),
        Math.min(S.mapHeight - nodeRadius - Math.max(...group.map(n => n.y)), newY - S.selectedNode.y));
    for (const n of group) {
        n.x += dx;
        n.y += dy;
    }
    renderEditor();
}

function snapPosition(pos) {
    if (!S.snapToGrid) return pos;
    return Math.round(pos / S.gridSize) * S.gridSize;
}

function toggleSnapToGrid() {
    S.snapToGrid = !S.snapToGrid;
    const btn = document.getElementById('snap-grid-btn');
    if (btn) {
        btn.classList.toggle('active', S.snapToGrid);
        btn.title = S.snapToGrid ? 'Snap to Grid (ON)' : 'Snap to Grid (OFF)';
    }
    renderEditor();
}

function handleMouseUp(event) {
    // Finalize a rubber-band marquee: select all nodes inside the rect.
    if (S.selectionMode && S.marquee) {
        const m = S.marquee;
        // Capture the release position as the final corner (in case the
        // pointer moved after the last mousemove or released without one).
        const up = getCanvasPoint(event);
        const left = Math.min(m.startX, up.x);
        const right = Math.max(m.startX, up.x);
        const top = Math.min(m.startY, up.y);
        const bottom = Math.max(m.startY, up.y);
        S.selectedNodes = S.nodes.filter(n =>
            n.x >= left && n.x <= right && n.y >= top && n.y <= bottom
        );
        S.selectedNode = S.selectedNodes[0] || null;
        S.marquee = null;
        populateNodeProperties(S.selectedNode || null);
        updateToolbarState();
        renderNodesList();
        renderEditor();
        return;
    }
    S.isDragging = false;
    if (S.isPanning) {
        S.isPanning = false;
        S.canvas.style.cursor = 'default';
    }
}

function getNodeAt(x, y) {
    return S.nodes.find(node => {
        const size = editorNodeHalfSize(node);
        return Math.abs(x - node.x) <= size.width && Math.abs(y - node.y) <= size.height;
    });
}

function findNodeById(id) {
    return S.nodes.find(node => node.id === id || node.dbId === id);
}

function getNodeColor(node) {
    const status = node.status || 'unknown';
    if (status === 'down') return '#dc3545';
    if (status === 'up') return '#28a745';
    return '#6c757d';  // unknown
}

function getNodeDeviceType(node) {
    const explicit = node.deviceType || node.meta?.device_type;
    if (explicit && explicit !== 'auto') return explicit;
    const label = String(node.label || '').toLowerCase();
    if (label.includes('palo') || label.includes('pan-') || label.includes('panos')) return 'paloalto';
    if (label.includes('netscaler') || label.includes('adc') || label.includes('citrix')) return 'netscaler';
    if (label.includes('forti') || label.includes('fortigate')) return 'fortinet';
    if (label.includes('aruba') || label.includes('hpe')) return 'aruba';
    if (label.includes('brocade') || label.includes('extreme')) return 'brocade';
    if (label.includes('firewall') || label.includes('fw')) return 'firewall';
    if (label.includes('router') || label.includes('core')) return 'router';
    if (label.includes('switch')) return 'switch';
    if (label.includes('server') || label.includes('db') || label.includes('app')) return 'server';
    return 'default';
}

function drawNode(node) {
    const ctx = S.ctx;
    const radius = 14;
    const defaultNodeStyle = getDefaultNodeStyle();
    const deviceType = getNodeDeviceType(node);
    ctx.beginPath();
    ctx.roundRect(node.x - 22, node.y - 14, 44, 28, 4);

    // Color based on state: link start (orange), selected (blue), normal (default or green)
    if (S.linkMode && S.linkStart === node) {
        ctx.fillStyle = '#fd7e14'; // Orange for link start
    } else if (S.selectedNodes.indexOf(node) >= 0 || node === S.selectedNode) {
        ctx.fillStyle = '#0d6efd'; // Blue for selected
    } else {
        ctx.fillStyle = node.status ? getNodeColor(node) : (defaultNodeStyle.color || '#28a745');
    }
    ctx.fill();

    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 2;
    ctx.stroke();

    const mark = { paloalto: 'PA', netscaler: 'LB', fortinet: 'FW', aruba: 'SW', brocade: 'SW', firewall: 'FW', router: 'RT', switch: 'SW', server: 'SV', default: 'DV' }[deviceType] || 'DV';
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 9px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(mark, node.x, node.y + 3);

    // Draw pulsing ring for link start node
    if (S.linkMode && S.linkStart === node) {
        ctx.beginPath();
        ctx.roundRect(node.x - 27, node.y - 19, 54, 38, 7);
        ctx.strokeStyle = 'rgba(253, 126, 20, 0.5)';
        ctx.lineWidth = 2;
        ctx.stroke();
    }

    // Draw red dashed ring for down nodes
    if (node.status === 'down') {
        ctx.beginPath();
        ctx.roundRect(node.x - 27, node.y - 19, 54, 38, 7);
        ctx.strokeStyle = 'rgba(220, 53, 69, 0.6)';
        ctx.lineWidth = 2;
        ctx.setLineDash([3, 2]);
        ctx.stroke();
        ctx.setLineDash([]);
    }

    // Use theme-aware text color for labels with shadow for readability
    const isDarkTheme = document.querySelector('.editor-container.dark-theme') !== null;
    ctx.font = '12px "Segoe UI", Arial, sans-serif';
    ctx.textAlign = 'center';

    // Add text shadow/outline for better readability
    ctx.strokeStyle = isDarkTheme ? 'rgba(0,0,0,0.7)' : 'rgba(255,255,255,0.8)';
    ctx.lineWidth = 3;
    ctx.strokeText(node.label || 'Node', node.x, node.y - 20);

    ctx.fillStyle = defaultNodeStyle.label_color || (isDarkTheme ? '#f8f9fa' : '#212529');
    ctx.fillText(node.label || 'Node', node.x, node.y - 20);
    drawEditorPortAnchors(node, isDarkTheme);
}

function editorNodeHalfSize(node) {
    return { width: 22, height: 14 };
}

function editorScreenScale() {
    const rect = S.canvas?.getBoundingClientRect();
    const displayScale = rect?.width > 0 && rect?.height > 0
        ? Math.min(rect.width / S.mapWidth, rect.height / S.mapHeight) : 1;
    return Math.max(0.01, displayScale * S.viewScale);
}

function drawEditorPortAnchors(node, isDarkTheme) {
    if (!editorPortAnchors) return;
    const selected = S.selectedNode === node || S.selectedNodes.includes(node);
    const screenScale = editorScreenScale();
    if (!selected && screenScale < 1.2) return;
    const anchors = editorPortAnchors.forNode(node);
    for (const anchor of anchors) {
        S.ctx.beginPath();
        S.ctx.arc(anchor.x, anchor.y, selected ? 3.4 : 2.8, 0, Math.PI * 2);
        S.ctx.fillStyle = selected ? '#7dd3fc' : '#59d1b2';
        S.ctx.fill();
        S.ctx.strokeStyle = isDarkTheme ? '#0f1720' : '#ffffff';
        S.ctx.lineWidth = 1.2;
        S.ctx.stroke();
        if (anchor.label && ((selected && screenScale >= 0.85) || screenScale >= 2)) {
            const horizontal = anchor.side === 'left' || anchor.side === 'right';
            S.ctx.font = '8px "Segoe UI", Arial, sans-serif';
            S.ctx.textAlign = anchor.side === 'left' ? 'right' : (anchor.side === 'right' ? 'left' : 'center');
            S.ctx.fillStyle = isDarkTheme ? '#dce7ef' : '#344955';
            S.ctx.fillText(anchor.label, anchor.x + (anchor.side === 'left' ? -5 : anchor.side === 'right' ? 5 : 0), anchor.y + (horizontal ? 3 : anchor.side === 'top' ? -5 : 10));
        }
    }
}

function drawLink(link) {
    const ctx = S.ctx;
    const src = findNodeById(link.srcId);
    const dst = findNodeById(link.dstId);
    link._segs = [];
    if (!src || !dst) return;

    const defaultLinkStyle = getDefaultLinkStyle();
    let viaPoints = (link.style && link.style.via_points) || [];
    const viaStyle = (link.style && link.style.via_style) || defaultLinkStyle.via_style || S.editorConfig.link_style;
    if (viaStyle === 'curved' && viaPoints.length === 0) {
        const dx = dst.x - src.x;
        const dy = dst.y - src.y;
        const length = Math.hypot(dx, dy) || 1;
        viaPoints = [{
            x: (src.x + dst.x) / 2 - (dy / length) * 36,
            y: (src.y + dst.y) / 2 + (dx / length) * 36,
        }];
    }
    const sourceAnchor = editorPortAnchors?.sourceFor(link) || null;
    const targetAnchor = editorPortAnchors?.targetFor(link) || null;
    let points = window.LLTPortAnchors?.routePoints && sourceAnchor && targetAnchor
        ? window.LLTPortAnchors.routePoints({ ...link, style: { ...link.style, via_style: viaStyle } }, sourceAnchor, targetAnchor, viaPoints)
        : [{ x: sourceAnchor?.x ?? src.x, y: sourceAnchor?.y ?? src.y }, ...viaPoints.map(p => ({ x: p.x, y: p.y })), { x: targetAnchor?.x ?? dst.x, y: targetAnchor?.y ?? dst.y }];
    if (viaStyle === 'straight' && points.length === 2) points = orthogonalEditorPoints(points[0], points[1]);
    points = trimEditorLinkEndpoints(points, sourceAnchor ? 3 : 13, targetAnchor ? 3 : 13);

    const telemetry = S.telemetry?.[link.id] || {};
    const simulatedFailure = S.simulation?.active
        && S.simulation.failedLinks?.has(String(link.id));
    const utilization = Number(telemetry.pct ?? telemetry.utilization ?? 0);
    const latency = Number(telemetry.latency_ms ?? 0);
    const jitter = Number(telemetry.jitter_ms ?? 0);
    const health = Number(telemetry.health_score ?? 100);
    const predictedRisk = Number(S.simulation?.result?.[link.id]?.risk ?? 0);
    const adaptiveWidth = Math.min(6, 2 + utilization / 25 + latency / 50);
    const adaptiveColor = predictedRisk >= 80 || health < 40
        ? '#dc3545'
        : predictedRisk >= 60 || health < 70 ? '#ff9800' : null;

    ctx.save();
    if (jitter > 10 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        ctx.translate(Math.sin(performance.now() / 90 + (Number(link.id) || 0)) * Math.min(2, jitter / 10), 0);
    }
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    const hitPoints = [points[0]];

    if (points.length === 2) {
        ctx.lineTo(points[1].x, points[1].y);
        hitPoints.push(points[1]);
    } else if (viaStyle === 'curved') {
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
            const length = Math.hypot(cp1x - p1.x, cp1y - p1.y)
                + Math.hypot(cp2x - cp1x, cp2y - cp1y) + Math.hypot(p2.x - cp2x, p2.y - cp2y);
            const steps = Math.max(8, Math.min(128, Math.ceil(length * editorScreenScale() / 8)));
            for (let step = 1; step <= steps; step++) {
                const t = step / steps, u = 1 - t;
                hitPoints.push({
                    x: u * u * u * p1.x + 3 * u * u * t * cp1x + 3 * u * t * t * cp2x + t * t * t * p2.x,
                    y: u * u * u * p1.y + 3 * u * u * t * cp1y + 3 * u * t * t * cp2y + t * t * t * p2.y,
                });
            }
        }
    } else {
        for (let i = 1; i < points.length; i++) {
            ctx.lineTo(points[i].x, points[i].y);
            hitPoints.push(points[i]);
        }
    }

    ctx.strokeStyle = simulatedFailure ? '#dc3545' : (adaptiveColor || ((link.style && link.style.color) ? link.style.color : (defaultLinkStyle.color || '#6c757d')));
    ctx.lineWidth = simulatedFailure ? 5 : ((link.style && link.style.width) ? link.style.width : Math.max(defaultLinkStyle.width || 2, adaptiveWidth, predictedRisk / 25));
    if (simulatedFailure) ctx.setLineDash([8, 5]);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.restore();

    // Follow the visible Bezier curve when picking simulation links.
    for (let i = 1; i < hitPoints.length; i++) {
        link._segs.push({ x1: hitPoints[i - 1].x, y1: hitPoints[i - 1].y, x2: hitPoints[i].x, y2: hitPoints[i].y });
    }
}

function orthogonalEditorPoints(start, end) {
    if (Math.abs(end.x - start.x) >= Math.abs(end.y - start.y)) {
        const midX = (start.x + end.x) / 2;
        return [start, { x: midX, y: start.y }, { x: midX, y: end.y }, end];
    }
    const midY = (start.y + end.y) / 2;
    return [start, { x: start.x, y: midY }, { x: end.x, y: midY }, end];
}

function trimEditorLinkEndpoints(points, startRadius = 13, endRadius = startRadius) {
    if (points.length < 2) return points;
    const trimmed = points.map(point => ({ ...point }));
    const moveToward = (from, toward, radius) => {
        const dx = toward.x - from.x;
        const dy = toward.y - from.y;
        const length = Math.hypot(dx, dy) || 1;
        return { x: from.x + dx / length * radius, y: from.y + dy / length * radius };
    };
    trimmed[0] = moveToward(trimmed[0], trimmed[1], startRadius);
    const last = trimmed.length - 1;
    trimmed[last] = moveToward(trimmed[last], trimmed[last - 1], endRadius);
    return trimmed;
}

function renderEditor() {
    const ctx = S.ctx;
    const canvas = S.canvas;
    if (!ctx || !canvas) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Scale world coordinates to the canvas buffer (which may differ from
    // the map's world dimensions when fitCanvasToWrap resizes for crisp
    // text on high-DPI displays).
    const bufScaleX = canvas.width / S.mapWidth;
    const bufScaleY = canvas.height / S.mapHeight;
    ctx.save();
    ctx.scale(bufScaleX, bufScaleY);
    ctx.translate(S.viewOffsetX, S.viewOffsetY);
    ctx.scale(S.viewScale, S.viewScale);

    // Draw grid when zoomed or snap is enabled
    if (S.viewScale !== 1 || S.snapToGrid) {
        drawGrid();
    }

    const defaultLinkStyle = getDefaultLinkStyle();
    editorPortAnchors = window.LLTPortAnchors?.build(S.nodes, S.links, {
        nodeId: node => node.id,
        nodeX: node => node.x,
        nodeY: node => node.y,
        sourceId: link => link.srcId,
        targetId: link => link.dstId,
        sourcePort: link => link.portA,
        targetPort: link => link.portB,
        sourceLabel: link => link.sourcePortName,
        targetLabel: link => link.destinationPortName,
        halfSize: editorNodeHalfSize,
    }) || null;
    // Viewport culling: skip nodes/links entirely outside the visible world
    // rect so pan/zoom on large maps doesn't draw off-screen content.
    const vMargin = 24 / S.viewScale;
    const vLeft = (0 - S.viewOffsetX) / S.viewScale - vMargin;
    const vRight = (S.mapWidth - S.viewOffsetX) / S.viewScale + vMargin;
    const vTop = (0 - S.viewOffsetY) / S.viewScale - vMargin;
    const vBottom = (S.mapHeight - S.viewOffsetY) / S.viewScale + vMargin;
    // Always render in-flight interaction nodes (dragged/selected/link-source)
    // even if outside the viewport — otherwise a node vanishes mid-drag when
    // it crosses the culling boundary.
    const pinned = new Set();
    if (S.selectedNodes) S.selectedNodes.forEach(n => pinned.add(n));
    if (S.linkStart) pinned.add(S.linkStart);
    const nodeVisible = (n) => pinned.has(n) ||
        (n.x >= vLeft && n.x <= vRight && n.y >= vTop && n.y <= vBottom);
    const linkVisible = (l) => {
        const a = findNodeById(l.srcId);
        const b = findNodeById(l.dstId);
        if (!a) return !!b && nodeVisible(b);
        if (!b) return nodeVisible(a);
        // Links touching a pinned (in-flight) node always render.
        // Include via_points in the AABB so bent links crossing the
        // viewport stay visible even with both endpoints off-screen.
        const via = (l.style && l.style.via_points) || [];
        const viaStyle = (l.style && l.style.via_style) || defaultLinkStyle.via_style || S.editorConfig.link_style;
        const pts = [{x: a.x, y: a.y}, ...via, {x: b.x, y: b.y}];
        let minX = Math.min(a.x, b.x), maxX = Math.max(a.x, b.x);
        let minY = Math.min(a.y, b.y), maxY = Math.max(a.y, b.y);
        if (viaStyle === 'curved' && via.length === 0) {
            minX -= 40; maxX += 40; minY -= 40; maxY += 40;
        }
        for (const p of via) {
            if (p.x < minX) minX = p.x; else if (p.x > maxX) maxX = p.x;
            if (p.y < minY) minY = p.y; else if (p.y > maxY) maxY = p.y;
        }
        // Curved (Catmull-Rom → cubic bezier) control points can extend
        // beyond the point hull; include them to avoid culling on-screen
        // curve segments.
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
        return Math.max(minX, vLeft) <= Math.min(maxX, vRight)
            && Math.max(minY, vTop) <= Math.min(maxY, vBottom);
    };

    for (const l of S.links) {
        l._segs = [];
        if (linkVisible(l)) drawLink(l);
    }
    for (const n of S.nodes) if (nodeVisible(n)) drawNode(n);

    // Rubber-band marquee overlay (in transformed canvas coords).
    if (S.selectionMode && S.marquee) {
        const m = S.marquee;
        const rLeft = Math.min(m.startX, m.x);
        const rTop = Math.min(m.startY, m.y);
        const rW = Math.abs(m.x - m.startX);
        const rH = Math.abs(m.y - m.startY);
        ctx.save();
        ctx.fillStyle = 'rgba(13, 110, 253, 0.15)';
        ctx.fillRect(rLeft, rTop, rW, rH);
        ctx.strokeStyle = '#0d6efd';
        ctx.lineWidth = 1 / S.viewScale;
        ctx.setLineDash([4 / S.viewScale, 4 / S.viewScale]);
        ctx.strokeRect(rLeft, rTop, rW, rH);
        ctx.setLineDash([]);
        ctx.restore();
    }

    ctx.restore();

    // Update minimap and status
    renderMinimap();
    updateStatusCounts();
}

function drawGrid() {
    const ctx = S.ctx;
    const size = S.snapToGrid ? S.gridSize : 50;
    ctx.strokeStyle = S.snapToGrid ? 'rgba(100, 150, 255, 0.3)' : 'rgba(200, 200, 200, 0.3)';
    ctx.lineWidth = 0.5 / S.viewScale;

    for (let x = 0; x <= S.mapWidth; x += size) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, S.mapHeight);
        ctx.stroke();
    }
    for (let y = 0; y <= S.mapHeight; y += size) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(S.mapWidth, y);
        ctx.stroke();
    }
}

// ========== Editor Minimap ==========
function initMinimap() {
    S.minimapCanvas = document.getElementById('editor-minimap');
    if (!S.minimapCanvas) return;
    S.minimapCtx = S.minimapCanvas.getContext('2d');
    S.minimapCanvas.addEventListener('click', handleMinimapClick);
}

function renderMinimap() {
    if (!S.minimapCtx || !S.minimapCanvas || !S.canvas) return;
    const mmW = S.minimapCanvas.width;
    const mmH = S.minimapCanvas.height;
    const minimapCtx = S.minimapCtx;

    minimapCtx.clearRect(0, 0, mmW, mmH);

    // Calculate scale to fit map in minimap
    const scaleX = mmW / S.mapWidth;
    const scaleY = mmH / S.mapHeight;
    const scale = Math.min(scaleX, scaleY);

    // Draw map bounds
    minimapCtx.strokeStyle = '#ccc';
    minimapCtx.lineWidth = 1;
    minimapCtx.strokeRect(0, 0, S.mapWidth * scale, S.mapHeight * scale);

    // Draw nodes as dots
    S.nodes.forEach(node => {
        minimapCtx.beginPath();
        minimapCtx.arc(node.x * scale, node.y * scale, 3, 0, Math.PI * 2);
        minimapCtx.fillStyle = node === S.selectedNode ? '#0d6efd' : '#28a745';
        minimapCtx.fill();
    });

    // Draw viewport rectangle
    if (S.viewScale !== 1 || S.viewOffsetX !== 0 || S.viewOffsetY !== 0) {
        const vpX = (-S.viewOffsetX / S.viewScale) * scale;
        const vpY = (-S.viewOffsetY / S.viewScale) * scale;
        const vpW = (S.mapWidth / S.viewScale) * scale;
        const vpH = (S.mapHeight / S.viewScale) * scale;

        minimapCtx.strokeStyle = 'rgba(0, 123, 255, 0.8)';
        minimapCtx.lineWidth = 2;
        minimapCtx.strokeRect(vpX, vpY, vpW, vpH);
    }
}

function handleMinimapClick(event) {
    if (!S.minimapCanvas || !S.canvas) return;
    const rect = S.minimapCanvas.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const clickY = event.clientY - rect.top;

    // Convert minimap coords to map coords
    const scaleX = S.minimapCanvas.width / S.mapWidth;
    const scaleY = S.minimapCanvas.height / S.mapHeight;
    const scale = Math.min(scaleX, scaleY);

    const mapX = clickX / scale;
    const mapY = clickY / scale;

    // Center view on clicked position
    S.viewOffsetX = S.mapWidth / 2 - mapX * S.viewScale;
    S.viewOffsetY = S.mapHeight / 2 - mapY * S.viewScale;

    renderEditor();
    updateZoomDisplay();
}

// ========== Link Mode ==========
function toggleLinkMode() {
    S.linkMode = !S.linkMode;
    S.linkStart = null;
    updateLinkModeUI();
}

function updateLinkModeUI() {
    const btn = document.getElementById('link-mode-btn');
    if (btn) {
        // Remove all state classes first
        btn.classList.remove('active', 'link-active');

        if (S.linkMode && S.linkStart) {
            btn.classList.add('link-active'); // Orange pulsing - waiting for 2nd node
            btn.title = 'Click another node to complete link';
        } else if (S.linkMode) {
            btn.classList.add('active'); // Blue - link mode on
            btn.title = 'Click a node to start link';
        } else {
            btn.title = 'Link Mode - Click two nodes to connect';
        }
    }
    // Change canvas cursor in link mode
    if (S.canvas) {
        S.canvas.style.cursor = S.linkMode ? 'crosshair' : 'default';
    }
}

// Initialize minimap on page load
document.addEventListener('DOMContentLoaded', initMinimap);
