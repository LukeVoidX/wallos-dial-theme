# 0.1.0 publication checklist

- [x] Source extracted to a standalone repository; no upstream repository history copied.
- [x] GPLv3 and upstream attribution included.
- [x] No database, uploaded logos, personalized logo map, auth material, or live host config in the candidate.
- [x] Pinned Wallos 5.8.1 Docker image builds.
- [x] PHP and JavaScript syntax checks pass.
- [x] Disposable runtime checks: dashboard, 16 subscription cards, calendar events, statistics sections, settings, and add form. Data was a private disposable copy and is not part of the package.
- [x] Example deployment binds to `127.0.0.1` and persists DB and uploaded logos outside the image.
- [ ] Owner reviews repository name, README, code, and release candidate archive.
- [ ] Create public `LukeVoidX/wallos-dial-theme` repository and push exact reviewed commit.
- [ ] Confirm remote commit and CI; create `v0.1.0` release after owner approval.

Tested from a disposable copy of a Wallos database. The source package itself contains no data. Upstream upgrades beyond 5.8.1 are not yet validated.
