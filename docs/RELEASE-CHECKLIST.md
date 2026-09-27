# 0.1.0 publication checklist

- [x] Source extracted to a standalone repository; no upstream repository history copied.
- [x] GPLv3 and upstream attribution included.
- [x] No database, uploaded logos, personalized logo map, auth material, or live host config in the candidate.
- [x] Pinned Wallos 5.8.1 image and nine overwritten-file hashes verified at build time.
- [x] Deliberately mismatched hash rejected by the build; the nine upstream hashes also match the official arm64 image.
- [x] PHP and JavaScript syntax checks passed; both Compose files validated.
- [x] Fresh install with empty persistent DB/logo mounts reached registration and created a valid SQLite database.
- [x] Disposable populated runtime loaded dashboard, 16 subscriptions, calendar, statistics, settings, and add form. A synthetic subscription was added and deleted; the test database returned to 16 rows and integrity `ok`.
- [x] Language switch checks: simplified/English/Traditional labels, persisted account field and locale selection, invalid language (422), missing CSRF, and invalid request method rejected.
- [x] Desktop width checks: header/dashboard aligned, timeline contained, no horizontal overflow across normal and wide viewport equivalents.
- [x] Upcoming payment countdown: localized column and values checked in both previews; signed calendar-day calculation and 1100px desktop layout checked.
- [x] Cross-page desktop scaling pass: main routes at 720–1920px, secondary routes at 720–1920px, and add dialog at 1280×700; hidden timeline tooltip overflow corrected.
- [x] Subscription details URL icon remains visible before hover in both private and public preview themes; exported calendar button remains visible.
- [x] Original favicon has SVG and 16px/32px PNG variants; both previews return correct image content types from versioned paths.
- [x] Public posters use ImageGen backgrounds and exact browser captures from isolated synthetic data; raw screenshots and provenance are included. No personal account screenshots are in the repository.
- [x] A 1280×640 social preview under 1 MB is prepared for GitHub repository settings.
- [x] English and Simplified Chinese install guides, before/after gallery, troubleshooting, contribution guide, release notes, and launch copy have valid local links.
- [x] Replaced the divergent static imitation with a local **actual Wallos Dial container** on `127.0.0.1:4190`; its separate SQLite volume has 30 fictional records and generated SVG marks. Dashboard, subscriptions, calendar, statistics, detail fetch, and English/Simplified Chinese loaded with no browser page errors.
- [x] A Demo Mode image build, PHP syntax check, runtime smoke, and language endpoint check passed. In Demo Mode, a Chinese language selection changed the visitor Cookie and rendered Chinese without changing the shared account's database language.
- [x] The synthetic reset script restored 30 rows and SQLite integrity `ok` in a dedicated local demo volume, after verifying the exact container mounts.
- [x] Isolated original Wallos and Dial demo containers used the same 16 fictitious subscriptions; both are healthy, the database integrity check returned `ok`, and browser sessions reported no page errors.
- [x] Exact review source built and passed hash/PHP/runtime checks on an amd64 host; a fresh mounted database reached registration with SQLite integrity `ok`.
- [x] The same theme source built natively on arm64, passed runtime health/assets smoke, and a fresh mounted database reached registration with SQLite integrity `ok`.
- [x] Runtime smoke checked `/health.php`, Dial CSS, and interaction JavaScript.
- [x] Pull-only Compose binds to `127.0.0.1`, persists DB/logos, and uses a versioned image tag.
- [ ] Owner reviews repository name, docs, code, image publication target, and release candidate archive.
- [ ] Create public `LukeVoidX/wallos-dial-theme` repository and push exact reviewed commit.
- [ ] Set repository description, suggested topics, and upload `docs/assets/posters/social-preview.png` as the social preview.
- [ ] Deploy a separate public, read-only Wallos Dial demo container with synthetic volumes at `wallos-demo.jarvishub.me`; verify Caddy allowlist, HTTPS, Cloudflare origin restriction, and two-hour reset job anonymously.
- [ ] Enable GitHub Pages from `main` `/docs` and verify that `https://lukevoidx.github.io/wallos-dial-theme/` forwards to the live real demo.
- [ ] Verify source CI. Push `v0.1.0` only after owner approval; verify amd64/arm64 GHCR image build and an arm64 runtime smoke.
- [ ] Make the first GHCR package public (the registry initially defaults to private), then verify an anonymous pull and a clean Compose installation.
- [ ] Publish GitHub Release with the reviewed source archive and exact image digest.

The currently running private Wallos service is not changed by this package work. The project cannot claim compatibility with untested future Wallos releases or zero defects.
