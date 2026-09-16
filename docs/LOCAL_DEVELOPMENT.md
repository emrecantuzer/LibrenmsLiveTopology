# Local development

Run commands from the repository root. Docker Desktop must be running on Windows; Linux users need Docker Engine and Compose.

## Start LibreNMS

```bash
docker compose -p librelivetopology -f docker-compose.dev.yml up -d db redis librenms
docker compose -p librelivetopology -f docker-compose.dev.yml logs -f librenms
```

Wait until nginx and PHP-FPM are ready. The web service binds to `127.0.0.1:8000`; the database and Redis are internal to the Docker network. The development stack enables simulated traffic and uses disposable development database credentials.

## Register the plugin

```bash
docker compose -p librelivetopology -f docker-compose.dev.yml exec librenms bash /opt/librenms/html/plugins/LibreLiveTopology/quick-install.sh
```

Create your local administrator interactively:

```bash
docker compose -p librelivetopology -f docker-compose.dev.yml exec --user librenms librenms php /opt/librenms/lnms user:add --role=admin
```

Seed the bundled demo topology:

```bash
docker compose -p librelivetopology -f docker-compose.dev.yml exec --user librenms librenms php /opt/librenms/html/plugins/LibreLiveTopology/database/seed-demo.php
```

Open [LibreNMS](http://localhost:8000), sign in, and visit [the map gallery](http://localhost:8000/plugin/LibreLiveTopology). Demo traffic is synthetic and does not verify a real SNMP/RRD data pipeline.

## Screenshots

The README images live in `docs/screenshots/`. Capture the overview, editor, and dashboard from the running application using a demo map. Use a consistent desktop viewport and avoid capturing account menus or credentials. Commit the PNG files together with the README.

## Tests

```bash
node --test tests/*.test.cjs
docker compose -p librelivetopology -f docker-compose.dev.yml exec -w /opt/librenms/html/plugins/LibreLiveTopology librenms composer install
docker compose -p librelivetopology -f docker-compose.dev.yml exec -w /opt/librenms/html/plugins/LibreLiveTopology librenms vendor/bin/phpunit
```

## Stop and resume

```bash
docker compose -p librelivetopology -f docker-compose.dev.yml stop
docker compose -p librelivetopology -f docker-compose.dev.yml start
```

Database and application state remain in named Docker volumes.
