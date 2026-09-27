# REQ-008 — Bilingual repository and interactive Pages demo

Status: accepted for local implementation on 2026-09-27; public publication pending final review.

## Outcome

Visitors can read the project in English or Simplified Chinese and try the Braun-inspired interface immediately at `https://lukevoidx.github.io/wallos-dial-theme/` after GitHub Pages is enabled.

## Scope

- Put a standalone static demo at `docs/index.html`, deployable from `main` `/docs` on GitHub Pages.
- Offer English and Simplified Chinese within the demo. Both repository READMEs link to the demo, install path, and comparisons.
- Include a larger synthetic subscription set across AI, Infra, Domains, and Media, with varied prices, intervals, and renewal dates.
- Make navigation, search, filter, sort, detail view, add/edit/delete, calendar, statistics, language switch, and reset usable without a server. Browser storage may keep demo changes locally.
- State clearly that all records are fictional and the demo does not connect to a Wallos database, payment service, or Luke's server.
- Provide publication steps and a manual acceptance check.

## Acceptance

At desktop widths 1600, 1100, and 900 px, primary content remains usable without horizontal scrolling. English and Simplified Chinese both work. Interactive controls have visible focus and meaningful results. Reload preserves a demo edit; reset restores seed data. No production hostname, private subscription, email, or credential is bundled. Paths work below the GitHub Pages project prefix. The shipped Docker theme and its compatibility gate remain unchanged.

## Boundary

GitHub Pages hosts static HTML, CSS, and JavaScript. The demo is a representative UI preview; the PHP-backed Wallos functions require installing the Docker image. Public repository creation, Pages activation, image publication, and release publication require a final review of the complete package.
