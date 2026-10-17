# Wallos Dial 0.1.1

This release carries Dial's warm ivory visual system into Wallos email notifications. It remains pinned to Wallos 5.8.1.

![Original Wallos renewal message and rendered Dial email template](https://raw.githubusercontent.com/LukeVoidX/wallos-dial-theme/v0.1.1/docs/assets/posters/compare-email.png)

## What changed

- Renewal, cancellation, SMTP test, email verification, and password-reset emails use one HTML template, with a plain-text alternative.
- Template text follows English, Simplified Chinese, or Traditional Chinese account language; other locales use English. Subscription names and amounts remain unchanged.
- Optional notification buttons use the installation's configured Wallos Server URL. Account emails stay queued when that URL is invalid, so no unusable verification or reset link is sent.
- The original recipient, SMTP, and scheduling logic remains in Wallos. Five additional upstream files are now checked by exact hash before overlaying.

## Install or upgrade

The versioned image is `ghcr.io/lukevoidx/wallos-dial:0.1.1` for `linux/amd64` and `linux/arm64` after the release workflow passes. Follow the [English README](https://github.com/LukeVoidX/wallos-dial-theme/blob/v0.1.1/README.md) or [中文说明](https://github.com/LukeVoidX/wallos-dial-theme/blob/v0.1.1/README.zh-CN.md), back up your SQLite database and uploaded logos, update your Compose image tag, then check an SMTP test email. The public [live demo](https://wallos-demo.mostai.org/) is read-only; it cannot send email.

Wallos versions after 5.8.1 need a separate compatibility review. The comparison image uses the original plain-text body and the actual Dial HTML function with the same fictional subscriptions, placed in illustrative inbox frames. It does not depict a delivered inbox message.
