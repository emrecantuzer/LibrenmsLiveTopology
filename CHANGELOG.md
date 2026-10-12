# LibreLiveTopology Changelog

This file tracks changes maintained in this repository. Package version numbers are defined in `VERSION` and `composer.json`; a changelog entry alone does not mean a GitHub release has been published.

## [Unreleased]

## [1.13.0] - 2026-10-12

### Added

- `database/seed-demo.php` also creates `enterprise-datacenter`, a 31-device demo with dual ISPs, MPLS branches, a firewall HA pair, a load balancer, a spine-leaf data center, and a campus, using generic device names. Existing demo maps are skipped instead of stopping the seeder.
- Snapshot retention: each map keeps its newest `version_retention` snapshots (default 20, env `LIBRELIVETOPOLOGY_VERSION_RETENTION`, `0` keeps all). Older snapshots are pruned when a new one is saved.
- Restoring a snapshot first saves the current map as a "Before restore: …" snapshot, so a restore can be undone.
- Regression tests for replay traffic, snapshot retention, and source integrity (facade imports, logger helpers, lookup authorization).

### Changed

- The version list response reports the real retention limit (`max_versions`, `null` when unlimited) and `backup_before_restore`, replacing hard-coded capability flags that did not reflect behavior.
- Introduce a light Network Atlas design for the live viewer and dashboard, with violet accents, flat device cards, and higher-contrast traffic colors.
- Refresh the live viewer, editor, and dashboard screenshots and add enterprise topology, map gallery, and version history screenshots.
- Document map visibility, snapshot retention, and their environment variables in the README.
- Correct the environment variables used by Docker Compose validation in CI.
- Refresh installation, deployment, roadmap, and release documentation.
- Remove obsolete release narratives and unverified performance claims from the documentation.

### Fixed

- Creating a map from a template no longer fails with a missing-class error when the transaction fails (`Log` facade was not imported).
- A failed map import returns the intended JSON 500 response instead of calling an undefined `getLogger()` method.
- The plugin index can redirect to the installer; the `View` return type previously turned that redirect into a TypeError. The installation check now uses the schema builder instead of MySQL-only `SHOW TABLES`.
- Opening the editor for a non-existent map returns 404 instead of an empty editor. The editor now reads the map's stored width, height, and tags instead of always showing defaults.
- Historical replay uses the same link rules as live data: one source endpoint (B reversed when A is absent), `max(in, out)` utilization, and port speed as the bandwidth fallback. Links without a known bandwidth no longer show 100%.
- Node and link ownership checks cast `map_id` to integer so drivers that return strings do not reject valid edits.
- Version history lists snapshots saved in the same second newest first, and replay picks the newest of them.

### Security

- Map visibility now follows LibreNMS device permissions. Non-admin users without global read only see maps whose every device (node devices and link port devices) they can access; other maps are omitted from the list, menu count, and kiosk cycle and return 403. Set `LIBRELIVETOPOLOGY_ACCESS_MODE=all` to keep the previous behavior, or `admin` to restrict maps to admins and global-read users.
- Device and port lookup endpoints now require administrator access, matching API.md. They list every LibreNMS device regardless of per-device permissions and are only used by the editor.
- API.md now lists version-history reads and the detailed health, stats, and metrics endpoints as admin-only, matching the code.

## [1.12.0] - Project baseline

### Included

- LibreNMS plugin integration under the LibreLiveTopology name.
- Visual topology editing, device and link placement, waypoints, and automatic layout.
- Live traffic views, dashboard embedding, and demo traffic for local evaluation.
- Map templates, JSON import/export, and named map snapshots.
- Docker development setup and automated PHP and JavaScript checks.
- README screenshots captured from a local demo installation.

### Installation

Install into `html/plugins/LibreLiveTopology` using the `librenms/librelivetopology` Composer package. Plugin data uses `llt_*` tables. The documented setup is for fresh installations; automatic migration from other plugins is not provided.

### Validation limits

Demo rendering and automated tests do not establish production performance or compatibility with every LibreNMS deployment. See [RELEASE.md](RELEASE.md) for release validation and [ROADMAP.md](ROADMAP.md) for outstanding work.
