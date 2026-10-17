# Wallos Dial 1.0.0 release checklist

**Status: GitHub Release, GHCR image, public Demo and bilingual product pages published on 2026-10-17.** Luke approved the GitHub, public Demo and product-page scope. The private Wallos installation and community posts are outside this release.

## Release identity

| Item | Verified evidence |
| --- | --- |
| Immutable source tag | [v1.0.0](https://github.com/LukeVoidX/wallos-dial-theme/releases/tag/v1.0.0) resolves to `d1eb9d2e4ea6f73ddef866e1508a98da40dccd93`. The tag was not moved after its first workflow failed on a Linux test-fixture ownership issue. |
| Main-branch CI harness | `b0778818e633936a3d53bd10d6a7c6f850067ea8` fixes disposable fixture permissions on nonroot Linux runners. Its one-time pinned-tag recovery workflow checked runtime equality before publishing; the workflow was removed after use. Runtime files remain identical to the tag. |
| Version and tested upstream | Root `VERSION`, image label, `DIAL_VERSION` and published tag identify `1.0.0`. Based on Wallos 5.8.3, `bellamy/wallos:5.8.3@sha256:0006879778f31a962a3873f7682514b92a3c7665dcbc4764fcc241ad968eaccd`. Alpine `rsvg-convert=2.62.3-r0` is pinned and smoke-tested on both architectures. |
| Published image | `ghcr.io/lukevoidx/wallos-dial:1.0.0@sha256:0e147a3bed5e7392cc51e9f3b8459ba1a8964c92cde29d619d8d9a1c9d5644c6` is a `linux/amd64` + `linux/arm64` OCI index. Anonymous pulls and `1.0.0` version-label checks passed for both architectures. |
| GitHub Release | [Wallos Dial v1.0.0](https://github.com/LukeVoidX/wallos-dial-theme/releases/tag/v1.0.0) is published, neither draft nor prerelease. |

## Acceptance evidence

| Gate | Result and artifact |
| --- | --- |
| Exact-tag local full gate | `bash scripts/verify-1.0-candidate.sh wallos-dial:exact-d1eb9d2` passed against the immutable source commit. The ignored `data/verify-1.0-exact-d1eb9d2.log` ends with `Complete local Dial candidate gate passed`. |
| Linux CI and pinned-image publish | [Branch Linux gate](https://github.com/LukeVoidX/wallos-dial-theme/actions/runs/39029999103) and [main Linux gate](https://github.com/LukeVoidX/wallos-dial-theme/actions/runs/39030241802) passed on the harness-fix commit. [Pinned-tag recovery/publish run](https://github.com/LukeVoidX/wallos-dial-theme/actions/runs/39030250424) passed; it verified main against the tag, then checked out the exact tag before building and publishing the image. |
| Package and privacy audit | `scripts/check-package.sh`, Git history/build-context checks and independent OCI scans of both exact-tag architecture images passed: 28 layers each, exactly nine neutral catalog SVGs, zero personal SVG/domain paths or content, and no private database, key or map. Ignored reports: `data/release-audit-d1eb9d2-{amd64,arm64}.json`. The image contains only the upstream empty database template. |
| Fresh installation and migration | Fresh synthetic Wallos 5.8.3 start, 30-record seed, image smoke and SQLite integrity passed. `tests/compatibility/upgrade-rollback.sh` passed on disposable 0.1.1 copies: backed-up database/Logo volumes, 1.0 migration, old-image rollback with add/edit/delete, then re-upgrade. Tampered upstream hash was rejected. Private data was not used. |
| Categories and icons | PHP, HTTP and browser tests passed for category CRUD/merge/starter, two-account isolation, CSRF and Demo read-only behavior. Original/Catalog/My Icons tests covered account isolation, upload limits and SVG rejection, low-resolution warning, Change preservation, list/grid/dashboard/calendar/details, and original fallback. |
| Cache and desktop layout | Old/new service-worker tests passed, including account switch, removal of old cache keys and no cached private images/account pages. Synthetic browser journey passed in English, 简体中文 and 繁體中文 at 900/1440/1920 px and 80/100/125% CSS zoom equivalents, with no horizontal overflow. Native macOS Chrome 80/100/125% zoom also passed on five desktop pages. Screenshots are in `docs/assets/screenshots/dial-1.0-*.png`; ignored manifests are in `data/qa-1.0/`. |
| Mail and notifications | Five email paths passed in isolated SMTP capture for `en`, `zh_cn`, `zh_tw`: renewal, cancellation, SMTP test, verification and password reset, including HTML/plain and two-account invalid-payer coverage. Nine local receipts passed over Discord, Gotify, Ntfy, Webhook and Mattermost on an internal Docker network. External delivery URLs and Web Push were not exercised end to end. |
| Multiarchitecture runtime and Demo safety | Exact-tag `linux/amd64` and `linux/arm64` images passed runtime/health/theme and SVG rasterizer smoke. Local Caddy/Nginx/HTTP/browser Demo read-only tests passed; injected reset failures restored the prior synthetic database and Logos. |
| Documentation contract | Version, package, CI and relative-link checks passed locally. The postrelease documentation commit `47391c53ad74b814e70c0ff0866d9af10083cb74` passed [Linux CI](https://github.com/LukeVoidX/wallos-dial-theme/actions/runs/39030996344) and [GitHub Pages deployment](https://github.com/LukeVoidX/wallos-dial-theme/actions/runs/39030995831). Public English and 简体中文 pages returned HTTP 200 with version 1.0.0, Wallos 5.8.3 JSON-LD, reciprocal language links, Demo link and six loaded images each. Headless Chrome at 1440 px found no horizontal overflow or page errors. |

## Public Demo change record

| Item | Observed result |
| --- | --- |
| Public site and image | [wallos-demo.mostai.org](https://wallos-demo.mostai.org/) now runs container `wallos-dial-public-demo` with the exact `1.0.0` OCI index above. Independent read-only host check confirmed the container is running, its OCI version label is `1.0.0`, and `DEMO_MODE=1`. |
| Synthetic data | Independent host readback found `PRAGMA integrity_check=ok`, 30 subscriptions with synthetic Logos, nine catalog preferences with distinct neutral keys, and zero private icon assets. The private Wallos database and Logo volume are mounted only by its separate container. |
| Public behavior | The cutover completed successfully and its public HTTPS/read-only smoke passed twice. Independent readback found homepage, subscriptions, categories, calendar, statistics, health and seed Logos returning HTTP 200 through the normal Cookie flow; private Logo/database paths and subscription mutation requests returned HTTP 404. |
| Reset service | `wallos-dial-demo-reset.timer` is active and enabled. Its scheduled 14:00 CEST run completed at 14:00:06 with exit status `0`, 30 subscriptions, nine icon preferences and public smoke intact; the next run is scheduled for 16:00 CEST. |
| Matched rollback backup | `/srv/wallos-dial-public-demo/backups/pre-1.0.0` exists with the old synthetic database, Logos and template, plus the old image ID, Compose/environment, Caddyfile and systemd reset files. The old 0.1.1 checkout and its local hotfixes were preserved. |
| Rollback boundary | If later public health, data or read-only checks fail, restore the matched 0.1.1 Demo image and synthetic volumes from that backup, restore its Caddy/systemd files, verify the public routes, then restart the old timer. Do not use the 1.0 reset script with the 0.1.1 image. Only Compose project `demo` is in scope. |

## Publication record and remaining checks

- [x] Luke approved pushing `main`, publishing the `v1.0.0` tag, GHCR image and GitHub Release, and updating the public Demo and product pages.
- [x] Exact source tag, successful Linux CI runs, GHCR multiarchitecture digest, anonymous pulls and GitHub Release were verified.
- [x] Public Demo was switched to the pinned 1.0.0 image, backed up, smoke-tested twice, and its two-hour reset timer restarted. The private Wallos installation was not part of the cutover.
- [x] Read back the [English product page](https://lukevoidx.github.io/wallos-dial-theme/) and [简体中文 product page](https://lukevoidx.github.io/wallos-dial-theme/zh-CN/) after commit `47391c53ad74b814e70c0ff0866d9af10083cb74`; version, Demo URL, screenshots, links and language alternates passed public HTTP and browser checks.

NodeSeek, V2EX, X and other community announcements require their own review and authorization.
