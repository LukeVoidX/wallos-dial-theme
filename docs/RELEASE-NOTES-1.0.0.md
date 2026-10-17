# Wallos Dial 1.0.0

**Released as [v1.0.0](https://github.com/LukeVoidX/wallos-dial-theme/releases/tag/v1.0.0).** The versioned GHCR image is `ghcr.io/lukevoidx/wallos-dial:1.0.0`; the separate [public Demo](DEMO.md) runs the same release with fictional data in read-only mode. The [release checklist](RELEASE-CHECKLIST-1.0.0.md) records build, public-route and rollback verification.

Wallos Dial 1.0.0 is a desktop-first, source-overlay theme for **Wallos 5.8.3**. It keeps the existing Wallos subscription workflow and adds two ways to make a real ledger easier to maintain: account-scoped category management and per-subscription icon choices.

## New in 1.0

- **Manage categories:** add, rename, reorder, merge, and delete empty categories in one page. The optional AI / Infra / Domains / Media starter set appears only for an account with no subscriptions and requires review before changes. Existing category assignments are retained.
- **Choose icons:** keep the original uploaded Wallos Logo, choose among nine neutral first-party catalog pictograms, or upload a personal image to **My Icons**. Plain/framed, color/monochrome and 80–120% optical controls adjust presentation without replacing the original Logo. The same choice appears in the dashboard, subscription list and details.
- **Private My Icons:** account-owned PNG, JPEG, WebP or constrained SVG uploads are normalized and saved to SQLite. Uploaded SVG is converted to PNG rather than served as SVG. The library is capped at 100 images and 32 MiB per account. An authenticated image endpoint enforces ownership and `no-store` responses.
- **Wallos 5.8.3 compatibility:** the Docker base and overwritten upstream files are pinned and checked. The service-worker cache uses both the Wallos and Dial versions. The 0.1.1 language switch and warm HTML email templates remain.
- **Mail and reliability:** renewal/cancellation plain-text alternatives, four account-email subjects, and optional period summaries follow the recipient's English, Simplified Chinese, or Traditional Chinese language. Cancellation reminders skip and log legacy subscriptions whose payer belongs to another account; set a valid payer on those subscriptions to resume their notices. The worker no longer stores account pages or uploaded Logos in CacheStorage; subscription writes without a new Logo no longer depend on Logo-volume write access. Original Logo downloads and decoding have explicit byte and pixel limits.
- **First-run experience:** registration, login, password reset, email verification and TOTP pages share Dial's warm and dark visual system and favicon while retaining Wallos's account handlers. The container completes database creation and migrations, then waits for the PHP-FPM listener before Nginx opens; `/health.php` reports ready only after the Dial schema is present. Optional read-only personal SVG mounts remain usable.

The nine catalog keys retain service names for recognition, but their graphics are [original generic pictograms](../theme/images/dial-catalog/SOURCES.md), not third-party logos. Personal SVGs, uploaded Logos, databases and filename maps are excluded from the public package.

## Installation and migration

New installations use the pull-only [`compose.yaml`](../compose.yaml), which pins the published `1.0.0` image, binds HTTP to `127.0.0.1:8282`, and persists `./data/db` and `./data/logos`. Contributors can use [`compose.build.yaml`](../compose.build.yaml) for a local source build. Existing Dial 0.1.1 users should follow [the upgrade and rollback procedure](UPGRADING.md) on a disposable copy before a one-container cutover. Back up the SQLite database **and** uploaded Logo volume; My Icons are stored in SQLite.

The public [Demo](DEMO.md) is a separate, read-only 1.0 container with synthetic records. It does not expose uploads, category edits, or account settings.

## Compatibility and limitations

- Tested upstream target: **Wallos 5.8.3 only**. A later Wallos version needs another reviewed Dial build.
- Primary design target: desktop. No separate mobile redesign is promised.
- The public catalog uses neutral symbols; people who want an official service logo can supply their own image through My Icons or retain their original Wallos Logo, subject to their own rights.
- A 0.1.1 rollback restores original Logos. Keep the matching pre-upgrade database and Logo backup; My Icons choices created after that backup require a later 1.0 database copy to recover.
- Validation covered the full local release gate, both architecture smokes, first-run readiness and account flows, five-path SMTP capture in three languages, synthetic browser screenshots, package privacy scan and Demo read-only probes. Hosted CI, the published multiarch digest and public Demo route checks are recorded separately in the [release checklist](RELEASE-CHECKLIST-1.0.0.md); local image IDs are not GHCR digests.

Wallos Dial is an independent GPLv3 derivative of [Wallos](https://github.com/ellite/Wallos). See the [notices](../NOTICE.md) and [English](../README.md) or [简体中文](../README.zh-CN.md) guides.
