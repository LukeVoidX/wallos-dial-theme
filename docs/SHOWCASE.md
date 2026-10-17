# Wallos Dial showcase

[Browse the six-part poster series in English](CAMPAIGN.md) · [查看六张中文主题海报](CAMPAIGN.zh-CN.md)

The historical UI comparisons below were captured directly from Wallos 5.8.1 and Wallos Dial 0.1.0 running with **the same synthetic demo database**. Service names, prices, user, logos, and payment method are fictitious. ImageGen was used for the editorial backgrounds; it did not redraw the UI screenshots. They show the original design change and predate the current 1.0.0 / Wallos 5.8.3 release.

The email comparison below is a separate Dial 0.1.1 fixture: it renders the original Wallos 5.8.1 renewal message body and the actual Dial HTML template with the same two fictional subscriptions. The mail-client frame is presentation artwork; it is not an inbox capture or evidence that an SMTP message was delivered.

## 1.0 direct browser captures

Dial 1.0.0 runs on Wallos 5.8.3. These are direct browser captures from an **isolated 1.0 release fixture** at a 1440 px CSS viewport and 100% browser zoom on 2026-10-17. The fixture contains 30 fictional subscriptions; its service names, amounts, domains, and payment methods are demonstration data. The icon chooser uses real service names only as labels for nine independent, neutral pictograms. No personal subscription records or private SVGs appear in these images. They were captured locally, so they are not screenshots of the public Demo hostname; the public Demo runs the same version with its own synthetic data.

### Overview and grouped subscriptions

![Dial 1.0 local overview with monthly cost, date dial, upcoming payments, and countdown](assets/screenshots/dial-1.0-overview.png)

![Dial 1.0 local subscription grid with fictional AI, Infra, Domains, and Media entries](assets/screenshots/dial-1.0-subscriptions-grid.png)

### Category manager and icon chooser

![Dial 1.0 local category manager showing account categories and subscription counts](assets/screenshots/dial-1.0-categories.png)

![Dial 1.0 local icon chooser with nine neutral catalog symbols and light and dark previews](assets/screenshots/dial-1.0-icon-chooser.png)

The [category guide](CATEGORIES.md) explains account-scoped ordering and merging; the [icon guide](ICONS.md) explains Original, built-in, and My Icons choices. The selected symbol is shown on warm and dark preview surfaces at its actual display size.

### Statistics

![Dial 1.0 local statistics with subdued charts and the same fictional account data](assets/screenshots/dial-1.0-statistics.png)

The [release checklist](RELEASE-CHECKLIST-1.0.0.md) records the image, fixture, viewport and browser QA. The older posters below document the original 0.1.0 redesign; they are not 1.0 screenshots.

## Design direction and product showcase

![Design principles: measured hierarchy, calibrated time, useful grouping, restrained signal](assets/posters/design-principles.png)

The warm ivory surface, dark figures, precise date marks and small red accents define the system. The generated background suggests an instrument dial; the poster copy and palette are typeset separately.

![Overview showcase using an actual Wallos Dial browser capture](assets/posters/showcase-overview.png)

![Grouped subscriptions showcase using an actual Wallos Dial browser capture](assets/posters/showcase-subscriptions.png)

![Calendar showcase using an actual Wallos Dial browser capture](assets/posters/showcase-calendar.png)

![Statistics showcase using an actual Wallos Dial browser capture](assets/posters/showcase-statistics.png)

The overview and subscription showcases use [actual demo overview](assets/screenshots/real-demo-overview.png) and [grouped grid](assets/screenshots/real-demo-grid.png) captures from the 30-record fictional demo. The calendar and statistics posters use [calendar](assets/screenshots/after-calendar.png) and [statistics](assets/screenshots/after-stats.png) captures from the 16-record synthetic review fixture. Interface details were not redrawn.

## Overview

![Original Wallos and Dial dashboard, side by side](assets/posters/compare-dashboard.png)

Full-resolution captures: [original Wallos](assets/screenshots/before-dashboard.png) · [Wallos Dial](assets/screenshots/after-dashboard.png)

Dial puts monthly cost, the date timeline, upcoming payments, and days remaining on one screen. The source Wallos page used the same subscription records.

## Subscriptions

![Original Wallos and Dial subscription pages, side by side](assets/posters/compare-subscriptions.png)

Full-resolution captures: [original Wallos](assets/screenshots/before-subscriptions.png) · [Wallos Dial](assets/screenshots/after-subscriptions.png)

Dial groups subscriptions into categories while retaining search, filters, sorting, list/grid views, and subscription actions.

## Other views

![Overview, calendar, and statistics in one visual system](assets/posters/features.png)

Full-resolution captures: [calendar](assets/screenshots/after-calendar.png) · [statistics](assets/screenshots/after-stats.png)

These are presentation images, not a promise of identical appearance on every screen size. Dial is designed primarily for desktop. The screenshots were captured at a 1600×1000 CSS viewport on 2026-09-27. No personal subscription records or private logos are included.

## Email notifications

![Original Wallos plain-text renewal message beside the rendered Dial 0.1.1 HTML template retained in 1.0](assets/posters/compare-email.png)

The source fixture is [`render-mail-fixtures.php`](assets/posters/source/render-mail-fixtures.php); it calls the same [`dial_mail_theme.php`](../theme/includes/dial_mail_theme.php) function used by the installable image. The template was introduced in Dial 0.1.1 and remains in 1.0. The original message text follows Wallos 5.8.1's renewal message builder. [Inspect the text](assets/posters/source/mail-original-body.txt) and [rendered HTML](assets/posters/source/mail-dial-render.html).
