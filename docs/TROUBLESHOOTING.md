# Troubleshooting

Start with `docker compose ps`, `docker compose logs --tail=100 wallos`, and `curl -i http://127.0.0.1:8282/health.php`. The default port is bound to the Docker host's loopback address, so a browser on another machine needs an SSH tunnel or your own HTTPS reverse proxy.

| Symptom | Check | Action |
|---|---|---|
| Port `8282` is already in use | `docker compose config` and `docker ps` | Choose a free loopback port in a local Compose override, or stop the other service. Do not run two Wallos containers against the same database. |
| GHCR image cannot be pulled | Versioned tag, package visibility, architecture and network | Use `ghcr.io/lukevoidx/wallos-dial:1.0.0`; verify the public package and an anonymous pull for your architecture. Contributors may use `compose.build.yaml` for a local source build. |
| Fresh container is not ready | `docker compose logs --tail=100 wallos` and `/health.php` | Startup completes database and Dial migrations and waits for PHP-FPM before Nginx opens. Wait for HTTP 200 / `OK`; if it never arrives, inspect mounts and SQLite before attempting registration. |
| Registration appears for an existing account | Resolved `WALLOS_DB_DIR` in `docker compose config` | Stop the container and check the absolute mount path. Do not register a new user or overwrite the existing database until the correct backup and mount are confirmed. |
| Uploaded logos are missing | Resolved `WALLOS_LOGOS_DIR`, filesystem access | Mount the same uploaded-logo directory that the previous Wallos container used. Dial does not replace uploaded logos. |
| A fresh install runs but `./data/db/wallos.db` is absent on the host | Docker context and host-directory sharing | With Colima or a remote Docker daemon, bind paths may resolve inside the VM/remote host. Stop before registration, place the checkout in a shared directory, then verify both persistent directories appear on the host. Do not rely on a container-only database as your backup. |
| Theme or favicon looks stale | Browser cache and service worker | Reload the page, then close and reopen the tab. If still stale, inspect the stylesheet and favicon URLs in the page source and confirm the container runs the expected image tag. Clear site data only if you can safely sign in again. |
| Container is unhealthy | `docker compose logs --tail=100 wallos` | Confirm writable `db` and `logos` mounts, valid SQLite backup, and available memory. Restore the previous image and matching database backup if a migration occurred. |
| Build stops at a checksum mismatch | `compat/upstream-files.sha256` output | The pinned Wallos base changed. Do not bypass the check. Compare upstream files, review the overlay, then update the manifest only after testing. |
| A new Wallos release is available | `WALLOS_VERSION` and image tag | Stay on the last tested Dial image until a compatibility release is published. |

For a bug report, provide the Dial and Wallos versions, architecture, browser, exact steps, and a **redacted** screenshot or log excerpt. Do not attach `wallos.db`, uploaded logos, tokens, `.env`, auth cookies, or account-specific notes.
