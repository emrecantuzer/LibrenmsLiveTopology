# LibreLiveTopology Changelog

This file tracks changes maintained in this repository. Package version numbers are defined in `VERSION` and `composer.json`; a changelog entry alone does not mean a GitHub release has been published.

## [Unreleased]

- Introduce a light Network Atlas design for the live viewer and dashboard, with violet accents, flat device cards, and higher-contrast traffic colors.
- Refresh the live viewer and dashboard screenshots.
- Correct the environment variables used by Docker Compose validation in CI.
- Refresh installation, deployment, roadmap, and release documentation.
- Remove obsolete release narratives and unverified performance claims from the documentation.

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
