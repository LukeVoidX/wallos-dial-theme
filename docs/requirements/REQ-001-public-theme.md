# REQ-001: Public Wallos Dial theme

Status: historical 0.1.0 scope; see the 1.0 specification and release checklist for current work
Risk: L2 (public reusable theme); each new GitHub/GHCR/Demo release requires its own approval
Delivery: versioned Docker image source for self-hosted Wallos
Review mode: retained as the original requirements record

## Outcome
A Wallos user can build and run the Dial desktop theme from a small, standalone repository while retaining their subscriptions, settings, and normal Wallos features.

## Original 0.1.0 scope
Package the Braun-inspired interface as the independently named **Wallos Dial**. Include pinned upstream v5.8.1 Docker base, theme overlays, installation, rollback, compatibility, license/attribution, and optional logo mapping. The package must not contain the maintainer's subscription database, uploaded logos, host configuration, personal mappings, authenticated screenshots, or credentials.

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

## 1.0 update

This file records the original 0.1.0 scope and does not describe the current 1.0 candidate. Dial 1.0 targets **Wallos 5.8.3** and adds category management and per-account icon choices; see the approved [1.0 design](../superpowers/specs/2026-10-17-wallos-dial-1.0-design.md), [icon guide](../ICONS.md), [category guide](../CATEGORIES.md) and [release checklist](../RELEASE-CHECKLIST-1.0.0.md).

The design document initially considered nine service-mark candidates. Luke subsequently chose nine **neutral, first-party pictograms** while retaining their service keys and names. The [catalog provenance](../../theme/images/dial-catalog/SOURCES.md) describes the resulting 1.0 drawings. The historical “no third-party brand SVGs” rule remains true for the public package; personal SVGs, original uploads, private filename mappings and account data remain excluded. This update does not approve GitHub publication or a public Demo change.
