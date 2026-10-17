# Wallos Dial 1.0.0

**Draft for release review.** The local source version is `1.0.0`; its Git tag and GHCR image have not been published. This document is not evidence that `v1.0.0` or the public Demo update exists. Complete the [release checklist](RELEASE-CHECKLIST-1.0.0.md) and obtain approval before publishing.

Wallos Dial 1.0.0 is a desktop-first, source-overlay theme for **Wallos 5.8.3**. It keeps the existing Wallos subscription workflow and adds two ways to make a real ledger easier to maintain: account-scoped category management and per-subscription icon choices.

## New in 1.0

- **Manage categories:** add, rename, reorder, merge, and delete empty categories in one page. The optional AI / Infra / Domains / Media starter set appears only for an account with no subscriptions and requires review before changes. Existing category assignments are retained.
- **Choose icons:** keep the original uploaded Wallos Logo, choose among nine neutral first-party catalog pictograms, or upload a personal image to **My Icons**. Plain/framed, color/monochrome and 80–120% optical controls adjust presentation without replacing the original Logo. The same choice appears in the dashboard, subscription list and details.
- **Private My Icons:** account-owned PNG, JPEG, WebP or constrained SVG uploads are normalized and saved to SQLite. Uploaded SVG is converted to PNG rather than served as SVG. The library is capped at 100 images and 32 MiB per account. An authenticated image endpoint enforces ownership and `no-store` responses.
- **Wallos 5.8.3 compatibility:** the Docker base and overwritten upstream files are pinned and checked. The service-worker cache uses both the Wallos and Dial versions. The 0.1.1 language switch and warm HTML email templates remain.
- **First-run experience:** registration, login, password reset, email verification and TOTP pages now share Dial's warm and dark visual system and favicon. The container completes database creation and migrations before accepting HTTP traffic; `/health.php` reports ready only after the Dial schema is present. Optional read-only personal SVG mounts remain usable.
- **Mail and reliability:** renewal/cancellation plain-text alternatives, four account-email subjects, and optional period summaries follow the recipient's English, Simplified Chinese, or Traditional Chinese language. Cancellation reminders skip and log legacy subscriptions whose payer belongs to another account; set a valid payer on those subscriptions to resume their notices. The worker no longer stores account pages or uploaded Logos in CacheStorage; subscription writes without a new Logo no longer depend on Logo-volume write access. Original Logo downloads and decoding have explicit byte and pixel limits.

The nine catalog keys retain service names for recognition, but their graphics are [original generic pictograms](../theme/images/dial-catalog/SOURCES.md), not third-party logos. Personal SVGs, uploaded Logos, databases and filename maps are excluded from the public package.

## Installation and migration

After the versioned GHCR image is published, a new installation can use the pull-only [`compose.yaml`](../compose.yaml), which binds HTTP to `127.0.0.1:8282` and persists `./data/db` and `./data/logos`. Until then, use [`compose.build.yaml`](../compose.build.yaml) for isolated local review. Existing Dial 0.1.1 users should follow [the upgrade and rollback procedure](UPGRADING.md) on a disposable copy before a one-container cutover. Back up the SQLite database **and** uploaded Logo volume; My Icons are stored in SQLite.

The public [Demo](DEMO.md) is a separate, read-only container with synthetic records. Its update to 1.0 is a separate approval-gated deployment after publication. It does not expose uploads, category edits, or account settings.

## Compatibility and limitations

- Tested upstream target: **Wallos 5.8.3 only**. A later Wallos version needs another reviewed Dial build.
- Primary design target: desktop. No separate mobile redesign is promised.
- The public catalog uses neutral symbols; people who want an official service logo can supply their own image through My Icons or retain their original Wallos Logo, subject to their own rights.
- A 0.1.1 rollback restores original Logos. Keep the matching pre-upgrade database and Logo backup; My Icons choices created after that backup require a later 1.0 database copy to recover.
- The local full candidate gate, both architecture smokes, five-path SMTP capture in three languages, synthetic browser screenshots, package privacy scan and Demo read-only probes passed. The source commit, hosted CI result, published multiarch digest and public Demo switch remain pending in the [release checklist](RELEASE-CHECKLIST-1.0.0.md). The checked local images are not a GHCR release.

Wallos Dial is an independent GPLv3 derivative of [Wallos](https://github.com/ellite/Wallos). See the [notices](../NOTICE.md) and [English](../README.md) or [简体中文](../README.zh-CN.md) guides.
