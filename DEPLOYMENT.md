# LibreLiveTopology Deployment Guide

This guide covers production deployment and maintenance for LibreLiveTopology .

For a first install, start with [INSTALL.md](INSTALL.md). This document focuses on production checks, Docker notes, monitoring, backup, and recovery.

## Requirements

- LibreNMS latest stable release
- PHP 8.2 or newer
- Composer
- MySQL or MariaDB using the existing LibreNMS database
- PHP extensions required by LibreNMS plus `gd`, `json`, and `mbstring`
- Read access to LibreNMS RRD files
- Write access to LibreLiveTopology output directories

LibreLiveTopology is a LibreNMS v2 Composer-discovered plugin. It does not use the legacy manifest or root-level route registration model.

## Production Install Flow

The supported install flow is:

```bash
cd /opt/librenms/html/plugins
git clone <repository-url> LibreLiveTopology
chown -R librenms:librenms /opt/librenms/html/plugins/LibreLiveTopology
sudo -u librenms -H bash -lc 'cd /opt/librenms/html/plugins/LibreLiveTopology && ./quick-install.sh'
```

The installer performs the important steps:

- Installs Composer dependencies
- Registers the plugin as a Composer path package from the LibreNMS root
- Runs package discovery
- Creates or updates LibreLiveTopology database tables
- Clears Laravel caches
- Creates required output directories
- Enables the LibreNMS plugin
- Verifies LibreLiveTopology routes are visible

## Manual Production Flow

Use this when you need more control than `quick-install.sh` gives you:

```bash
cd /opt/librenms/html/plugins
git clone <repository-url> LibreLiveTopology
cd LibreLiveTopology
composer install --no-dev --optimize-autoloader

cd /opt/librenms
composer config repositories.librelivetopology '{"type":"path","url":"html/plugins/LibreLiveTopology","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/librelivetopology:*' --with-dependencies --no-interaction
php artisan package:discover

cd /opt/librenms/html/plugins/LibreLiveTopology
php database/setup.php

cd /opt/librenms
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
./lnms plugin:enable LibreLiveTopology
php artisan route:list | grep -iE 'librelivetopology|llt'
```

## Docker Deployment

Mount the plugin into the LibreNMS container at the expected plugin path:

```yaml
services:
  librenms:
    volumes:
      - /path/to/LibreLiveTopology:/opt/librenms/html/plugins/LibreLiveTopology:rw
```

Then run setup as the `librenms` user inside the container:

```bash
docker exec -u librenms <container> composer install -d /opt/librenms/html/plugins/LibreLiveTopology --no-dev --optimize-autoloader
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && composer config repositories.librelivetopology "{\"type\":\"path\",\"url\":\"html/plugins/LibreLiveTopology\",\"options\":{\"symlink\":true}}"'
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && FORCE=1 composer require "librenms/librelivetopology:*" --with-dependencies --no-interaction'
docker exec -u librenms <container> php /opt/librenms/artisan package:discover
docker exec -u librenms <container> php /opt/librenms/html/plugins/LibreLiveTopology/database/setup.php
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && php artisan optimize:clear && php artisan route:clear && php artisan view:clear && php artisan config:clear && php artisan cache:clear'
docker exec -u librenms <container> /opt/librenms/lnms plugin:enable LibreLiveTopology
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && php artisan route:list | grep -iE "librelivetopology|llt"'
```

Always run these commands as the same user LibreNMS uses. Avoid running setup as root unless you immediately repair ownership.

## Upgrade Flow

```bash
cd /opt/librenms/html/plugins/LibreLiveTopology
git pull
composer install --no-dev --optimize-autoloader
php database/setup.php

cd /opt/librenms
FORCE=1 composer require 'librenms/librelivetopology:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:list | grep -iE 'librelivetopology|llt'
```

Read [CHANGELOG.md](CHANGELOG.md) before upgrading across minor versions. Do not drop `llt_*` tables unless you intend to remove LibreLiveTopology data.

## Health And Readiness

Public probe endpoints:

```bash
curl -f https://librenms.example.com/plugin/LibreLiveTopology/health
curl -f https://librenms.example.com/plugin/LibreLiveTopology/ready
curl -f https://librenms.example.com/plugin/LibreLiveTopology/live
```

Authenticated admin/detail endpoints:

- `/plugin/LibreLiveTopology/health/stats`
- `/plugin/LibreLiveTopology/health/detailed`
- `/plugin/LibreLiveTopology/metrics`

The detail endpoints can expose operational information, so they are intentionally behind LibreNMS authentication.

## Verification Checklist

After install or upgrade:

