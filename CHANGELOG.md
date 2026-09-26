# Changelog

## 0.1.0 — release candidate

### Added

- Dial desktop presentation extracted from a Wallos 5.8.1 overlay.
- Versioned, pull-only Compose installation backed by a planned GHCR image for `linux/amd64` and `linux/arm64`; source-build Compose remains available.
- Upstream file hash gate, package checks, runtime health smoke, scheduled upstream-release notice, and tag-triggered image publishing workflow.
- Optional user-owned SVG mapping without bundled brand art.
- Shared-header language switch that saves the account locale using Wallos translations.
- Centered 1480px maximum width for the header and dashboard on wide desktop windows.

### Operational notes

- Wallos 5.8.1 is the only tested upstream version. Later versions require an explicit compatibility release.
- The public package excludes databases, uploaded logos, personal mappings, credentials, and live deployment configuration.
