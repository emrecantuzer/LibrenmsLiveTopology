const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const view = fs.readFileSync(path.join(__dirname, '../resources/views/embed.blade.php'), 'utf8');
const inlineScript = view.match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(inlineScript, 'embed script exists');

const fixtureMap = {
    id: 1,
    options: {},
    nodes: [
        { id: 1, x: 0, y: 0, label: '<core-sw-01>', status: 'up', traffic: { in_bps: 100, out_bps: 200 } },
        { id: 2, x: 100, y: 0, label: 'edge-rtr-02', status: 'down', traffic: { in_bps: 0, out_bps: 0 } },
    ],
    links: [{ id: 10, src: 1, dst: 2, source_port_name: 'eth1/0/1', destination_port_name: 'ge-0/0/0', live: { in_bps: 100, out_bps: 200, pct: 90 } }],
};

const elements = new Map();
function element(id) {
    if (!elements.has(id)) {
        elements.set(id, {
            style: {},
            classList: { toggle() {}, add() {}, remove() {} },
            addEventListener() {},
            getBoundingClientRect() { return { width: 328, height: 270 }; },
            querySelector() { return { replaceChildren() {} }; },
        });
    }
    return elements.get(id);
}

const script = inlineScript
    .replace('mapData = @json($mapData ?? []);', 'mapData = fixtureMap;')
    .replace(/@json\([^\r\n]*\)/g, 'null')
    .replace(/\{\{[^}]*\}\}/g, 'x');
function makeContext(reducedMotion = false) {
    const frames = new Map(), handlers = new Map(), documentHandlers = new Map();
    let nextFrame = 0;
    const sandbox = vm.createContext({
        fixtureMap: structuredClone(fixtureMap), URLSearchParams, console,
        requestAnimationFrame(callback) { const id = ++nextFrame; frames.set(id, callback); return id; },
        cancelAnimationFrame(id) { frames.delete(id); },
        window: { location: { search: '' }, matchMedia: () => ({ matches: reducedMotion }), addEventListener() {}, innerWidth: 900, innerHeight: 700 },
        document: {
            hidden: false,
            addEventListener(name, handler) { documentHandlers.set(name, handler); },
            getElementById(id) {
                const item = element(id);
                item.setAttribute = () => {};
                item.addEventListener = (name, handler) => handlers.set(`${id}:${name}`, handler);
                return item;
            },
            body: { classList: { contains() { return false; } } },
        },
    });
    for (const file of ['topology-layout.js', 'topology-ports.js']) {
        vm.runInContext(fs.readFileSync(path.join(__dirname, '../resources/js', file), 'utf8'), sandbox);
    }
    return { sandbox, frames, handlers, documentHandlers };
}
const { sandbox: context } = makeContext();
// These existing inspector tests intentionally update the original fixture.
context.fixtureMap = fixtureMap;

test('embed script parses and uses NOC utilization bands', () => {
    new vm.Script(script);
    vm.runInContext(script, context);
    for (const [utilization, expected] of [[0, '#06b6d4'], [30, '#06b6d4'], [31, '#10b981'], [70, '#10b981'], [71, '#f59e0b'], [89, '#f59e0b'], [90, '#ef4444'], [100, '#ef4444']]) {
        assert.equal(vm.runInContext(`getLinkColor(${utilization})`, context), expected);
    }
    assert.equal(vm.runInContext('isLightMapBackground("#f4f7fa")', context), true);
    assert.equal(vm.runInContext('isLightMapBackground("#0b0f19")', context), false);
    fixtureMap.links[0].style = { via_style: 'curved', via_points: [] };
    assert.equal(vm.runInContext('linkInView(mapData.links[0], {left:-10,right:110,top:-50,bottom:50})', context), true);
    const compactScale = vm.runInContext('canvas = {width:320,height:300}; fitViewport(); baseScale', context);
    assert.ok(compactScale > 0.3, 'compact iframe retains usable map scale');
});

