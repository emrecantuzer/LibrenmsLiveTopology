# LibreLiveTopology Versioning

## Package releases

`VERSION` and the `version` field in `composer.json` define the current package version. The current value is **1.12.0**. [CHANGELOG.md](CHANGELOG.md) records the project baseline and subsequent changes.

Release tags use `v<version>` and must match both metadata files and a corresponding changelog section. See [Release procedure](RELEASE.md) before creating a tag.

- **Patch:** compatible fixes and documentation corrections.
- **Minor:** compatible new functionality.
- **Major:** incompatible changes to installation, API, configuration, or data behavior.

The rename changes runtime identifiers, paths, configuration keys, and database tables. A matching numeric baseline does not make the previous plugin's data or configuration interchangeable. Fresh installations are the current documented path; migration remains planned work in [ROADMAP.md](ROADMAP.md).

## Map snapshots

Map snapshots are independent of package releases. They are stored in `llt_map_versions` and capture map settings, nodes, and links, together with a name, optional description, timestamp, and creator reference when available.

The editor's Version History interface and the registered routes support:

- Saving and listing named snapshots.
- Restoring a saved snapshot.
- Comparing snapshots.
- Deleting snapshots and exporting version history.

`routes/web.php` defines the current routes; [API.md](API.md) documents the API. Editing and version mutations require administrator access. Snapshot storage is created by `database/setup.php`.

## Operational guidance

Save a named snapshot before major topology changes and include a short description. Restoring a snapshot changes the map; review the selected version first. Keep database backups that include maps, nodes, links, and snapshot tables. A map snapshot does not replace a complete database backup or migrate data between plugin identities.

Additional retention controls and migration support are future work, not guarantees of the current release.
