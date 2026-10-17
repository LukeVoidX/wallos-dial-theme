# Updating and rollback

Wallos Dial 1.0.0 is a [versioned release](https://github.com/LukeVoidX/wallos-dial-theme/releases/tag/v1.0.0) for **Wallos 5.8.3**, using a digest-pinned source overlay. This guide covers a move from **Dial 0.1.1 / Wallos 5.8.1** to 1.0.0. Rehearse the published 1.0.0 image on disposable data copies before touching a live installation. The earlier 0.1.1 image remains the rollback image.

Dial is a Docker image, not an in-app Wallos theme selector. Wallos migrations and new Dial icon tables run when the new image starts. A newer upstream Wallos tag is not automatically compatible with this overlay.

## Locate both persistent volumes

The example [`compose.yaml`](../compose.yaml) mounts `${WALLOS_DB_DIR:-./data/db}` at `/var/www/html/db` and `${WALLOS_LOGOS_DIR:-./data/logos}` at `/var/www/html/images/uploads/logos`. Resolve your actual Compose file and environment first:

```bash
docker compose config
docker inspect wallos-dial --format '{{range .Mounts}}{{println .Source "→" .Destination}}{{end}}'
```

An existing installation may use a different container or Compose project name. Record its exact image reference, environment, port, proxy settings, database mount, Logo mount and optional private SVG mount. Do not assume the example paths match your server. **My Icons** in 1.0 live inside the SQLite database; original uploaded Wallos Logos and optional legacy SVGs live in the separate Logo volume. Back up **both** volumes. The private SVG map, if used, also needs its own backup.

## Preflight a disposable copy

1. Confirm the source installation is healthy and record the subscription count, category assignments, selected language, visible Logos, notification settings and working image tag.
2. Stop the old container for a consistent two-volume backup. Let the SQLite connection close before copying either volume. Keep the old container stopped through the cutover so two Wallos processes cannot write the same database.
3. Choose absolute paths outside the mounted volumes for the backup. The host needs the `sqlite3` and `tar` commands. Run the following in a Bash shell after replacing all three placeholders; it stops on any failed command. The `PRAGMA integrity_check` output must be `ok`.

   ```bash
   set -euo pipefail
   DIAL_DB_DIR=/absolute/path/to/current/db
   DIAL_LOGOS_DIR=/absolute/path/to/current/logos
   DIAL_BACKUP_DIR=/absolute/path/to/backup-before-dial-1.0
   mkdir -p "$DIAL_BACKUP_DIR"
   test "$(sqlite3 "$DIAL_DB_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   sqlite3 "$DIAL_DB_DIR/wallos.db" ".backup '$DIAL_BACKUP_DIR/wallos.db'"
   tar -C "$DIAL_LOGOS_DIR" -cf "$DIAL_BACKUP_DIR/logos.tar" .
   test "$(sqlite3 "$DIAL_BACKUP_DIR/wallos.db" 'PRAGMA integrity_check;')" = ok
   ```

4. Restore **copies** of `wallos.db` and `logos.tar` into a disposable directory, point the 1.0.0 image at those copies, and test login, category management, original Logos, icon selection, My Icons upload, add/edit/clone/delete, dashboard, calendar, statistics and settings. Check all five email types through a local SMTP capture, including HTML and plain text, before relying on notifications. Do not send test mail to real recipients.
   Dial skips and logs cancellation notices for a legacy subscription whose selected payer belongs to another account. Review those logs on the copy and set a valid payer through the subscription editor before relying on that reminder.
5. Stop that test container and start the **0.1.1** image against another copy of the pre-upgrade backup. Confirm the old UI sees the original Logos and can add/edit/delete a synthetic subscription. This is the rollback rehearsal; do not perform it on the live volumes.

## One-container cutover

After the disposable test passes, pull the published `ghcr.io/lukevoidx/wallos-dial:1.0.0` image, or use a reviewed local source build. Update only your Dial image reference and any deliberate deployment settings. Check `docker compose config` again, including the absolute database and Logo mounts, then start the new container. Never mount the same live database into a second running Wallos container.

The first start completes the Wallos database and Dial migrations and waits for the PHP-FPM listener before opening Nginx. `/health.php` returns `OK` only after the Dial migration is recorded. Wait for that response before testing login or registration; if it does not arrive, read container logs and retain the old stopped container plus matching backups for rollback.

Read back the image version, `/health.php`, login, subscription count, category assignments, uploaded Logos, My Icons, dashboard, search/filter/sort, calendar, statistics, settings, language switch and notification configuration. Run `PRAGMA integrity_check` again after the container has stopped or use SQLite's safe read-only connection. If any of these checks fails, follow the rollback below. A healthy container alone does not prove the data and workflows survived.

## Rollback to 0.1.1

1. Stop the 1.0 container. Keep its current database and Logo directory as a **failed-state copy** for diagnosis; do not overwrite the pre-upgrade backup.
2. Restore the matching `wallos.db` and `logos.tar` from the pre-upgrade backup into the original volume paths. Make sure no stale `wallos.db-wal` or `wallos.db-shm` from the failed state remains in the restored database directory. Restore the original private SVG map or custom configuration if it was changed.
3. Restore the saved Compose/image reference to `ghcr.io/lukevoidx/wallos-dial:0.1.1`, validate its mounts, then start only that old container. Confirm health, login, subscription count, original Logos and ordinary subscription actions.

Do not point 0.1.1 at an unknown migrated 1.0 database as a general rollback method. The old image ignores Dial's new icon tables and displays original Logos, but restoring the matching database and Logo backup is the reliable recovery path. My Icons selections made after the upgrade are absent from the pre-upgrade backup. Keep a copy of the 1.0 database if those choices need later recovery, and never mix it with an older Logo snapshot without checking the records.

## Maintainer compatibility process

1. The scheduled upstream checker reports a new Wallos release. It does not approve an update.
2. Change `WALLOS_VERSION` and the pinned digest on a branch. `compat/upstream-files.sha256` checks every overwritten upstream file before the Dial overlay is copied. Review each changed upstream file and update the hash manifest only after reading the diff.
3. Run the full release gate on fresh and populated synthetic databases, including upstream security checks, category/icon ownership checks, five mail flows, first-run readiness, account flows, service-worker upgrade, browser layout and a 0.1.1 rollback rehearsal.
4. Publish a new versioned image only after the new Wallos base and both architectures have been tested. Update both READMEs and the product pages with the **exact** supported Wallos version. Keep the previous image available.

No compatibility guarantee is made for an upstream release that has not been reviewed. The version pin keeps working installations on their tested base.
