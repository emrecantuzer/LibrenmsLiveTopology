# Editor layout, discovery and live traffic

The editor and embed share `resources/js/topology-layout.js` and
`resources/js/topology-ports.js`. These are local browser scripts; no package
installation or frontend build is required.

## Operator workflow

1. Save an existing draft before using **Auto-discover**. Discovery adds physical
   LibreNMS LLDP/CDP/XDP neighbors and keeps existing node positions, labels and routes.
   New devices prefer available space near their already mapped neighbors.
2. Set **Network role** on important devices whose names do not describe their
   role: Core/Spine, Distribution/Aggregation, or Access/Leaf/Edge. This is separate
   from the device's icon/vendor. Changing a role does not move the node.
3. Use **Auto-Layout** to arrange the whole map. Explicit core peers share a tier;
   otherwise a well-connected transit device seeds each component. Parallel ports
   count as one neighbor. Downstream devices are ordered by shared uplinks, and
   unrelated components are packed separately. Two-tier networks stay two-tier.
4. Use **Fit topology** (F) to frame devices, labels and routes without changing
   their saved coordinates. Reset (0) restores the full map coordinate view.
5. Save to publish the changed topology to View. Undo/redo includes the map size.

LLDP/CDP neighbors describe physical connectivity; they do not establish BGP/OSPF
forwarding paths or prove a device's logical role. Explicit roles take priority
over name hints. The distinction between two- and three-tier designs follows
the [Cisco campus architecture overview](https://www.cisco.com/c/en/us/td/docs/solutions/Enterprise/Campus/campover.html).

## Routes and embedding

Automatic routes use orthogonal corridors around device cards. Ports use the
first/last route point to select their device side, with perpendicular entry and
exit segments. Parallel physical circuits stay separate in storage and can be
collapsed/expanded in View. Their aggregate traffic respects endpoint direction.

View preserves saved positions and routes. `?layout=tiered` requests an automatic
layout for that view; invalid or completely collapsed initial positions also
trigger it. Automatic layout rejects maps that cannot fit readable spacing in
4096×4096, retaining the old layout. Split such maps by site or rack. Crossings
between links can still occur in meshed networks; they do not imply a junction.

Flow continuously animates nonzero measured RX/TX between live samples. It uses
the same route as the visible stroke, a bounded particle density and screen-sized
direction markers. Utilization controls color and speed. Flow off retains static
links; reduced-motion preferences and hidden tabs suspend motion. Animation
smoothness does not increase LibreNMS's polling frequency.

Port text and bandwidth detail follow actual screen zoom and inspected items.
Collision checks suppress labels that cannot fit, while critical alert markers
remain available in the overview. Fit all and readable focus remain separate
view controls.

## Verification

Run `node --test tests/*.test.cjs` with Node 22. CI includes these tests alongside
PHPUnit. Coverage includes layout bounds and route/card intersections for 20,
101 and 500 devices; paired cores; parallel circuits; port entry/exit; editor
drag/resize/Fit; draft-safe replay; initial save; and live animation lifecycle.

Browser verification on 2026-09-15 used the actual embed scripts with sample
data at 1600×1000 and 320×640: moving overlay pixels, aligned canvases, Flow
toggle and Fit all passed. It did not connect to a production LibreNMS instance.
PHP discovery regressions must run in the PHP/LibreNMS CI environment; the local
Windows environment had no PHP executable or running Docker engine.


Traffic flow respects reduced-motion preferences on initial load; explicitly enabling
Flow opts into measured directional animation. Idle links do not animate.
Utilization uses the configured link capacity, or the slower known LibreNMS port
ifSpeed when no capacity is configured. Gray / Unknown means utilization cannot
be calculated; known 0% utilization is cyan. Changing the label metric does not
change the full-duplex utilization calculation (maximum of receive/transmit).


Dashboard mode: choose Display settings > View mode > Dashboard - hidden names,
or append `dashboard=1` to the embed URL. The URL retains the mode on refresh and
can be used in an iframe. Device names and IP labels are omitted from the canvas;
inspectors and alerts use the generic label Device. Saved labels and Operations
view remain unchanged. Metrics and traffic remain visible. Embedded LibreNMS
graph images are replaced with the local trend because external graph titles may
contain addresses or names. This is a presentation mode, not access control:
the underlying map payload still contains device data.

RX/TX are relative to the source port. Equal values can occur in real traffic, but
identical endpoint port IDs previously forced both to the larger measurement.
The same port is now counted once. The RRD series parser also leaves an unknown
column unavailable instead of silently reading the first column for both directions.


Port graph parity: live link rates use source port A's RX/TX rather than maxima
across independently polled endpoints. With only B configured, flow directions
are reversed into the source perspective; the inspector reverses them back to
match B's graph. LibreNMS INOCTETS/OUTOCTETS rates are converted from octets/s to
bits/s once at the RrdDataService boundary, including historical reads. Compare
the same port's In/Out **Now** values, not its 15-minute average or maximum.
Graph consolidation and refresh timing can still produce small differences.
Reference: https://github.com/librenms/librenms/blob/master/includes/html/graphs/generic_data.inc.php


For a live mismatch, run `php bin/diagnose-port-traffic.php PORT_ID` as the LibreNMS
user from the plugin checkout (optional second argument: LibreNMS root, default
`/opt/librenms`). It reports both endpoint IDs, the selected graph port, raw RRD
columns and recent numeric rows, octet/s and bit/s readings, and the pre-existing
traffic cache. No device names, IP addresses or full RRD paths are printed.
It does not modify map/config data; normal traffic reads may populate the cache.
The CLI report cannot prove what an already-running PHP web worker has loaded.
