# REQ-001: Public Wallos Dial theme

Status: verified locally; public publication pending approval
Risk: L2 (public reusable theme); GitHub publication is a separate external action
Delivery: Docker image source for self-hosted Wallos, then public GitHub repository after approval
Review mode: batch preparation requested; final publication gate remains

## Outcome
A Wallos user can build and run the Dial desktop theme from a small, standalone repository while retaining their subscriptions, settings, and normal Wallos features.

## Scope
Package the current Braun-inspired interface as the independently named **Wallos Dial**. Include pinned upstream v5.8.1 Docker base, theme overlays, installation, rollback, compatibility, license/attribution, and optional logo mapping. The package must not contain the maintainer's subscription database, uploaded logos, host configuration, personal mappings, authenticated screenshots, or credentials.

## Acceptance evidence
- All Dockerfile `COPY` sources exist and the image builds from a clean package.
- PHP and JavaScript syntax checks pass.
- A disposable instance renders dashboard, subscriptions, calendar, statistics, and settings; primary navigation and add-form controls work.
- No personal data or deployment secrets in tracked files or Git history.
- README states that this is an overlay pinned to Wallos v5.8.1, not a native theme plugin; upgrades require compatibility review.
- Public repository name, description, release content, and exact files are reviewable before publication.

## Constraints and risks
The upstream PHP and JavaScript modifications are derivatives of GPLv3 Wallos and must retain GPLv3 notices. No third-party brand SVGs ship. Users may mount their own optional SVGs; uploaded logos retain their original artwork by default. Docker binds in examples must be local-only by default. Do not alter any live instance or import his data into the public package. Desktop is the designed target; existing mobile behavior is retained without a new mobile design promise.

## Version and publication
Initial candidate: `0.1.0`, compatible with upstream Wallos `5.8.1` only. Build is local first. Creating a public GitHub repository, pushing commits, tagging, or publishing a release requires Luke's final approval of the reviewed candidate.
