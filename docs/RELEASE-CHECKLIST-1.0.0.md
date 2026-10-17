# Wallos Dial 1.0.0 release checklist

**Status: local candidate passed, external release pending.** No item below represents GitHub, GHCR, public Demo, private Wallos, or community publication. The local findings are from 2026-10-17; recheck the live Demo and remote branch immediately before any approved switch.

## Candidate identity

| Item | Evidence |
| --- | --- |
| Source commit SHA | Implementation commit `01bb613ff129e3901fd1c33b611d1945cb87d1a5`; any later documentation-only checklist commit must be included in the tag and recorded at the final approval. |
| Root `VERSION`, image label, `DIAL_VERSION`, Compose and tag contract | `1.0.0` aligned; `tests/release/version-contract.sh` and full gate passed. Demo Compose default deliberately remains public `0.1.1` until its approved switch. |
| Tested upstream | Wallos 5.8.3; `bellamy/wallos:5.8.3@sha256:0006879778f31a962a3873f7682514b92a3c7665dcbc4764fcc241ad968eaccd` |
| SVG rasterizer | Alpine `rsvg-convert=2.62.3-r0` pinned in Dockerfile and verified in each built architecture |
| Source vs `origin/main` ancestry after fetch | Last read-only fetch: `origin/main` `368a7d602daee9441f8ae704768d84189a6e86de` is an ancestor of this local branch (`git merge-base --is-ancestor` exit 0). Re-fetch immediately before any approved push. |
| Release notes and exact GHCR tag | [draft notes](RELEASE-NOTES-1.0.0.md); proposed `ghcr.io/lukevoidx/wallos-dial:1.0.0` |

## Local acceptance evidence

| Gate | Result and artifact |
| --- | --- |
| Full `scripts/verify-1.0-candidate.sh` and CI contract | Passed on the exact implementation commit with `wallos-dial:commit-gate`; ignored `data/verify-1.0-commit.log` ends with complete gate passed. CI/tag workflow contract passed. |
| Package scan, Git history, build context and image-layer privacy audit | Passed. `scripts/check-package.sh` and independent OCI scan of both exact-commit architecture images: 28 layers each, exactly nine neutral catalog SVGs, zero personal SVG/domain paths or content, no private DB/keys/maps. Ignored reports: `data/release-audit-commit-{amd64,arm64}.json`. The only image DB is the upstream empty template. |
| Fresh Wallos 5.8.3 start and SQLite integrity | Fresh synthetic template, 30-record seed, image smoke and SQLite integrity passed in full gate; no private DB mounted. |
| Dial 0.1.1 → 1.0.0 migration with database and Logo volume backup | `tests/compatibility/upgrade-rollback.sh` passed on disposable copies and checks the migrated schema; real user volumes were not touched. |
| 0.1.1 rollback on a disposable copy, normal add/edit/delete, then re-upgrade | Same compatibility test passed, including old-image writes, rollback and re-upgrade. Tampered upstream hash rejected. |
| Category CRUD/merge/starter and two-account isolation | PHP, HTTP and browser tests passed, including owned IDs, CSRF, Demo read-only and repeated `AI & Tools` encoding. |
| Original/Catalog/My Icons across all views; malicious upload and foreign-image denial | HTTP/browser tests passed, including account isolation, 2 MiB/size limits, SVG rejection, low-resolution warning, quick Change preservation, list/grid/dashboard/calendar/details and original fallback. |
| Old and new service-worker cache tests, including account switch | Passed. Browser report shows zero private images/account pages cached and foreign-image HTTP 404; old cache keys removed. |
| Browser journey: English, 简体中文, 繁體中文; 900/1440/1920 px and 80/100/125% zoom | 28 synthetic screenshots, 30 fictional subscriptions, no browser errors or horizontal overflow in CSS zoom equivalents; ignored `data/qa-1.0/qa-manifest.json`. Native macOS Chrome 80/100/125% zoom on the functional release candidate (followed only by PHP whitespace cleanup) passed across overview, subscriptions, categories, calendar and statistics (15 page/zoom combinations, DPR 1.6/2/2.5, three rendered ApexCharts); ignored `data/qa-1.0/native-chrome-zoom-stable-2026-10-17.json`. Five clean captures are in `docs/assets/screenshots/dial-1.0-*.png`. |
| Five email paths through isolated SMTP capture | Passed per `en`, `zh_cn`, `zh_tw`: renewal, cancellation, SMTP test, verification, password reset; HTML/plain, localized subjects, RFC 2047, fictional URL and a two-account invalid-payer cancellation case. Ignored `data/mail-qa-1.0/manifest-*.json`. |
| Other notification channel receipts or documented coverage gap | Nine local receipts through Discord, Gotify, Ntfy, Webhook and Mattermost on Docker `--internal` network passed. External URL channels and Web Push were not exercised end to end. |
| `linux/amd64` image build and runtime smoke | Exact-commit `wallos-dial:commit-amd64`, ID `sha256:10ca3eeda35e3c6840a3620afb8ea99dc32ccb29d33841d77b06228b7ceefa9b`; health/theme smoke, SVG normalize, pinned rasterizer and OCI privacy checks passed. |
| `linux/arm64` image build and runtime smoke | Exact-commit `wallos-dial:commit-arm64`, ID `sha256:f59c6df89553d2a3d6d2f6b57a9e445addd26d04f768da0355fee01a0779702e`; same checks passed. These are local image IDs, not a published multiarch digest. |
| Local Demo Mode, Caddy allow-list and blocked mutation tests | Caddy/Nginx/HTTP and browser tests passed; reset preflight fails closed and injected DB swap, Logo copy, and public-smoke failures restore old synthetic DB plus Logos. |
| Documentation contract and relative-link audit | Passed. Independent scan of 42 README/docs/HTML files and 297 links found no missing local target. |

