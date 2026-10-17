# Changelog

## 1.0.0 — local release candidate

Target base: Wallos 5.8.3. The local source has been promoted to version `1.0.0` for final review. This entry does not assert that a GitHub Release, GHCR image, or Demo update has occurred.

### Added

- Account-scoped Manage categories page with add, rename, keyboard/drag reorder, safe merge, empty-category deletion, and an optional starter set for empty accounts.
- Per-subscription Icon chooser with Original, nine first-party neutral catalog pictograms, and My Icons, plus plain/framed, color/monochrome, and 80–120% optical adjustment.
- Private My Icons library in the account's SQLite database, including normalized image uploads, owner-checked retrieval, quota controls, and safe fallback to the original Wallos Logo.
- Consistent icon resolution across the subscription list, dashboard and detail view. Existing uploaded Logo files and optional private legacy SVG mappings remain available.
- Real read-only category-manager view for the isolated synthetic Demo, subject to the separate Demo deployment approval.

### Compatibility and release boundary

- Rebased the pinned source overlay and exact-file hash gate to Wallos 5.8.3; upstream security and mail behavior is included in the reviewed overlay.
- The 0.1.1 email templates and language switch continue in 1.0. Renewal/cancellation plain text and optional period summaries now follow the recipient language. Dynamic account pages and uploaded Logos are no longer stored by the Service Worker. A new upstream Wallos release still requires a reviewed Dial image.
- The final combined security, upgrade/rollback, browser, dual-architecture, Demo and publication checks are recorded in [the 1.0 checklist](docs/RELEASE-CHECKLIST-1.0.0.md) before release. Unchecked items are not release claims.

## 0.1.1

### Added

- Warm ivory HTML templates for renewal, cancellation, test, verification, and password-reset emails, with plain-text alternatives.
- English, Simplified Chinese, and Traditional Chinese template labels based on the account language; other locales fall back to English.
- Email before/after poster made from the original Wallos 5.8.1 plain-text body and the actual Dial template output with the same fictional data.

### Safety and compatibility

- Email action links use the installation's configured Wallos Server URL. Optional links are omitted if it is unavailable; account emails remain queued until a valid URL is configured.
- Recipient selection, SMTP configuration, and notification schedules are unchanged. The five additional upstream mail entrypoints are protected by the build's exact-file hash gate.
- Wallos 5.8.1 remains the only tested upstream version.

## 0.1.0

### Added

- Dial desktop presentation extracted from a Wallos 5.8.1 overlay.
- Versioned, pull-only Compose installation backed by a planned GHCR image for `linux/amd64` and `linux/arm64`; source-build Compose remains available.
- Upstream file hash gate, package checks, runtime health smoke, scheduled upstream-release notice, and tag-triggered image publishing workflow.
- Optional user-owned SVG mapping without bundled brand art.
- Shared-header language switch that saves the account locale using Wallos translations.
- Centered 1480px maximum width for the header and dashboard on wide desktop windows.
- Localized calendar-day countdown beside upcoming payment dates, with restrained emphasis for payments due within three days.
- Adjusted the final timeline tooltips at compact desktop widths so hidden date labels cannot create horizontal scrolling.
- Restored the visible globe icon on the subscription details URL action before hover.
- Replaced the browser tab favicon with a crisp, original warm-white and black W monogram.
- Added a public visual gallery: ImageGen-backed posters and direct before/after browser captures from a synthetic demo account.
- Added English and Simplified Chinese install guidance, troubleshooting, contribution instructions, and launch notes.

### Operational notes

- Wallos 5.8.1 is the only tested upstream version. Later versions require an explicit compatibility release.
- The public package excludes databases, uploaded logos, personal mappings, credentials, and live deployment configuration.
