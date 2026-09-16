# LibreLiveTopology API And Route Reference

Routes are registered through `routes/web.php` when LibreNMS discovers the Composer package provider. If routes are missing, run package discovery from the LibreNMS root and verify with:

```bash
cd /opt/librenms
php artisan route:list | grep -iE 'librelivetopology|llt'
```

## Authentication & Authorization

All routes are registered inside LibreNMS `web` + `auth` middleware, so every request resolves against an authenticated LibreNMS session — except for three public probe routes. There is no per-map ownership: any authenticated user can read any map, and all write operations require an admin. The admin gate is enforced in each controller via the shared `AdminCheck` trait, which accepts `hasGlobalAdmin()`, `isAdmin()`, or a LibreNMS `level >= 10` and otherwise `abort(403)`.

### Public probe routes (no auth)

These sit outside the `auth` group and are intentionally minimal so they can back health checks and load balancers:

- `GET /plugin/LibreLiveTopology/health`
- `GET /plugin/LibreLiveTopology/ready`
- `GET /plugin/LibreLiveTopology/live`

### Read routes (open to all authenticated users)

`GET` map index/show/editor/view/embed/json/live/sse/export, `GET templates` index/show, and `GET /health/detailed`, `/health/stats`, `/metrics`. None of these call `requireAdmin()`. Device and port lookups (`GET /api/devices`, `GET /api/device/{id}/ports`) are admin-only — they expose device IPs and topology data.

### Admin-only routes (require `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`)

Every `POST`, `PUT`, `PATCH`, and `DELETE` endpoint calls `requireAdmin()` at the top of the controller action and `abort(403)` for non-admins. This covers all map CRUD, node CRUD, link CRUD, template CRUD, full-map save, import, auto-discovery, and the install runner. The `GET /plugin/LibreLiveTopology/install` UI is authenticated (it sits in the `auth` group); its `POST` counterpart is admin-only.

## Page Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/LibreLiveTopology` | Map index |
| `GET` | `/plugin/LibreLiveTopology/install` | Installer UI |
| `POST` | `/plugin/LibreLiveTopology/install` | Run installer |
| `GET` | `/plugin/LibreLiveTopology/editor/{map?}` | Editor for an existing or new map |
| `GET` | `/plugin/LibreLiveTopology/view/{map}` | Full map view |
| `GET` | `/plugin/LibreLiveTopology/embed/{map}` | Embed viewer |

## Map Data Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/json` | Serialized map model |
| `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/live` | Current traffic/status payload |
| `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/sse` | Server-Sent Events live stream |
| `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/export` | Export a map |
| `POST` | `/plugin/LibreLiveTopology/api/import` | Import a map |
| `POST` | `/plugin/LibreLiveTopology/api/maps/{map}/save` | Save full editor state |

Example live payload shape:

```json
{
  "ts": 1738284000,
  "links": {
    "1": {
      "in": 52428800,
      "out": 104857600,
      "in_perc": 33,
      "out_perc": 67
    }
  },
  "nodes": {
    "1": {
      "status": "up",
      "alerts": {
        "count": 0,
        "severity": "ok"
      }
    }
  }
}
```

## Lookup Routes (admin-only)

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/LibreLiveTopology/api/devices` | Device lookup for the editor (admin) |
| `GET` | `/plugin/LibreLiveTopology/api/device/{id}/ports` | Ports for one LibreNMS device (admin) |

## Map Management Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/LibreLiveTopology/map` | Create a map |
| `PUT` | `/plugin/LibreLiveTopology/map/{map}` | Update map metadata |
| `DELETE` | `/plugin/LibreLiveTopology/map/{map}` | Delete a map |
| `POST` | `/plugin/LibreLiveTopology/map/{map}/autodiscover` | Run current auto-discovery flow |

Create map request:

```json
{
  "name": "production_map",
  "title": "Production Network Map",
  "width": 1200,
  "height": 800
}
```

Typical response:

