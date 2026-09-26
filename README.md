# Wallos Dial

A precise, warm, desktop-focused interface for [Wallos](https://github.com/ellite/Wallos). The theme adds an instrument-style dashboard timeline, a restrained type and color system, grouped subscription cards, clearer statistics, focused interaction states, and a persistent language switch in the header. Wallos's subscription, calendar, settings, search, and notification features remain available.

**Status:** `0.1.0` release candidate · **Tested Wallos version:** `5.8.1` only.

Compatibility is published per Wallos version. An upstream release does not automatically make an older Dial image compatible. The build checks the exact upstream files it replaces and stops on a mismatch; a newly tested Dial image is then published.

Dial is a source overlay built into a Docker image. It is not a native Wallos theme option, and it is not affiliated with the Wallos project. Upgrading Wallos requires rebuilding and reviewing the overlay against the new upstream version. See [Updating](docs/UPGRADING.md).

## Preview

The design uses warm off-white, dark numerals, fine lines, and a small red signal. It is tuned for desktop use. The original Wallos mobile layout remains available, but this release does not promise a separate mobile redesign.

## Install a new instance

Requirements: Docker with Compose, an available local port `8282`, and a recent browser.

```bash
git clone https://github.com/LukeVoidX/wallos-dial-theme.git
cd wallos-dial-theme
docker compose up -d
```

Open `http://127.0.0.1:8282` from the same machine. The Compose file pulls a prebuilt versioned image and binds to loopback; use your own HTTPS reverse proxy and access controls if you need remote access. Set `TZ` in a local `.env` file if needed. Wallos stores its database in `./data/db` and uploaded subscription logos in `./data/logos`; both are excluded from Git.

The GitHub repository and public container image become available only after release publication. Until then, the archive can be built locally with `docker compose -f compose.build.yaml up -d --build`.

## Use an existing Wallos database

1. Back up the existing SQLite database and uploaded logos. Keep the previous image reference and Compose file for rollback.
2. Copy the repository locally and create a Git-ignored `.env` file with **absolute** paths to the existing mounted directories:

   ```dotenv
   WALLOS_DB_DIR=/absolute/path/to/current/db
   WALLOS_LOGOS_DIR=/absolute/path/to/current/logos
   TZ=Europe/Berlin
   ```

3. Run `docker compose config` and check the resolved mounts. Stop the old container before starting Dial on the same port; then run `docker compose up -d`.
4. Check login, subscription count, logos, dashboard, calendar, statistics, settings, and notifications. Do not re-enter subscriptions.

The theme itself does not seed or edit subscriptions. Wallos's own startup and migration behavior still applies; test a newer Wallos base against a disposable data copy first. If your existing service uses other environment variables, proxy rules, or a different port, carry them into your deployment configuration deliberately.

## Optional custom vector logos

Uploaded logos remain untouched and are fitted into a consistent tile by default. For a custom SVG, put it in `config/icons/` and uncomment the two read-only mounts in `compose.yaml`. Copy [`config/logo-map.example.php`](config/logo-map.example.php) to `config/logo-map.local.php`, then map the uploaded filename to your SVG filename. Unknown filenames keep their original uploaded logo.

This repository contains no subscription data, uploaded logos, brand marks, or private logo mappings. `config/icons/`, `config/logo-map.local.php`, `.env`, and `data/` are Git ignored.

## Language switch

Use the selector in the shared header to switch quickly between 简体中文, English, and 繁體中文. The menu also includes all other Wallos languages. Dial saves the choice to the existing Wallos account language field and language cookie; it persists across pages and sign-ins. User-entered subscription and category names remain as entered.

## Updates and compatibility

| Dial | Tested Wallos | Install image |
|---|---|---|
| 0.1.0 | 5.8.1 | `ghcr.io/lukevoidx/wallos-dial:0.1.0` |

Use a versioned Dial image; avoid `latest`. A weekly workflow detects new upstream Wallos releases. Maintainers inspect changed templates, run the compatibility gate and smoke checks, then publish a new Dial image. Until that verified image exists, keep the previous working version. [Upgrade and rollback details](docs/UPGRADING.md).

## What is included

- `theme/`: modified Wallos PHP, CSS, and JavaScript files at their in-container paths.
- `compose.yaml`: pull-only, loopback-bound deployment with persistent database and logo volumes.
- `Dockerfile` and `compose.build.yaml`: source build for contributors and pre-release checks.
- `compat/upstream-files.sha256`: hashes of every upstream file overwritten by the theme; a changed base fails the build pending review.
- `VERSION` and `WALLOS_VERSION`: theme release and tested upstream version.
- `docs/UPGRADING.md`: compatibility and rollback instructions.
- `LICENSE.md`: GPLv3, matching the upstream project's license.

`./scripts/check-package.sh` validates the package. `docker build -t wallos-dial:0.1.0 .` and `./scripts/smoke-image.sh wallos-dial:0.1.0` check a local image. Releases publish both `linux/amd64` and `linux/arm64` images after passing these gates.

## Credits and license

Wallos Dial modifies [Wallos](https://github.com/ellite/Wallos), originally by ellite and contributors. Wallos and this derivative source are distributed under [GPLv3](LICENSE.md). Keep the upstream copyright and license notices when redistributing. No official Wallos endorsement is implied.
