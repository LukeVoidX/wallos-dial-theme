# Wallos Dial 0.1.0

An open-source, desktop-focused presentation for Wallos 5.8.1: warm off-white surfaces, precise typography and lines, a date dial, payment countdown, grouped subscriptions, clearer statistics, and a quick language selector. Wallos's existing subscription and settings functions remain available.

![Wallos Dial dashboard](https://raw.githubusercontent.com/LukeVoidX/wallos-dial-theme/v0.1.0/docs/assets/screenshots/after-dashboard.png)

## Install

```bash
git clone https://github.com/LukeVoidX/wallos-dial-theme.git
cd wallos-dial-theme
docker compose up -d
```

Open `http://127.0.0.1:8282` on the Docker host or forward the loopback port over SSH. The versioned image is `ghcr.io/lukevoidx/wallos-dial:0.1.0` for `linux/amd64` and `linux/arm64`. Follow the [README](https://github.com/LukeVoidX/wallos-dial-theme/blob/v0.1.0/README.md) before attaching an existing Wallos database.

## Compatibility and limits

- Verified base: Wallos `5.8.1`, pinned by image digest and checked upstream-file hashes.
- Existing database and uploaded logos are persistent mounts; back them up before migrating an installation.
- A newer Wallos version needs a separate compatibility review and Dial image. There is no automatic major upgrade.
- Desktop is the primary design target; this release does not include a dedicated phone redesign.
- The UI examples use synthetic data. Poster backgrounds are generated; interface screenshots are direct browser captures.

Full screenshots, installation details, troubleshooting, and rollback guidance are in the [repository](https://github.com/LukeVoidX/wallos-dial-theme). Wallos Dial is an independent GPLv3 overlay, not an official Wallos theme option.
