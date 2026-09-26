# Updating and rollback

Dial `0.1.0` targets Wallos `5.8.1` and pins the upstream Docker image by digest. End users pull a versioned Dial image; they do not need to build it. A new Wallos image does not automatically carry this theme. Changing the `FROM` line without checking the overlaid PHP templates, scripts, database expectations, and styles may break features.

## Before an update

1. Record the currently running Dial image tag and Wallos version.
2. Make a SQLite backup with `sqlite3 /path/to/wallos.db ".backup '/safe/path/wallos.db'"` and archive uploaded logos.
3. Build the candidate against a disposable copy of the database and logos.
4. Check login, dashboard, subscription create/edit/detail, search/filter/grouping, calendar, statistics, settings, and notifications. Confirm the database integrity check returns `ok`.
5. Only then replace the running container.

## Rollback

Stop the candidate, restore the previous Compose image reference, and start the previous container with the same persistent volumes. If the upstream version ran a schema migration, restore the matching pre-upgrade database backup as well. Never copy a database backward across unknown schema changes.

The package intentionally has no auto-update job and does not modify a live server by itself.

## Compatibility process for maintainers

1. The weekly `check-upstream.yml` workflow flags a new Wallos release. This is a notification, not approval to update users.
2. Change `WALLOS_VERSION` and the pinned upstream image digest in `Dockerfile` on a branch. Run the build. `compat/upstream-files.sha256` checks every overwritten upstream file **before** the overlay is copied and rejects changed files.
3. Review upstream diffs for all changed files and adjust the Dial overlay. Update the compatibility hashes only after that review; a green hash check by itself cannot prove every behavior works.
4. Run package checks, image smoke, and a disposable authenticated browser pass for dashboard, subscriptions, create/edit/details, search/filter/grouping, calendar, statistics, settings, and notifications. Check both an empty account and a populated account.
5. Publish a new versioned image only when the new Wallos version is verified. Update the compatibility table in README. Keep the previous image available for rollback.

No general guarantee can be made for an upstream release that has not yet happened. The version pin protects working installations from an untested update.