test('inspector escapes device names and marks unavailable metrics as N/A', () => {
    vm.runInContext('showGraphPopup({type:"node",id:1,data:mapData.nodes[0]},100,100)', context);
    const card = element('graph-popup').innerHTML;
    assert.match(card, /&lt;core-sw-01&gt;/);
    assert.doesNotMatch(card, /<core-sw-01>/);
    assert.match(card, /Ping RTT<\/span><strong>N\/A/);
    assert.match(card, /Packet loss \/ drop<\/span><strong>N\/A/);
    assert.match(card, /BGP<\/span><strong>N\/A/);
});

test('live samples form a trend and down nodes surface in alert bar', () => {
    vm.runInContext('recordTrendSamples()', context);
    assert.match(vm.runInContext('sparklineMarkup({type:"link",id:10})', context), /Collecting live trend/);
    fixtureMap.links[0].live.in_bps = 300;
    vm.runInContext('recordTrendSamples(); updateNocSummary()', context);
    assert.match(vm.runInContext('sparklineMarkup({type:"link",id:10})', context), /<polyline/);
    assert.match(element('alert-pill').textContent, /1 devices down/);
    assert.match(element('topology-summary').textContent, /2 devices · 1 links · 1 down/);
});

test('status bar displays only measured RTT', () => {
    vm.runInContext('updateStatus()', context);
    assert.equal(element('live-ping').textContent, 'RTT N/A');
    fixtureMap.links[0].live.latency_ms = 12.4;
    vm.runInContext('updateStatus()', context);
    assert.equal(element('live-ping').textContent, 'RTT 12.4 ms');
});

test('tiered layout separates layers and bundles parallel circuits until expanded', () => {
    const layoutMap = {
        options: {}, nodes: [
            { id: 1, x: 0, y: 0, label: 'core-spine-01' },
            { id: 2, x: 0, y: 0, label: 'distribution-01' },
            { id: 3, x: 0, y: 0, label: 'access-01' },
            { id: 4, x: 0, y: 0, label: 'access-02' },
        ], links: [
            { id: 11, src: 1, dst: 2, live: { in_bps: 100, out_bps: 50, pct: 20 } },
            { id: 12, src: 1, dst: 2, live: { in_bps: 200, out_bps: 75, pct: 95 } },
            { id: 13, src: 2, dst: 3 }, { id: 14, src: 2, dst: 4 },
        ],
    };
    context.layoutMap = layoutMap;
    vm.runInContext('mapData = layoutMap; applyTieredEmbedLayout(); rebuildNodeIndex(); rebuildParallelLinkGroups()', context);
    const [core, distribution, accessA, accessB] = layoutMap.nodes;
    assert.ok(core.y < distribution.y && distribution.y < accessA.y);
    assert.equal(accessA.y, accessB.y);
    assert.ok(Math.abs(accessA.x - accessB.x) >= 230, 'access cards retain a full slot');
    for (const [index, node] of layoutMap.nodes.entries()) {
        for (const other of layoutMap.nodes.slice(index + 1)) {
            assert.ok(Math.abs(node.x - other.x) >= 76 || Math.abs(node.y - other.y) >= 46,
                `device cards ${node.id} and ${other.id} do not overlap`);
        }
    }
    assert.equal(vm.runInContext('nodeCardTrim(0, 0, 0, 200)', context), 27);
    assert.equal(vm.runInContext('nodeCardTrim(0, 0, 200, 0)', context), 42);
    assert.equal(vm.runInContext('isVisibleLink(mapData.links[0])', context), true);
    assert.equal(vm.runInContext('isVisibleLink(mapData.links[1])', context), false);
    assert.equal(vm.runInContext('displayLinkPct(mapData.links[0])', context), 95);
    assert.equal(vm.runInContext('displayLinkLive(mapData.links[0]).in_bps', context), 300);
    assert.equal(vm.runInContext('shouldDrawLinkLabel(mapData.links[0], 95)', context), false);
    vm.runInContext('expandedBundles.add(linkEndpointKey(mapData.links[0]))', context);
    assert.equal(vm.runInContext('isVisibleLink(mapData.links[1])', context), true);
    assert.ok(layoutMap.links[0].style.via_points.length > 0, 'links get a routed path');
    assert.equal(layoutMap.links[0].style.via_style, 'angled');
});