```json
{
  "success": true,
  "map": {
    "id": 1,
    "name": "production_map",
    "title": "Production Network Map",
    "width": 1200,
    "height": 800
  },
  "redirect": "https://librenms/plugin/LibreLiveTopology/editor/1"
}
```

## Node Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/LibreLiveTopology/map/{map}/nodes` | Store node through collection route |
| `POST` | `/plugin/LibreLiveTopology/map/{map}/node` | Create one node |
| `PATCH` | `/plugin/LibreLiveTopology/map/{map}/node/{node}` | Update one node |
| `DELETE` | `/plugin/LibreLiveTopology/map/{map}/node/{node}` | Delete one node |

Create node request:

```json
{
  "label": "Core Router",
  "x": 400,
  "y": 300,
  "device_id": 42
}
```

## Link Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/LibreLiveTopology/map/{map}/links` | Store link through collection route |
| `POST` | `/plugin/LibreLiveTopology/map/{map}/link` | Create one link |
| `PATCH` | `/plugin/LibreLiveTopology/map/{map}/link/{link}` | Update one link |
| `DELETE` | `/plugin/LibreLiveTopology/map/{map}/link/{link}` | Delete one link |

Create link request:

```json
{
  "src_node_id": 1,
  "dst_node_id": 2,
  "port_id_a": 101,
  "port_id_b": 102,
  "bandwidth_bps": 1000000000,
  "style": {
    "via_style": "angled",
    "via_points": [
      {"x": 500, "y": 240}
    ]
  }
}
```

## Template Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/LibreLiveTopology/templates` | List templates |
| `GET` | `/plugin/LibreLiveTopology/templates/{id}` | Show one template |
| `POST` | `/plugin/LibreLiveTopology/templates` | Create a template |
| `PUT` | `/plugin/LibreLiveTopology/templates/{id}` | Update a template |
| `DELETE` | `/plugin/LibreLiveTopology/templates/{id}` | Delete a template |
| `POST` | `/plugin/LibreLiveTopology/templates/{id}/create-map` | Create a map from a template |

## Health And Metrics Routes

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Public | `GET` | `/plugin/LibreLiveTopology/health` | Basic health |
| Public | `GET` | `/plugin/LibreLiveTopology/ready` | Readiness probe |
| Public | `GET` | `/plugin/LibreLiveTopology/live` | Basic liveness |
| Authenticated | `GET` | `/plugin/LibreLiveTopology/health/detailed` | Detailed health |
| Authenticated | `GET` | `/plugin/LibreLiveTopology/health/stats` | Plugin stats |
| Authenticated | `GET` | `/plugin/LibreLiveTopology/metrics` | Prometheus-style metrics |

## Version History Routes

Version routes are registered in `routes/web.php` under the `web` + `auth` middleware group. All mutating endpoints require admin via `requireAdmin()`.

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Authenticated | `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/versions` | List versions for a map |
| Admin | `POST` | `/plugin/LibreLiveTopology/api/maps/{map}/versions` | Create a named version |
| Authenticated | `GET` | `/plugin/LibreLiveTopology/api/versions/{versionId}` | Show version details + snapshot |
| Admin | `POST` | `/plugin/LibreLiveTopology/api/versions/{versionId}/restore` | Restore map to a version |
| Admin | `GET` | `/plugin/LibreLiveTopology/api/versions/{versionId}/compare/{compareId}` | Compare two versions (returns flat diff: `nodes_added`, `nodes_removed`, `nodes_modified`, `links_added`, `links_removed`, `links_modified`) |
| Admin | `DELETE` | `/plugin/LibreLiveTopology/api/versions/{versionId}` | Delete a single version |
| Admin | `GET` | `/plugin/LibreLiveTopology/api/maps/{map}/versions/export` | Export all versions as JSON |

Diff direction: `compare(v1, v2)` reports what changed going from v1 → v2. `nodes_added` = IDs in v2 but not v1.

## Error Shape

Most JSON write endpoints return a success flag and a message on errors:

```json
{
  "success": false,
  "message": "Map not found"
}
```

Validation errors may include field-level details depending on the controller/request class.