## Public Demo change record

Read the live Demo state **before** requesting approval; do not guess these values from repository examples.

| Item | Observed value |
| --- | --- |
| Current public Demo image ID/digest | Read-only 2026-10-17 check: `ghcr.io/lukevoidx/wallos-dial:0.1.1`, local image ID `sha256:943374e75aaa71b5366b1620f54c35dcf1ca58d1a64c52eb71521599ba267c1f` (`amd64`) |
| Container and Compose location | `wallos-dial-public-demo`; Compose label points to `/srv/wallos-dial-public-demo/source/demo/compose.yaml` (`source` HEAD `ac9b314a3e6f82937f86192898f079c7d0da2f27`). Its Demo files have local hotfixes; preserve them during cutover rather than resetting that checkout. |
| Exact DB and Logo mount paths and demo-only marker | `/srv/wallos-dial-public-demo/db` → `/var/www/html/db`; `/srv/wallos-dial-public-demo/logos` → `/var/www/html/images/uploads/logos`; `/srv/wallos-dial-public-demo/.wallos-dial-demo-only` exists. Container reports `DEMO_MODE=1`. Current DB: 1 synthetic account, 30 subscriptions, 58 migrations, `PRAGMA integrity_check=ok`. |
| Reset job, current schedule and latest successful run | `wallos-dial-demo-reset.timer` every two hours; service calls `/srv/wallos-dial-public-demo/source/demo/reset.sh /srv/wallos-dial-public-demo`. Last observed success: 2026-10-17 12:00:02–12:00:04 CEST. Current 0.1.1 service/script remains untouched until the approved switch. |
| Current Caddy site, route rules and health response | `/etc/caddy/Caddyfile` SHA-256 `81135c205d0818a088798d4c5041f771256327eb5f4107735931038a265cbd80`. Current site allows Cloudflare-origin GET pages/assets and language POST, redirects `/logout.php` home, denies other routes. Public `https://wallos-demo.mostai.org/health.php` returned HTTP 200; home redirected to `/login.php` during anonymous curl. An ignored local full-file candidate and 32-line Demo-only diff have SHA-256 `be5c02665fa1015a21a8d60249ffed0d7ab95ecd84ad98319c8e4704bbd92b60`; abort if the live base SHA drifts. |
| Synthetic DB and Logo backup location | Proposed `/srv/wallos-dial-public-demo/backups/pre-1.0.0` (read-only check found no existing path). It is created only after release approval. |
| Proposed image switch command | Pending approval. The ignored local `data/demo-cutover-1.0.0.md` is the exact host-specific runbook. It preserves the dirty existing 0.1.1 source checkout, stages a clean 5.8.3 synthetic DB in a new tag worktree, stops only the Demo timer/container, snapshots matching DB/Logo/template plus Caddy/systemd, sets exact `1.0.0` image and Demo URLs, applies the reviewed Demo-only Caddy diff, then verifies reset and HTTPS smoke before restarting the timer. Current `.env` still says `0.1.0`, so the new worktree gets its own correct environment. |
| Exact old-image and synthetic DB restore commands | Same ignored runbook: move failed new volumes aside, restore the matched `old-db`, `old-logos`, `old-template`, previous Caddy and systemd files, and recreate only Compose project `demo` with an explicit `WALLOS_DEMO_IMAGE=ghcr.io/lukevoidx/wallos-dial:0.1.1` override. Preserve the old source hotfixes and verify its original image ID, health, 30 synthetic records and read-only routes before restarting the timer. Do not use the 1.0 reset script with the 0.1.1 image. |
| Anonymous categories, details, icon-image and blocked-write probe | pending |

The private Wallos container, database and Logo volume are outside the public Demo switch. A failed public Demo health, data, or read-only check requires restoring its previous image and matching synthetic database backup.

## Decision and publication record

- [ ] Luke reviewed the commit SHA, release notes, upstream digest, local test results, privacy audit, both architecture IDs, observed Demo state and exact rollback commands.
- [ ] Luke explicitly approved pushing `main`, creating `v1.0.0`, publishing the GHCR image and GitHub Release, and updating **only** the public Demo. This is a separate decision from local implementation approval.
- [ ] After approval: GitHub tag/Release URL, CI run ID, GHCR multi-architecture digest and anonymous pull readback: pending.
- [ ] After approval: public Demo image digest, HTTPS page/asset responses, 30 synthetic records, read-only probes, reset result and date: pending.
- [ ] Any follow-up documentation push is separately covered by the release approval or approved again: pending.

NodeSeek, V2EX, X and other announcements require their own review and authorization.
