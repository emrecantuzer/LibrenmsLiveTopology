# Contributing to LibreLiveTopology

Thanks for helping improve LibreLiveTopology. The project is a LibreNMS v2 plugin, so changes should be tested both as a PHP package and inside a LibreNMS plugin install when possible.

## Requirements

- PHP 8.2 or newer
- Composer
- Git
- Docker (required for running tests and lint — PHP can run inside the container; the LibreNMS image provides the matching PHP runtime)
- LibreNMS development or test instance for UI/install validation

## Development Setup

> The plugin runs inside LibreNMS. A local PHP installation is optional when using Docker. Use the LibreNMS Docker image to run PHP tooling (tests, lint, composer) so the environment matches production exactly.

### Local Package Setup

```bash
git clone <repository-url> LibreLiveTopology
cd LibreLiveTopology
composer install
composer validate --no-check-publish
```

### Running Tests

If PHP is unavailable on your host — run the suite through the LibreNMS Docker image:

```bash
docker run --rm --entrypoint php \
  -v "$PWD":/opt/librenms/html/plugins/LibreLiveTopology \
  -w /opt/librenms/html/plugins/LibreLiveTopology \
  librenms/librenms:latest \
  vendor/bin/phpunit --no-coverage
```

Run the current suite to see test totals and skips. Some integration tests require a full LibreNMS database. Add regression tests for behavior changes.

### LibreNMS Plugin Setup

For manual local integration testing, install the checkout under LibreNMS:

```bash
cd /opt/librenms/html/plugins
git clone /path/to/your/LibreLiveTopology LibreLiveTopology
cd LibreLiveTopology
composer install

cd /opt/librenms
composer config repositories.librelivetopology '{"type":"path","url":"html/plugins/LibreLiveTopology","options":{"symlink":true}}'
composer require 'librenms/librelivetopology:*' --with-dependencies
php artisan package:discover

cd /opt/librenms/html/plugins/LibreLiveTopology
php database/setup.php

cd /opt/librenms
php artisan optimize:clear
php artisan route:list | grep -iE 'librelivetopology|llt'
./lnms plugin:enable LibreLiveTopology
```

The Composer path package registration is required. Without it, Laravel will not discover the service provider, routes, and views reliably.

## Project Structure

```text
LibreLiveTopology/
├── composer.json                 # Package metadata and Laravel provider discovery
├── VERSION                       # Release version source used by runtime/tests
├── quick-install.sh              # Supported installer
├── routes/web.php                # Plugin routes
├── src/
│   ├── LibreLiveTopologyProvider.php  # Composer-discovered Laravel provider
│   ├── AdminCheck.php            # Shared admin-gate trait for controllers/hooks
│   ├── Hooks/                    # LibreNMS plugin hooks
│   ├── Http/Controllers/         # Web/API controllers
│   ├── Http/Requests/            # Request validation
│   ├── Models/                   # Eloquent models
│   ├── RRD/                      # RRD helpers
│   └── Services/                 # Core domain/services
├── resources/
│   ├── views/                    # Blade views
│   ├── js/                       # Browser-side JavaScript
│   └── css/                      # Stylesheets
├── database/
│   ├── setup.php                 # Supported table setup/upgrade entrypoint
│   ├── migrations/               # Schema history/reference
│   └── seeds/                    # Template/demo seeders
├── config/                       # Plugin configuration
├── bin/                          # Optional operational scripts
├── tests/                        # PHPUnit and install/documentation tests
└── .github/workflows/            # CI, install validation, release validation
```

## Coding Standards

- Follow PSR-12 for PHP.
- Prefer typed parameters and return types where they make behavior clearer.
- Keep controller actions thin and put reusable behavior in services.
- Preserve existing LibreNMS conventions and Bootstrap 4 compatibility.
- Use FormRequest classes for request validation when adding write endpoints.
- Avoid broad rewrites when a focused change will solve the problem.

## UI Standards

- Keep the UI practical and operator-focused.
- Preserve keyboard access and visible focus states.
- Add accessible names to icon-only controls.
- Test editor, embed, and index views at more than one viewport size when touching layout.
- Prefer existing Font Awesome/Bootstrap patterns already used by LibreNMS.