test('embed theme is included at render time so the toolbar cannot fall back to gray', () => {
    assert.match(view, /@include\('LibreLiveTopology::partials\.embed-noc-css'\)/);
    assert.doesNotMatch(view, /asset\('plugins\/LibreLiveTopology\/resources\/css\/embed-noc\.css'\)/);
    const css = fs.readFileSync(path.join(__dirname, '../resources/views/partials/embed-noc-css.blade.php'), 'utf8');
    assert.match(css, /rgba\(15, 23, 42, \.75\)/);
    assert.match(css, /backdrop-filter: blur\(14px\)/);
});

test('wide fanout wraps into rows and opens at a readable focus scale', () => {
    const starMap = {
        nodes: [{ id: 1, label: 'core-01', x: 0, y: 0 }],
        links: [],
    };
    for (let id = 2; id <= 81; id++) {
        starMap.nodes.push({ id, label: `access-${id}`, x: 0, y: 0 });
        starMap.links.push({ id, src: 1, dst: id });
    }
    context.starMap = starMap;
    vm.runInContext('mapData = starMap; applyTieredEmbedLayout(); canvas = {width:1280,height:720}; fitMode = "readable"; fitViewport()', context);
    const leaves = starMap.nodes.slice(1);
    assert.ok(new Set(leaves.map(node => node.y)).size > 2, 'the access tier occupies several rows');
    const width = Math.max(...leaves.map(node => node.x)) - Math.min(...leaves.map(node => node.x));
    assert.ok(width < 3500, 'fanout is bounded horizontally');
    for (const [index, node] of starMap.nodes.entries()) {
        for (const other of starMap.nodes.slice(index + 1)) {
            assert.ok(Math.abs(node.x - other.x) >= 76 || Math.abs(node.y - other.y) >= 46,
                `device cards ${node.id} and ${other.id} do not overlap`);
        }
    }
    assert.equal(vm.runInContext('isFocusedView', context), true);
    const focusedScale = vm.runInContext('baseScale', context);
    const allScale = vm.runInContext('fitMode = "all"; fitViewport(); baseScale', context);
    assert.ok(focusedScale > allScale, 'focus mode keeps nodes readable; Fit all shows the whole topology');
    const transform = vm.runInContext('({scale:baseScale,x:baseOffsetX,y:baseOffsetY})', context);
    for (const node of starMap.nodes) {
        assert.ok((node.x - 38) * transform.scale + transform.x >= 0);
        assert.ok((node.x + 38) * transform.scale + transform.x <= 1280);
        assert.ok((node.y - 33) * transform.scale + transform.y >= 0);
        assert.ok((node.y + 23) * transform.scale + transform.y <= 720);
    }
});

test('saved positions and VIA paths survive default load and layout overflow', () => {
    const { sandbox } = makeContext();
    vm.runInContext(script, sandbox);
    const before = JSON.stringify(sandbox.fixtureMap);
    assert.equal(vm.runInContext('applyTieredEmbedLayout()', sandbox), false);
    assert.equal(JSON.stringify(sandbox.fixtureMap), before);
    vm.runInContext('urlParams.set("layout", "tiered"); window.LLTTopologyLayout.layout = () => ({overflow:true, positions:[], routes:[]})', sandbox);
    assert.equal(vm.runInContext('applyTieredEmbedLayout()', sandbox), false);
    assert.equal(JSON.stringify(sandbox.fixtureMap), before);
});

test('first-load particle canvas is aligned after the loading flex sibling disappears', () => {
    const { sandbox } = makeContext();
    vm.runInContext(script, sandbox);
    const container = element('map-container');
    container.clientWidth = 900; container.clientHeight = 700;
    container.getBoundingClientRect = () => ({ left: 0, top: 0, width: 900, height: 700 });
    const main = element('map-canvas');
    main.parentElement = container;
    main.getContext = () => ({});
    main.getBoundingClientRect = () => ({ left: element('loading').style.display === 'none' ? 0 : 450, top: 0, width: 900, height: 700 });
    element('overlay-canvas').getContext = () => ({});
    element('loading').style.display = 'flex';
    vm.runInContext('initCanvas()', sandbox);
    assert.equal(element('overlay-canvas').style.left, '0px');
    assert.equal(element('overlay-canvas').style.width, '900px');
    assert.equal(element('overlay-canvas').width, 900);
});