1. Run `php artisan route:list | grep -iE 'librelivetopology|llt'` from `/opt/librenms`.
2. Confirm the LibreNMS menu shows LibreLiveTopology or Network Maps.
3. Visit `/plugin/LibreLiveTopology`.
4. Create or open a map.
5. Open the editor.
6. Open the embed view.
7. Check `/plugin/LibreLiveTopology/ready`.
8. Run LibreNMS `validate.php`; ignore `llt_*` extra-table warnings unless you are uninstalling.
9. If `validate.php` reports `utf8mb4_bin` collation on `llt_map_templates.config`, `llt_nodes.meta`, `llt_maps.options`, or `llt_links.style`, treat that as expected JSON-column behavior.

If an older deployment shows duplicate `LibreLiveTopology` rows in the LibreNMS `plugins` table, rerun `quick-install.sh` or `php database/setup.php` as the `librenms` user. Both normalize plugin registration: keep one active `version=2` row, promote legacy `version=1` rows, and remove stale duplicates.

## Background Poller

LibreLiveTopology can render live data from LibreNMS RRD files on demand. The optional poller script remains available for environments that want scheduled background work:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/LibreLiveTopology/bin/map-poller.php >> /var/log/librenms/librelivetopology.log 2>&1
```

Use the poller only if it fits your deployment model. The web views and live endpoints should still be validated separately.

## Backup And Recovery

Back up the LibreLiveTopology tables with the rest of the LibreNMS database:

```bash
mysqldump -u librenms -p librenms \
  llt_maps llt_nodes llt_links llt_map_templates llt_map_versions \
  > librelivetopology.sql
```

Back up generated output if you rely on exported images or thumbnails:

```bash
tar -czf librelivetopology-output.tgz /opt/librenms/html/plugins/LibreLiveTopology/output
```

Recovery is the reverse:

```bash
mysql -u librenms -p librenms < librelivetopology.sql
tar -xzf librelivetopology-output.tgz -C /
cd /opt/librenms
php artisan optimize:clear
php artisan package:discover
```

## Troubleshooting

### Routes Missing

If `/plugin/LibreLiveTopology` loads partially or routes such as editor/API/health are missing, Composer package discovery did not see the plugin:

```bash
cd /opt/librenms
composer config repositories.librelivetopology '{"type":"path","url":"html/plugins/LibreLiveTopology","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/librelivetopology:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:list | grep -iE 'librelivetopology|llt'
```

### Permission Problems

```bash
chown -R librenms:librenms /opt/librenms/html/plugins/LibreLiveTopology
find /opt/librenms/html/plugins/LibreLiveTopology/bin -type f -name '*.php' -exec chmod +x {} \;
```

### Database Setup Problems

```bash
cd /opt/librenms/html/plugins/LibreLiveTopology
php database/setup.php
```

`database/setup.php` is the supported setup path for plugin tables. Do not use Laravel's application migration commands for plugin table setup unless a future release explicitly documents that as the supported install path.

### RRD Or Traffic Problems

- Verify the link has valid LibreNMS port associations.
- Check that the LibreNMS user can read the RRD directory.
- Use demo mode to separate UI problems from data-source problems.
- Confirm the map renders before debugging flow animation or labels.

## Security Notes

- Keep LibreLiveTopology under LibreNMS authentication for editor, map management, metrics, and detailed health data.
- Public health endpoints should remain minimal.
- Keep file ownership aligned with the LibreNMS runtime user.
- Do not expose backup files, `.env`, logs, or SQL dumps under the web root.

### Authorization Model

LibreLiveTopology enforces a two-tier authorization model on top of LibreNMS `web` + `auth` middleware:

- **Read endpoints** are open to any authenticated LibreNMS user. This includes viewing maps and the editor, embed, JSON and image export, the live data endpoint, the SSE stream, device/port lookups, template listings, and the `health/detailed`, `health/stats`, and `metrics` endpoints.
- **Mutation endpoints** require an admin user — `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`. This covers creating, updating, and deleting maps, nodes, and links; saving a map; importing a map; running auto-discovery; creating, updating, and deleting templates; creating a map from a template; and running the install controller.

The three public probe endpoints — `/health`, `/ready`, and `/live` — are intentionally unauthenticated so external health checks can reach them.

Authorization is enforced at the controller level using LibreNMS administrator checks. There is no per-map ownership configuration.

## Compatibility

| LibreLiveTopology | LibreNMS | PHP | Database |
|--------------|----------|-----|----------|
| Current development baseline | Verify against the target installation | 8.2+ | LibreNMS MySQL/MariaDB database |

PostgreSQL is not currently documented as a supported production target for this plugin.
