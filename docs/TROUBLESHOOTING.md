# Troubleshooting

Start with `docker compose ps`, `docker compose logs --tail=100 wallos`, and `curl -i http://127.0.0.1:8282/health.php`. The default port is bound to the Docker host's loopback address, so a browser on another machine needs an SSH tunnel or your own HTTPS reverse proxy.

| Symptom | Check | Action |
|---|---|---|
| Port `8282` is already in use | `docker compose config` and `docker ps` | Choose a free loopback port in a local Compose override, or stop the other service. Do not run two Wallos containers against the same database. |
| GHCR image cannot be pulled | Image name and package visibility | Use the versioned tag shown in the README. Maintainers must make the GHCR package public after the first release and verify an anonymous pull. Before release, use `compose.build.yaml` to build locally. |
| Registration appears for an existing account | Resolved `WALLOS_DB_DIR` in `docker compose config` | Stop the candidate and check the absolute mount path. Do not register a new user or overwrite the existing database until the correct backup and mount are confirmed. |
| Uploaded logos are missing | Resolved `WALLOS_LOGOS_DIR`, filesystem access | Mount the same uploaded-logo directory that the previous Wallos container used. Dial does not replace uploaded logos. |
| Theme or favicon looks stale | Browser cache and service worker | Reload the page, then close and reopen the tab. If still stale, inspect the stylesheet and favicon URLs in the page source and confirm the container runs the expected image tag. Clear site data only if you can safely sign in again. |
| Container is unhealthy | `docker compose logs --tail=100 wallos` | Confirm writable `db` and `logos` mounts, valid SQLite backup, and available memory. Restore the previous image and matching database backup if a migration occurred. |
| Build stops at a checksum mismatch | `compat/upstream-files.sha256` output | The pinned Wallos base changed. Do not bypass the check. Compare upstream files, review the overlay, then update the manifest only after testing. |
| A new Wallos release is available | `WALLOS_VERSION` and image tag | Stay on the last tested Dial image until a compatibility release is published. |

For a bug report, provide the Dial and Wallos versions, architecture, browser, exact steps, and a **redacted** screenshot or log excerpt. Do not attach `wallos.db`, uploaded logos, tokens, `.env`, auth cookies, or account-specific notes.
