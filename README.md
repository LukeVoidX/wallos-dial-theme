# Wallos Dial

A precise, warm, desktop-focused interface for [Wallos](https://github.com/ellite/Wallos). The theme adds an instrument-style dashboard timeline, a restrained type and color system, grouped subscription cards, clearer statistics, and focused interaction states. Wallos's subscription, calendar, settings, search, and notification features remain available.

**Status:** `0.1.0` release candidate · **Wallos compatibility:** `5.8.1` only.

Dial is a source overlay built into a Docker image. It is not a native Wallos theme option, and it is not affiliated with the Wallos project. Upgrading Wallos requires rebuilding and reviewing the overlay against the new upstream version. See [Updating](docs/UPGRADING.md).

## Preview

The design uses warm off-white, dark numerals, fine lines, and a small red signal. It is tuned for desktop use. The original Wallos mobile layout remains available, but this release does not promise a separate mobile redesign.

## Install a new instance

Requirements: Docker with Compose, an available local port `8282`, and a recent browser.

```bash
git clone https://github.com/LukeVoidX/wallos-dial-theme.git
cd wallos-dial-theme
docker compose -f compose.example.yaml up -d --build
```

Open `http://127.0.0.1:8282` from the same machine. The example binds to loopback; use your own HTTPS reverse proxy and access controls if you need remote access. Change `TZ: UTC` to your timezone. Wallos stores its database in `./data/db` and uploaded subscription logos in `./data/logos`; both are excluded from Git.

The `git clone` URL becomes usable after the public repository is published. Until then, use the release candidate archive supplied by the maintainer.

## Use an existing Wallos database

1. Back up your existing SQLite database and uploaded logos. Keep the original image and Compose file for rollback.
2. Review `compose.example.yaml`, then point its two volume mounts at your existing Wallos `db` and `images/uploads/logos` directories. Retain the same timezone and other environment settings you already use.
3. Stop the existing Wallos container before binding Dial to the same port. Build and start Dial with `docker compose -f compose.example.yaml up -d --build`.
4. Confirm that login, subscription count, logos, dashboard, calendar, statistics, settings, and notifications look correct. Do not import or re-enter subscriptions.

The theme does not migrate, seed, or edit your subscription data. Wallos's own startup and migration behavior still applies. Test upgrades against a copy of your data first.

## Optional custom vector logos

Uploaded logos remain untouched and are fitted into a consistent tile by default. To substitute your own SVG for a particular uploaded file:

1. Put the SVG in `theme/images/dial-logos/` and rebuild the image.
2. Copy [`config/logo-map.example.php`](config/logo-map.example.php) to `config/logo-map.local.php`, then map the uploaded filename to the SVG filename.
3. Uncomment the read-only config mount and `WALLOS_DIAL_LOGO_MAP` line in `compose.example.yaml`; restart the container.

Unknown filenames keep their original uploaded logo. This repository contains no subscription data, uploaded logos, brand marks, or private logo mappings. The optional mapping file and `data/` directory are Git ignored.

## What is included

- `theme/`: modified Wallos PHP, CSS, and JavaScript files at their in-container paths.
- `Dockerfile`: the exact Wallos 5.8.1 base image and the overlay. `VERSION` is the canonical theme version.
- `compose.example.yaml`: loopback-bound sample deployment with persistent database and logo volumes.
- `docs/UPGRADING.md`: compatibility and rollback instructions.
- `LICENSE.md`: GPLv3, matching the upstream project's license.

`docker build -t wallos-dial:0.1.0 .` builds the image. `scripts/check-package.sh` checks package structure, syntax, and accidental inclusion of local data before a release.

## Credits and license

Wallos Dial modifies [Wallos](https://github.com/ellite/Wallos), originally by ellite and contributors. Wallos and this derivative source are distributed under [GPLv3](LICENSE.md). Keep the upstream copyright and license notices when redistributing. No official Wallos endorsement is implied.