function particleRecorder() {
    const tips = [];
    return { tips, save() {}, restore() {}, beginPath() {}, closePath() {}, fill() {}, lineTo() {},
        moveTo(x, y) { tips.push({ x, y, color: this.fillStyle }); } };
}

test('measured outbound and inbound particles move in opposite directions and keep screen size', () => {
    const { sandbox } = makeContext();
    vm.runInContext(script, sandbox);
    const recorder = particleRecorder(); sandbox.recorder = recorder;
    const draw = 'drawFlowParticles({live:{out_bps:100,in_bps:200}},0,0,1000,0,20,[{x:0,y:0},{x:1000,y:0}],recorder)';
    vm.runInContext(`viewScale=.2; animTick=0; ${draw}`, sandbox);
    const first = recorder.tips.splice(0);
    vm.runInContext(`animTick=30; ${draw}`, sandbox);
    const second = recorder.tips.splice(0);
    assert.ok(second[0].x > first[0].x, 'outbound moves source to target');
    const inbound = first.length / 2;
    assert.ok(second[inbound].x < first[inbound].x, 'inbound moves target to source');
    assert.equal(first[0].x * .2, 2.2, 'arrow head keeps a readable screen size at overview zoom');
    assert.ok(first.every(point => point.color === '#06b6d4'), 'both directions use the utilization legend color');
    vm.runInContext('drawFlowParticles({live:{out_bps:0,in_bps:0}},0,0,1000,0,99,null,recorder)', sandbox);
    assert.equal(recorder.tips.length, 0, 'utilization alone does not invent traffic');
    vm.runInContext(`flowAnimationEnabled=false; ${draw}`, sandbox);
    assert.equal(recorder.tips.length, 0, 'flow toggle disables moving marks');
});

test('dense maps limit particles per circuit instead of saturating shared corridors', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    const recorder = particleRecorder(); sandbox.recorder = recorder;
    vm.runInContext(`viewScale=1; linkGeoms.push(...Array(80).fill({}));
        drawFlowParticles({id:17,live:{out_bps:100,in_bps:200}},0,0,10000,0,90,[{x:0,y:0},{x:10000,y:0}],recorder)`, sandbox);
    assert.equal(recorder.tips.length, 2, 'one moving mark in each measured direction remains visible');
    assert.ok(recorder.tips.every(point => point.color === '#ef4444'), 'critical utilization remains red');
});

test('animation starts from live traffic, advances by elapsed time and respects toggle and reduced motion', () => {
    for (const reducedMotion of [false, true]) {
        const { sandbox, frames, handlers } = makeContext(reducedMotion);
        vm.runInContext(script, sandbox);
        vm.runInContext('mapData.nodes.forEach(node => node.status="up"); renderMap=()=>{}; renderOverlay=()=>{}; startAutoUpdate=()=>{}; startLiveUpdates()', sandbox);
        assert.equal(frames.size, reducedMotion ? 0 : 1, 'startup obeys motion preference');
        if (!reducedMotion) {
            const advance = timestamp => { const [id, callback] = frames.entries().next().value; frames.delete(id); callback(timestamp); };
            advance(100); advance(150);
            assert.ok(Math.abs(vm.runInContext('animTick', sandbox) - 3) < .001, '50ms advances three 60Hz frames');
            handlers.get('toggle-flow:click')();
            assert.equal(frames.size, 0, 'toggle off stops the flow loop');
            handlers.get('toggle-flow:click')();
            assert.equal(frames.size, 1, 'toggle on restarts it');
            vm.runInContext('hasActiveTraffic=false', sandbox); advance(200);
            assert.equal(frames.size, 0, 'idle traffic stops scheduling frames');
            vm.runInContext('applyLiveUpdate({links:{10:{in_bps:0,out_bps:500}}})', sandbox);
            assert.equal(frames.size, 1, 'a fresh live sample resumes motion');
            sandbox.document.hidden = true; advance(250);
            assert.equal(frames.size, 0, 'hidden document pauses animation');
        } else {
            handlers.get('toggle-flow:click')();
            assert.equal(frames.size, 1, 'explicit Flow opt-in overrides the default motion preference');
            const recorder = particleRecorder(); sandbox.recorder = recorder;
            vm.runInContext('drawFlowParticles({live:{out_bps:100}},0,0,1000,0,20,[{x:0,y:0},{x:1000,y:0}],recorder)', sandbox);
            assert.ok(recorder.tips.length > 0);
        }
    }
});

