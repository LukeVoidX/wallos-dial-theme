# REQ-007: Public launch materials and release

Status: local visuals and dual-architecture preflight verified; external publication pending owner confirmation
Risk: L3 public source and container publication
Delivery: public GitHub repository, versioned GHCR image, GitHub Release after review

## Outcome
Give other Wallos users a truthful, appealing, and installable Wallos Dial release. The repository opens with clear product visuals, actual before/after screenshots, concise benefits, complete installation and upgrade guidance, and a recoverable fixed-version deployment path.

## Scope and non-goals
Create four editorial posters using ImageGen backgrounds and exact browser captures from the same sanitized, synthetic demo account. Include raw demo screenshots, provenance, English and Simplified Chinese instructions, troubleshooting, contribution guidance, release notes, and launch copy. Validate archive, Docker builds, startup, and the existing feature gates. No private account screenshots, database, uploaded logos, credentials, private host configuration, or automatic upstream upgrade. No phone redesign or guarantee of zero defects.

## Acceptance evidence
- Posters read at GitHub README width and are clearly marked as synthetic demo data; UI portions are direct browser captures, not regenerated controls.
- Screenshot content is checked for private identifiers and the public package scan finds no account data or secrets.
- README installation steps match the exact Compose file and tested Wallos version; update and rollback instructions are actionable.
- Build, PHP/JavaScript syntax, fresh registration, populated demo dashboard/list/calendar/stats, runtime health, and archive checks pass.
- The reviewed commit, archive digest, image tag, target repository visibility, and GitHub Release body are fixed before publication.

## Constraints and authorization
The public repository name is `LukeVoidX/wallos-dial-theme`; the planned image is `ghcr.io/lukevoidx/wallos-dial:0.1.0`. A public push, tag, image publication, GHCR visibility change, and GitHub Release are consequential actions. Prepare everything locally first, then request a final confirmation of the exact reviewed candidate before those actions. If CI, arm64, image visibility, or anonymous install fails, stop and report the partial state rather than announcing release success.
