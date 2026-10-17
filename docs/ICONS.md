# Choose subscription icons

[English](ICONS.md) · [简体中文](zh-CN/ICONS.md)

Wallos Dial keeps each subscription's original Wallos Logo. You can choose its **Original** image, a bundled catalog symbol, or an image from **My Icons**. The choice and display style belong to your account and appear consistently in the dashboard, subscription cards, and details. If an older entry has no image, its name supplies a letter fallback.

## Choose and adjust

Open **Subscriptions → Add subscription** or **Edit**, then use the **Icon** section. A card's **Change icon** action opens the same chooser. Select **Original**, **Catalog**, or **My Icons**; compare the 60 px preview on light and dark surfaces; then choose plain or framed, color or monochrome, and a size from 80% to 120%. Save the subscription to persist the choice. A quick Change action updates only that subscription's icon preference.

![Dial 1.0 local icon chooser with nine neutral symbols and light and dark previews](assets/screenshots/dial-1.0-icon-chooser.png)

This direct browser capture uses a fictional local account on the **unpublished 1.0 candidate**. The nine service names identify neutral, original symbols; they are not third-party brand logos. The [full local-candidate gallery](SHOWCASE.md) also shows the dashboard, grouped cards, category manager and statistics. The [public Demo](https://wallos-demo.mostai.org/) still runs 0.1.1 until 1.0 is approved and deployed.

**Original** deliberately restores the subscription's Wallos Logo. Its optional light/dark variant remains intact. Deleting a catalog or personal selection does not delete the original upload. If an asset is missing after a restore, Dial falls back to the original Logo or the subscription's initial.

For newly fetched or uploaded original Logos, Dial accepts at most 4 MiB of encoded data, 4096 px on either side, and 4 million pixels in total before decoding. An oversized Logo is skipped with a form warning; the other subscription fields can still be saved.

## My Icons

Upload a PNG, JPEG, WebP, or a simple geometric SVG. The upload limit is **2 MiB per file**. Dial checks the actual content, resizes images to at most **1024 × 1024 px**, re-encodes them without the source metadata, and stores at most **512 KiB** per resulting image. SVG is parsed against a closed shape and attribute list, rasterized under a separate process limit, then stored only as PNG. SVG scripts, embedded content, external references, fonts, text, animation, and CSS are rejected. Export a complex mark to PNG if its SVG cannot be accepted.

Each account can keep up to **100 images** and **32 MiB** of normalized image data. Uploading the same image again reuses its existing asset. An image selected by a subscription cannot be deleted until you change that subscription's icon. Images are served only to their account through an authenticated, uncached endpoint.

Raster images whose longest side is under **128 px** are accepted with a visible low-resolution warning in the upload result and My Icons list. Resizing or increasing the display scale does not restore missing detail.

My Icons live in the Wallos **SQLite database**. Original Wallos Logo uploads and any optional personal legacy SVGs remain in the **Logo volume**. Back up **both** the database and Logo volume before an upgrade. An older Dial image can still show the original Wallos Logo; keep the database and Logo backup together if you roll back. See [Updating and rollback](UPGRADING.md).

## Existing personal redraws

A personal `WALLOS_DIAL_LOGO_MAP` can continue to map original upload filenames to SVGs in a privately mounted `images/dial-logos/` directory. Dial applies that map only when a subscription has **no explicit icon choice**. Choosing Original overrides the map for that subscription. Private maps and SVGs are not included in the public source or image; import images into My Icons for a per-account choice that survives a normal SQLite backup.

The [public Demo](https://wallos-demo.mostai.org/) currently runs Dial 0.1.1 with fictional subscriptions. The 1.0 icon chooser is available in this local candidate; after release, the Demo will show its appearance but will not accept uploads or saved selections.