test('semantic zoom suppresses overview detail while inspected items and critical alerts remain available', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    vm.runInContext('viewScale=.25; userScale=4', sandbox);
    assert.equal(vm.runInContext('shouldDrawLinkLabel(mapData.links[0],90)', sandbox), false);
    assert.equal(vm.runInContext('showNodeDetails(mapData.nodes[0])', sandbox), false);
    assert.equal(vm.runInContext('showAlertBadge({alerts:{count:1,severity:"critical"}})', sandbox), true);
    vm.runInContext('graphHoverTarget={data:mapData.links[0]}', sandbox);
    assert.equal(vm.runInContext('shouldDrawLinkLabel(mapData.links[0],90)', sandbox), true);
    vm.runInContext('placedLabelRects.length=0; reserveRect({x:-1000,y:-1000,w:2000,h:2000})', sandbox);
    assert.equal(vm.runInContext('resolveLabelPlacement({x:0,y:0},120,26,11)', sandbox), null, 'unplaceable label is omitted instead of overlapping cards');
});

test('angled routes attach to the exact port anchors and reversed bundles preserve direction', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    vm.runInContext(`
        mapData.nodes[1].x=300; mapData.nodes[1].y=190;
        mapData.links[0].style={via_style:'angled',via_points:[{x:0,y:95},{x:300,y:95}]};
        livePortAnchors=window.LLTPortAnchors.build(mapData.nodes,mapData.links,{halfSize:liveNodeHalfSize});
    `, sandbox);
    const points = vm.runInContext('buildLinkPath(mapData.links[0],0,0,300,190,0,0).points', sandbox);
    const source = vm.runInContext('livePortAnchors.sourceFor(mapData.links[0])', sandbox);
    const target = vm.runInContext('livePortAnchors.targetFor(mapData.links[0])', sandbox);
    assert.equal(points[0].x, source.x); assert.equal(points[0].y, source.y);
    assert.equal(points.at(-1).x, target.x); assert.equal(points.at(-1).y, target.y);
    for (let i=1;i<points.length;i++) assert.ok(points[i].x===points[i-1].x || points[i].y===points[i-1].y);
    vm.runInContext('mapData.links=[{id:1,src:1,dst:2,live:{in_bps:100,out_bps:20}},{id:2,src:2,dst:1,live:{in_bps:7,out_bps:30}}]; rebuildParallelLinkGroups()', sandbox);
    assert.equal(vm.runInContext('displayLinkLive(mapData.links[0]).in_bps', sandbox), 130);
    assert.equal(vm.runInContext('displayLinkLive(mapData.links[0]).out_bps', sandbox), 27);
});


test('utilization preserves zero, accepts numeric API values and ignores the label metric', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    for (const metric of ['in', 'out', 'sum', 'percent']) {
        sandbox.metric = metric;
        assert.equal(vm.runInContext('currentMetric=metric; getLinkPct({bandwidth_bps:1000,live:{in_bps:"100",out_bps:"200"}},300)', sandbox), 20);
    }
    assert.equal(vm.runInContext('getLinkPct({live:{in_bps:0,out_bps:0,bandwidth_bps:1000}})', sandbox), 0);
    assert.equal(vm.runInContext('getLinkPct({live:{pct:"31"}})', sandbox), 31);
    assert.equal(vm.runInContext('getLinkPct({live:{in_bps:100}})', sandbox), null);
    assert.equal(vm.runInContext('getLinkPct({bandwidth_bps:1000,live:{in_bps:"",out_bps:null}})', sandbox), null);
    assert.equal(vm.runInContext('getLinkColor(NaN)', sandbox), '#64748b');
});

test('display settings stays inside narrow and short viewports', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    const menu = element('viz-menu'); menu.style.display = 'block';
    sandbox.window.innerWidth=320; sandbox.window.innerHeight=240;
    menu.getBoundingClientRect=()=>({left:260,right:528,top:180,bottom:404});
    vm.runInContext('positionVizMenu()', sandbox);
    assert.equal(menu.style.left, '-216px');
    assert.equal(menu.style.top, 'calc(100% + -164px)');
    assert.equal(menu.style.maxHeight, '224px');
});


