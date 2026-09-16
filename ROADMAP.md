# LibreLiveTopology Roadmap

This roadmap describes the next work for LibreLiveTopology. It is a priority list, not a release schedule. Completed changes belong in [CHANGELOG.md](CHANGELOG.md).

## Current status

The current development baseline is **1.12.0**. Release readiness is tracked separately from the package version; see [RELEASE.md](RELEASE.md).

Implemented capabilities include:

- LibreNMS plugin installation, authenticated map access, and administrator editing.
- Canvas editing with nodes, links, waypoints, undo/redo, and automatic layout.
- RRD traffic integration, live updates, and an embedded NOC viewer.
- Map templates, JSON import/export, and named map snapshots.
- LLDP/CDP discovery and installation diagnostics.

Local validation has covered installation, demo rendering, and creating, saving, reading, and deleting a test map. Demo screenshots use simulated traffic. This is not a complete production compatibility or performance assessment.

The new plugin uses the `LibreLiveTopology` directory, `librenms/librelivetopology` Composer package, and `llt_*` database tables. Existing installations under the old identity need a separately designed migration.

## Next priorities

### 1. Reproducible installation and release

- [ ] Verify CI and Installation Tests on the exact commit selected for release.
- [ ] Repeat a clean installation from the published source archive on Linux.
- [ ] Verify executable script permissions and document Windows-to-GitHub publishing.
- [ ] Record the LibreNMS, PHP, and database versions used for release validation.

Completion means a new user can follow the installation guide and open a working map without undocumented fixes.

### 2. Real monitoring data validation

- [ ] Check RX/TX direction and units against representative LibreNMS RRD files.
- [ ] Verify missing, stale, idle, and down states against real device observations.
- [ ] Validate device/port association, discovery, and alerts in a representative deployment.

Completion means the displayed values and status can be traced back to the same source data in LibreNMS.

### 3. Editor and viewer reliability

- [ ] Expand browser coverage for create/save/reload, import/export, undo, and version restore.
- [ ] Check replay transitions preserve unsaved changes under delayed responses.
- [ ] Review readability and interaction on small screens and dense maps.
- [ ] Measure rendering cost with representative topologies before publishing scale claims.

### 4. Migration and operational documentation

- [ ] Define whether to support importing maps from the previous plugin identity.
- [ ] Specify backup, migration, validation, and rollback steps before implementing a migration.
- [ ] Document tested upgrade paths between future LibreLiveTopology releases.

## Ideas to evaluate

Site/rack grouping, focused neighborhood views, path highlighting, and snapshot retention controls may be useful follow-up work. They are not committed features or dated promises.

Report reproducible bugs and proposals in this repository's Issues tab. Include steps, expected behavior, and sanitized examples.
