# 0.1.1 publication checklist

- [x] Private hard-coded hostname removed from the public mail code and fixture.
- [x] Five mail entrypoints are derived from the pinned Wallos 5.8.1 files; exact upstream hashes match the pinned image.
- [x] Local image builds, PHP overlay lints, mail rendering and PHPMailer MIME tests pass without sending email.
- [x] Runtime health and themed asset smoke passes on the local candidate image.
- [x] Before/after email poster uses fictional data and the actual Dial HTML function; original message content follows Wallos 5.8.1.
- [x] Both Compose files validate on a host with Docker Compose v5.3.1.
- [x] Send a synthetic message from the candidate image to an isolated Mailpit container; inspect that the captured message has Chinese HTML and the original plain-text alternative. No external email was sent.
- [x] Exercise renewal, cancellation, verification, and password-reset cron paths with an isolated synthetic account and Mailpit. Check Chinese HTML, original plain text or account link, fictional recipient, and configured URL. Empty URL omits optional reminder links; invalid account URL leaves verification pending without sending.
- [ ] Exercise the authenticated SMTP test endpoint in a disposable Wallos browser session; its rendering and MIME path have been tested separately.
- [ ] Review source and documentation, then obtain owner confirmation for public GitHub push, tag, image release, and demo update.
- [ ] Verify GitHub source CI and multi-arch image publication.
- [ ] Update only the isolated public Demo image; verify 30 synthetic records, read-only proxy, languages, and health.
- [ ] Publish GitHub Release and reread the public README, poster, image, and demo.
- [ ] Update NodeSeek and V2EX drafts with the fourth image and final release version; obtain separate approval for public posts.
