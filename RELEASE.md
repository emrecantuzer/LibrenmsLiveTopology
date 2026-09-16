# Releasing LibreLiveTopology

This is the release procedure, not an announcement of an already published release. The current package version is `1.12.0`. Validate the selected commit before publishing a release.

## Validate the release candidate

Run from the repository root with PHP and Composer available:

```bash
composer install
composer validate --no-check-publish
node --test tests/*.test.cjs
vendor/bin/phpunit --no-coverage
php tests/rrd-parser-regression.php
php bin/validate-map-config.php config/maps/example.conf
```

For container commands, see [Local development](docs/LOCAL_DEVELOPMENT.md).

Check both **CI** and **Installation Tests** for the exact candidate commit. Review skipped tests and distinguish a skipped integration check from a passing check. The scheduled LibreNMS smoke job may need a manual workflow run.

Before testing an archive, confirm Git records the installer's executable permission:

```bash
git ls-files --stage quick-install.sh
```

The mode must be `100755`. If it is `100644`, run `git update-index --chmod=+x quick-install.sh` and commit the change. A ZIP extracted on Windows and committed into a fresh repository may lose this permission.

## Manual release checks

- Install into a clean LibreNMS instance using the published instructions.
- Create a map, add nodes and links, save, reload, and verify the topology persists.
- Exercise undo/redo, import/export, snapshots, and restore.
- Check viewer updates and traffic values against real monitoring data; demo data alone is insufficient.
- Verify administrator editing and authenticated viewing with separate accounts.
- Record tested software versions and any remaining limitations.
- Review the archive for local credentials, private data, and unwanted files.

For an upgrade test, back up the database first and run `php database/setup.php` from the plugin directory after installing dependencies. Only advertise upgrade paths that have actually been tested. There is no automatic migration from the previous plugin identity.

## Prepare version metadata

Choose the next release version based on compatibility, including the changed plugin paths, configuration keys, and database identity. Do not publish the rename as a compatible patch to the old plugin.

Update these together:

- `VERSION`: the package version.
- `composer.json`: the same `version` value.
- `CHANGELOG.md`: a dated `## [<version>] - YYYY-MM-DD` section describing this project's changes.

Move completed items from `Unreleased` into that section and keep an empty `Unreleased` section for subsequent work. Describe only changes included in the release candidate.

## Publish

After validation and review, tag the reviewed commit:

```bash
git tag v<version>
git push origin v<version>
```

The **Release** workflow runs on version tags. It checks metadata, extracts the matching changelog section, creates source archives, and publishes the GitHub release. Review that workflow after tagging; it cannot be required to pass before its triggering tag exists.

See [Versioning](VERSIONING.md) for the distinction between package releases and map snapshots.
