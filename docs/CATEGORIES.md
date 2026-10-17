# Manage categories

[English](CATEGORIES.md) · [简体中文](CATEGORIES.zh-CN.md)

Open **Subscriptions → Manage categories**, or use **Settings → Categories → Manage categories**. Each account manages its own category list. Existing Wallos categories and subscription assignments remain in place when Dial is installed.

## Daily changes

- **Add or rename:** enter a name of 1–60 characters and save. Names are your data; switching the interface language does not translate them.
- **Reorder:** drag a row by its handle, or use its Up and Down buttons with a keyboard. A protected default row, when present, stays first.
- **Merge:** choose a destination and review the affected subscription count before confirming. Every subscription retains its ID, amount, payment date, payment method, and uploaded logo. Only its `category_id` changes; the source category is then removed. This includes inactive and one-time records.
- **Delete:** an empty category can be deleted after confirmation. If any subscription still points to it, use Merge first. A protected default row cannot be deleted.

## Optional starter categories

An account with **no subscriptions at all** can choose **Use starter categories**. The preview adds only missing names from **AI, Infra, Domains, Media**. It lists each existing empty category eligible for removal; every removal checkbox starts unchecked. Review and select exact rows before confirming. A protected default row is retained. This starter action is optional and never replaces an existing subscription ledger automatically.

The manager rejects stale previews if another tab changes a category or adds a subscription before confirmation. Reopen the preview and review the current list.

The [public Demo](https://wallos-demo.mostai.org/) displays its fictional categories but does not permit changes. Install your own [versioned Dial image](../README.md#install-a-new-instance) to manage categories. The Demo’s four groups are sample data, not defaults forced onto an installation.