## Testing

Run the full suite with the LibreNMS Docker image (see Development Setup):

```bash
docker run --rm --entrypoint php \
  -v "$PWD":/opt/librenms/html/plugins/LibreLiveTopology \
  -w /opt/librenms/html/plugins/LibreLiveTopology \
  librenms/librenms:latest \
  vendor/bin/phpunit --no-coverage
```

The suite currently has 178 tests, 579 assertions, and 18 skipped tests. The skipped tests are pre-existing stubs that need a live Eloquent/DB connection and are skipped outside a full LibreNMS environment — they are not regressions. When you add a feature, add tests for it.

Useful focused checks:

```bash
docker run --rm --entrypoint php \
  -v "$PWD":/opt/librenms/html/plugins/LibreLiveTopology \
  -w /opt/librenms/html/plugins/LibreLiveTopology \
  librenms/librenms:latest \
  vendor/bin/phpunit tests/RoutesSmokeTest.php
```

## Linting

If PHP is unavailable on your host, so lint a file through the LibreNMS Docker image:

```bash
docker run --rm --entrypoint php \
  -v "$PWD":/opt/librenms/html/plugins/LibreLiveTopology \
  -w /opt/librenms/html/plugins/LibreLiveTopology \
  librenms/librenms:latest \
  -l <file>
```

Before release-oriented changes, also validate:

```bash
docker run --rm --entrypoint composer \
  -v "$PWD":/opt/librenms/html/plugins/LibreLiveTopology \
  -w /opt/librenms/html/plugins/LibreLiveTopology \
  librenms/librenms:latest \
  validate --no-check-publish
git diff --check
```

## Architecture

LibreLiveTopology is a LibreNMS v2 plugin that uses LibreNMS's hook-based architecture. The service provider is `src/LibreLiveTopologyProvider.php` (note: `LibreLiveTopologyServiceProvider.php` was an earlier name that has been removed — always reference `LibreLiveTopologyProvider.php`). The provider publishes `MenuEntry` and `Settings` hooks, merges plugin config, and registers routes/views through Composer package discovery.

## Authorization Model

All mutation (write) endpoints require an admin user; read endpoints are open to any authenticated user. Admin gating is implemented by the `AdminCheck` trait (`src/AdminCheck.php`), which checks `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`.

When you add a new mutation endpoint, import and use the trait and call `requireAdmin()` at the top of the method:

```php
use LibreNMS\Plugins\LibreLiveTopology\AdminCheck;

class MyController extends Controller
{
    use AdminCheck;

    public function store(Request $request): JsonResponse
    {
        $this->requireAdmin();
        // ...
    }
}
```

`requireAdmin()` aborts with 403 if the authenticated user is not an admin. Read-only endpoints do not need this guard.

## Documentation

Update docs when changing:

- Install, upgrade, or Composer discovery behavior
- Routes or API payloads
- Health/readiness/security boundaries
- Version metadata or release flow
- Editor, embed, or map-management workflows
- Supported PHP/LibreNMS requirements

## Pull Request Checklist

- [ ] Tests pass.
- [ ] Composer metadata validates.
- [ ] Install or route changes include docs and smoke-test updates.
- [ ] User-facing changes are reflected in README, INSTALL, API, or EMBED docs as appropriate.
- [ ] Versioned releases update `VERSION`, `composer.json`, and `CHANGELOG.md` together.
- [ ] UI changes account for keyboard access, focus states, and smaller viewports.

## Commit Messages

Use concise conventional-style commit messages where practical:

```text
fix: correct embed zoom controls
docs: refresh deployment guide
test: add version metadata guard
```

## Reporting Issues

Good bug reports include:

- LibreLiveTopology version
- LibreNMS version
- PHP version
- Install method: native, Docker, or other
- Relevant route output from `php artisan route:list | grep -iE 'librelivetopology|llt'`
- Logs, screenshots, or exact error text
- Whether LibreNMS `validate.php` reports anything relevant

## License

By contributing to LibreLiveTopology, you agree that your contributions will be licensed under the MIT License.
