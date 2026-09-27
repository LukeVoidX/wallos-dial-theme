# REQ-008 — Bilingual repository and real, isolated live demo

Status: revised after visual review on 2026-09-27; local real runtime verified, public publication pending.

## Outcome

Visitors can read the project in English or Simplified Chinese and use a public URL to inspect the **actual Wallos Dial application** with fictional data. The earlier hand-built static imitation was rejected because its layout, logos, and interactions diverged from the installable theme.

## Architecture

- GitHub Pages at `https://lukevoidx.github.io/wallos-dial-theme/` is the free entry URL. Its `docs/index.html` forwards to `https://wallos-demo.mostai.org/`.
- The destination runs the same versioned Wallos Dial Docker image as the release, on a separate server container, hostname, port, SQLite volume, and logo directory.
- `demo/seed.py` creates a fictional account, 30 subscriptions across AI/Infra/Domains/Media, and SVG marks, refusing any populated database.
- Public reverse proxy permits read-only application browsing and the language endpoint. The language selection sets a visitor cookie without changing the shared demo account. All other writes and administrative paths are blocked. A reset job periodically restores the synthetic seed.
- The local preview at `127.0.0.1:4190` runs the actual container with the same synthetic dataset. It is not publicly reachable.

## Acceptance

The local real app must load dashboard, 30 grouped subscriptions, calendar, statistics, details, search, and English/Simplified Chinese with no page errors or horizontal overflow at 900–1600 px. The public demo needs anonymous HTTPS checks for the entry redirect and real runtime, write blocks, privacy isolation, reset, and compatibility with the tested image. README links, screenshots, and wording must distinguish local checks from a published URL.

## Boundary

GitHub Pages cannot host PHP/SQLite. It only forwards to the real demo. The public demo remains read-only because visitors share one synthetic account. Full account and editing functions remain in the installable image. The private Wallos instance must not be copied or modified. Public repository creation, DNS/Caddy changes, demo container deployment, GHCR image and GitHub Release require a final review and owner confirmation.
