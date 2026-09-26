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
- [x] Runtime smoke checked `/health.php`, Dial CSS, and interaction JavaScript.
- [x] Pull-only Compose binds to `127.0.0.1`, persists DB/logos, and uses a versioned image tag.
- [ ] Owner reviews repository name, docs, code, image publication target, and release candidate archive.
- [ ] Create public `LukeVoidX/wallos-dial-theme` repository and push exact reviewed commit.
- [ ] Verify source CI. Push `v0.1.0` only after owner approval; verify amd64/arm64 GHCR image build and an arm64 runtime smoke.
- [ ] Make the first GHCR package public (the registry initially defaults to private), then verify an anonymous pull and a clean Compose installation.
- [ ] Publish GitHub Release with the reviewed source archive and exact image digest.

The currently running private Wallos service is not changed by this package work. The project cannot claim compatibility with untested future Wallos releases or zero defects.
