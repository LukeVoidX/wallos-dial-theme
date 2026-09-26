# Updating and rollback

Dial `0.1.0` targets Wallos `5.8.1` and pins the upstream Docker image by digest. A new Wallos image does not automatically carry this theme. Changing the `FROM` line without checking the overlaid PHP templates, scripts, database expectations, and styles may break features.

## Before an update

1. Record the currently running Dial image tag and Wallos version.
2. Make a SQLite backup with `sqlite3 /path/to/wallos.db ".backup '/safe/path/wallos.db'"` and archive uploaded logos.
3. Build the candidate against a disposable copy of the database and logos.
4. Check login, dashboard, subscription create/edit/detail, search/filter/grouping, calendar, statistics, settings, and notifications. Confirm the database integrity check returns `ok`.
5. Only then replace the running container.

## Rollback

Stop the candidate, restore the previous Compose image reference, and start the previous container with the same persistent volumes. If the upstream version ran a schema migration, restore the matching pre-upgrade database backup as well. Never copy a database backward across unknown schema changes.

The package intentionally has no auto-update job and does not modify a live server by itself.
