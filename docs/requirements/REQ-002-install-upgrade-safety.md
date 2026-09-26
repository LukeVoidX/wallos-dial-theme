# REQ-002: Easy installation and safe Wallos upgrades

Status: implemented; local and disposable verification passed; publication pending
Risk: L2 (distribution and update behavior); publication remains a separate external action
Delivery: public source repository plus prebuilt GHCR image after approval
Review mode: batch preparation requested; public publication gate remains

## Outcome
A new user installs Dial by pulling a published multi-architecture image with a short Compose workflow. Existing Wallos users can migrate their mounted data with a clear backup and rollback path. A future upstream release cannot silently replace modified templates without a compatibility review.

## Scope and non-goals
Add a pull-only Compose file, GHCR publishing workflow, compatibility checks against the pinned upstream image, runtime smoke test, and upstream-release monitoring. Keep the source-build path for contributors. Do not alter any live Wallos service or perform an automatic database migration. Do not promise compatibility with an untested future Wallos version.

## Acceptance evidence
- From a clean checkout, image build, PHP/JS syntax checks, Compose validation, and health smoke test pass.
- The public Compose uses a versioned GHCR tag, loopback binding, and persistent DB/logo volumes; no local build is required for installation.
- CI can publish amd64 and arm64 images on an approved release tag, with source/revision labels.
- Replacing a modified upstream file in a new base without reviewing the change makes the compatibility gate fail.
- A scheduled check reports when upstream latest differs from the declared supported Wallos version.
- README clearly separates new install from migration and explains compatible versions, data safety, rollback, and the no-guarantee boundary.

## Constraints and risks
GHCR packages are private by default on first publication; the owner must make the package public and verify anonymous pulls. Auto-updating to `latest` is prohibited. New upstream versions require validation and a new theme image, even if the theme's visible design is unchanged. The public installer must not ingest, export, or overwrite private data by default.

## Version and publication
Keep Dial `0.1.0` as the initial candidate unless source-visible behavior requires a new release candidate. `VERSION` is the canonical Dial version; `WALLOS_VERSION` is the tested upstream version. Repository creation, image publication, tag/release publication, and package visibility change await final approval.