test('dashboard hides device names and addresses without mutating saved labels', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    vm.runInContext('dashboardMode=true', sandbox);
    for (const label of ['10.0.0.1', 'router 10.0.0.1', '2001:db8::1']) {
        sandbox.device = {id:12,label,device_sysname:'core-sw-01',device_name:'10.0.0.1'};
        assert.equal(vm.runInContext('nodeDisplayName(device)', sandbox), 'Device');
        assert.equal(sandbox.device.label, label);
    }
    assert.equal(vm.runInContext('nodeDisplayName({id:12,label:"10.0.0.1"})', sandbox), 'Device');
    assert.equal(vm.runInContext('nodeDisplayName({id:12,label:"Custom core",device_sysname:"sys-core"})', sandbox), 'Device');
    assert.equal(vm.runInContext('dashboardMode=false; nodeDisplayName({id:12,label:"10.0.0.1"})', sandbox), '10.0.0.1');
});

test('dashboard toggle preserves URL parameters and hides names in the inspector', () => {
    const { sandbox, handlers } = makeContext();
    sandbox.URL=URL; sandbox.window.location.href='https://example.test/embed/1?interval=10';
    let url; sandbox.window.history={replaceState(a,b,value){url=String(value);}};
    vm.runInContext(script, sandbox);
    vm.runInContext('renderMap=()=>{}; startAutoUpdate=()=>{}; startLiveUpdates()', sandbox);
    element('dashboard-mode').value='1'; handlers.get('dashboard-mode:change')();
    assert.equal(new URL(url).searchParams.get('dashboard'), '1');
    assert.equal(new URL(url).searchParams.get('interval'), '10');
    vm.runInContext('mapData.nodes[0].label="10.0.0.1"; mapData.nodes[0].device_sysname="core-sw-01"; showGraphPopup({type:"node",id:1,data:mapData.nodes[0]},100,100)', sandbox);
    assert.doesNotMatch(element('graph-popup').innerHTML, /core-sw-01/);
    assert.match(element('graph-popup').innerHTML, /inspector-title">Device</);
    assert.doesNotMatch(element('graph-popup').innerHTML, /10\.0\.0\.1/);
});


test('destination-only inspector displays directions relative to its LibreNMS graph', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    sandbox.Image = class {};
    vm.runInContext('mapData.links[0].port_id_a=null; mapData.links[0].port_id_b=101; mapData.links[0].live={in_bps:8000000,out_bps:1000000}; showGraphPopup({type:"link",id:10,data:mapData.links[0]},100,100)', sandbox);
    const html=element('graph-popup').innerHTML;
    assert.match(html, /RX <strong>1\.00 Mb\/s/);
    assert.match(html, /TX <strong>8\.00 Mb\/s/);
    assert.match(html, /RX\/TX at graphed port/);
});


test('click and hover select the same single port and 15-minute graph window', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    assert.equal(vm.runInContext('portGraphUrl({port_id_a:101,port_id_b:202},2000)', sandbox), 'x?type=port_bits&id=101&from=1100&to=2000');
    assert.equal(vm.runInContext('portGraphUrl({port_id_b:202},2000)', sandbox), 'x?type=port_bits&id=202&from=1100&to=2000');
    assert.equal(vm.runInContext('portGraphUrl({},2000)', sandbox), null);
});

test('an open inspector updates both directions when fresh live samples arrive', () => {
    const { sandbox } = makeContext(); vm.runInContext(script, sandbox);
    vm.runInContext('graphsEnabled=false; renderMap=()=>{}; graphHoverTarget={type:"link",id:10,data:mapData.links[0]}; showGraphPopup(graphHoverTarget,100,100)', sandbox);
    vm.runInContext('applyLiveUpdate({links:{10:{in_bps:17360,out_bps:8380}}})', sandbox);
    const html=element('graph-popup').innerHTML;
    assert.match(html, /RX <strong>17\.36 Kb\/s/);
    assert.match(html, /TX <strong>8\.38 Kb\/s/);
});
