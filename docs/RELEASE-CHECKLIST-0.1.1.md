# 0.1.1 publication checklist

- [x] Private hard-coded hostname removed from the public mail code and fixture.
- [x] Five mail entrypoints are derived from the pinned Wallos 5.8.1 files; exact upstream hashes match the pinned image.
- [x] Local image builds, PHP overlay lints, mail rendering and PHPMailer MIME tests pass without sending email.
- [x] Runtime health and themed asset smoke passes on the local candidate image.
- [x] Before/after email poster uses fictional data and the actual Dial HTML function; original message content follows Wallos 5.8.1.
- [x] Both Compose files validate on a host with Docker Compose v5.3.1.
- [x] Send a synthetic message from the candidate image to an isolated Mailpit container; inspect that the captured message has Chinese HTML and the original plain-text alternative. No external email was sent.
- [x] Exercise renewal, cancellation, verification, and password-reset cron paths with an isolated synthetic account and Mailpit. Check Chinese HTML, original plain text or account link, fictional recipient, and configured URL. Empty URL omits optional reminder links; invalid account URL leaves verification pending without sending.
- [x] Exercise the authenticated SMTP test endpoint in a disposable Wallos 0.1.1 instance using a synthetic session and Mailpit. The endpoint returned success; captured mail had Chinese HTML and a plain-text alternative.
- [x] Owner confirmed public GitHub publication and isolated Demo update. Source commit `0e94189` and tag `v0.1.1` are public.
- [x] GitHub source CI and tag publication CI passed. Anonymous GHCR manifest reports `linux/amd64` and `linux/arm64`, with image-index digest `sha256:943374e75aaa71b5366b1620f54c35dcf1ca58d1a64c52eb71521599ba267c1f`.
- [x] Only the isolated public Demo was recreated on `ghcr.io/lukevoidx/wallos-dial:0.1.1`; it is healthy with 30 synthetic records and SQLite integrity `ok`. The reverse proxy still returns 404 for admin and write paths. Its scheduled reset path was exercised and retained 0.1.1. The private Wallos container stayed running on its previous image.
- [x] GitHub Release is published; English and Chinese GitHub Pages both show 0.1.1 and the email poster. All four post images return HTTP 200 from the version tag.
- [ ] Update NodeSeek and V2EX drafts with the fourth image and final release version; obtain separate approval for public posts.
