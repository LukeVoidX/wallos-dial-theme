# Wallos Dial Icon System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a Wallos Dial user select a reviewed vector mark, upload a reusable icon, adjust its card treatment, or restore the original Logo from the browser, with the same choice shown throughout the application.

**Architecture:** Keep `subscriptions.logo` and its uploaded file untouched. A Dial migration adds per-account image assets and per-subscription presentation preferences; one resolver reads those preferences and supplies safe display data to every view. The public catalog has its own path, separate from the legacy private SVG mount; uploads become normalized raster BLOBs served through an authenticated endpoint.

**Tech Stack:** Wallos 5.8.3 PHP 8, SQLite3, GD, Alpine `rsvg-convert`, vanilla JavaScript, CSS, Docker, Python standard-library HTTP tests.

**Spec:** `docs/superpowers/specs/2026-10-17-wallos-dial-1.0-design.md`

## Global Constraints

- Target Dial `1.0.0` on the reviewed, digest-pinned Wallos `5.8.3` image. This is a Docker source overlay, not a native Wallos theme plug-in.
- Run the **compatibility plan first**, then the **category-manager plan**, then this plan. The compatibility plan owns `Dockerfile` base rebase, `theme/service-worker.php`, `theme/includes/dial_version.php`, and the implementation milestone `DIAL_VERSION='1.0.0-rc.1'`; integration later promotes the reviewed candidate to `1.0.0`. Coordinate the `rsvg-convert` package addition and exact endpoint cache rule with that owner. Do not introduce a second version constant or cache mechanism.
- The compatibility plan provides the local `wallos-dial:compat` image alias. Rebuild that alias after each task changing an image overlay before an HTTP or in-image test; never publish it.
- Preserve the Wallos 5.8.3 `endpoints/subscription/add.php` category-owner, payment-owner, payer/household-owner and SSO-related protections when overlaying it. Review the complete upstream diff before adding icon logic. The category plan precedes this plan and its changes to shared `subscriptions.php`/CSS must survive.
- Public candidates are exactly `apple-one`, `chatgpt`, `claude`, `jianying`, `netcup`, `nmcloud`, `proxy-seller`, `racknerd`, and `vmiss`. Exclude seven private SVGs, their generator definitions, upload filename map, private data, and host configuration from Git, Docker context, image layers, fixtures, documentation assets, and release archive.
- `apple-one` is an Apple silhouette. The local `apple-one`, `claude`, and `netcup` SVG bytes were checked against official Simple Icons tag `16.35.0` and matched exactly; the other six are locally drawn. Recheck those exact bytes during implementation and review each mark individually; a package-level CC0 label does not clear an individual brand mark. Do not silently omit or publish an unreviewed candidate.
- Keep the existing optional `images/dial-logos/` mount and `dial_logo_map.local.php` fallback for private installs. Put reviewed built-ins under `images/dial-catalog/` so that old mounts cannot hide them. An explicit Original preference overrides the legacy map.
- My Icons belongs to one account and is limited to 100 images and 32 MiB of normalized bytes. Requests are at most 2 MiB; normalized images are at most 1024 px on either side and 512 KiB. Accept PNG, JPEG, WebP, and safe SVG; never store or serve submitted SVG raw.
- Server-side SVG rasterization runs as a non-root user with no external references, a five-second timeout, a 128 MiB memory limit, and a 1024 px output bound. The exact `rsvg-convert` package version is recorded in the built image. Built-in static SVGs are separate from this upload path.
- Built-in and user icons have plain/framed, color/monochrome, and 80–120% size settings. Browser controls have English, Simplified Chinese, and Traditional Chinese text plus keyboard/focus states. No arbitrary CSS or remote URL is accepted as an icon preference.
- Add/edit writes the Wallos subscription and selected icon preference in one SQLite transaction, after staging any original Wallos Logo file. A failed write leaves neither a partial subscription nor a missing previous Logo. My Icons upload is an independent, explicitly confirmed library action and may remain after a form is canceled.
- The service worker handles `/endpoints/dial/icon.php?id=<int>` network-only. It never caches authenticated image BLOBs. Public Demo mutations are rejected by the application even if the proxy is misconfigured.
- This plan prepares local source and tests only. GitHub push, tag, GHCR, public Demo switch, private Wallos deployment, and community posts remain separate release decisions.

## Review Focus

The following five conditions must receive an explicit test in the owning task, in addition to ordinary successful cases:

1. A syntactically valid SVG with a `DOCTYPE`, external reference, `foreignObject`, excessive path data, or slow renderer is rejected without creating an asset or changing the selected icon (Task 4).
2. An authenticated account requests or selects another account's image ID and receives no image bytes and no preference write (Tasks 4 and 5).
3. A saved catalog key disappears, or a saved library image is absent: all views render the original Logo/fallback without a broken image or an attacker-supplied path (Task 3).
4. Re-uploading identical pixels at 100 images or near 32 MiB reuses the existing owned BLOB; a different image is rejected with quota details and without a partial row (Task 4).
5. Dial 0.1.1 deletes a subscription or account after a 1.0 migration, and the installed SQLite triggers remove Dial-owned rows; re-upgrade does not resurrect deleted preferences (Task 2 and integration handoff).

---

## File map and handoff contracts

| File | Single responsibility |
| --- | --- |
| `theme/images/dial-catalog/{nine names}.svg`, `catalog.json`, `SOURCES.md` | Reviewed static catalog, optical calibration, per-mark provenance. |
| `theme/migrations/dial_001_icon_library.php` | Idempotent schema plus delete triggers; sorts after numbered Wallos migrations. |
| `theme/includes/dial_icon_catalog.php` | Read an allow-listed catalog manifest; never construct a path from arbitrary client text. |
| `theme/includes/dial_icon_repository.php` | Account-scoped asset and preference queries, quota, validation, writes. |
| `theme/includes/dial_icon_resolver.php` | Produce one safe descriptor and HTML from catalog, asset, legacy, or original. |
| `theme/includes/dial_icon_normalize.php`, `theme/bin/dial-render-svg.sh` | Validate, decode, bound, and re-encode raster/SVG uploads. |
| `theme/endpoints/dial/icons.php` | Authenticated JSON list, upload, and delete library operations. |
| `theme/endpoints/dial/icon.php` | Authenticated image GET, owner check, `nosniff`, `no-store`. |
| `theme/endpoints/subscription/add.php`, `clone.php`, `get.php` | 5.8.3-based atomic preference writes and detail data. |
| `theme/includes/list_subscriptions.php`, `dial_dashboard.php`, `theme/index.php`, `theme/endpoints/subscriptions/get.php` | Shared resolver in each server-rendered view. |
| `theme/scripts/subscriptions.js`, `theme/scripts/dial-icon-chooser.js`, `theme/scripts/subscription-details.js`, `theme/subscriptions.php`, `theme/styles/dial.css` | Browser chooser, edit/detail integration, visual treatment. |
| `tests/icon-catalog.php`, `icon-schema.php`, `icon-resolver.php`, `icon-normalize.php`, `icon-atomic.php`, `icon-http.py`, `tests/run-icon-http.sh` | Isolated PHP unit and HTTP integration entrypoints; the shell runner can hold a writable fixture open for Playwright. |

Use these exact public PHP interfaces across tasks:

```php
function dialIconCatalog(): array;
function dialIconCatalogItem(string $key): ?array;
function dialIconValidatePreference(SQLite3 $db, int $userId, array $input): array;
function dialIconSavePreference(SQLite3 $db, int $userId, int $subscriptionId, array $normalized): void;
function dialIconGetPreference(SQLite3 $db, int $userId, int $subscriptionId): ?array;
function dialIconListAssets(SQLite3 $db, int $userId): array;
function dialIconSaveAsset(SQLite3 $db, int $userId, string $label, array $normalized): int;
function dialIconDeleteAsset(SQLite3 $db, int $userId, int $assetId): bool;
function dialIconAsset(SQLite3 $db, int $userId, int $assetId): ?array;
function dialResolveIcon(SQLite3 $db, int $userId, array $subscription): array;
function dialRenderIcon(array $icon, string $className): string;
function dialNormalizeIconUpload(array $file): array;
```

`dialIconValidatePreference` accepts `['source'=>'original|catalog|asset', 'catalog_key'=>?string, 'asset_id'=>?int, 'presentation'=>'plain|framed', 'treatment'=>'color|mono', 'scale'=>int]`; it returns those same canonical keys or throws `InvalidArgumentException`. `dialNormalizeIconUpload` returns `['mime'=>'image/png|image/jpeg|image/webp', 'bytes'=>string, 'sha256'=>64-char hex, 'width'=>int, 'height'=>int]` or throws `InvalidArgumentException`. `dialResolveIcon` returns `['kind'=>'original|catalog|asset|legacy|fallback', 'src'=>string, 'variant_src'=>?string, 'native_text_color'=>?string, 'presentation'=>'plain|framed', 'treatment'=>'color|mono', 'scale'=>int]`; all strings used in HTML are escaped at output, while a JSON endpoint uses `json_encode` hex flags. The resolver accepts the database row, not a submitted URL.

The category-manager plan may change `theme/subscriptions.php`, `theme/styles/dial.css`, and category filtering before this work begins. Re-read those files immediately before editing. The icon plan owns the eventual `theme/endpoints/subscription/add.php` overlay; do not have a second agent independently replace that file. The compatibility plan's `DIAL_VERSION` and service-worker route rule are the integration contract.

For browser integration, create a path with `fixture=$(mktemp -t wallos-dial-icon-fixture.XXXXXX.json)` and run `tests/run-icon-http.sh wallos-dial:compat --serve --fixture-out "$fixture"`. It starts a disposable writable instance and holds it open until SIGTERM. It writes a mode-0600 JSON file with exactly this shape; the credentials are generated test data and never tracked:

```json
{
  "base_url": "http://127.0.0.1:49152",
  "accounts": [
    {"role": "owner", "username": "dial_owner", "password": "synthetic-random-value"},
    {"role": "other", "username": "dial_other", "password": "synthetic-random-value"}
  ]
}
```

The port and passwords in this schema illustration are examples; the runner supplies their actual values. The integration plan's Playwright tests consume `base_url` and select accounts by `role`. Default runner mode executes `icon-http.py` and cleans its own containers/volumes immediately.

### Task 1: Review and package the public catalog

**Files:**
- Create: `theme/images/dial-catalog/apple-one.svg`, `chatgpt.svg`, `claude.svg`, `jianying.svg`, `netcup.svg`, `nmcloud.svg`, `proxy-seller.svg`, `racknerd.svg`, `vmiss.svg`, `catalog.json`, `SOURCES.md`
- Create: `theme/includes/dial_icon_catalog.php`, `tests/icon-catalog.php`
- Modify: `scripts/check-package.sh`, `README.md`, `README.zh-CN.md`, `docs/requirements/REQ-001-public-theme.md` (historical-scope note and 1.0 spec link only)

**Interfaces:** Produces `dialIconCatalog()` and `dialIconCatalogItem(string $key): ?array`; Tasks 3 and 5 consume them. `catalog.json` uses one object per allowed key with `file`, `label`, `source`, `source_url`, `license_note`, `trademark_note`, `optical_scale` (numeric, 0.8–1.2).

- [ ] **Step 1: Write a failing catalog/package test.** Start `tests/icon-catalog.php` with this executable core, then add assertions for all nine keys, their source fields, only nine SVG files, nonempty SVG viewBox, and absence of the seven excluded private basenames:

```php
<?php
require __DIR__ . '/../theme/includes/dial_icon_catalog.php';
$want = ['apple-one','chatgpt','claude','jianying','netcup','nmcloud','proxy-seller','racknerd','vmiss'];
sort($want);
$got = array_keys(dialIconCatalog());
sort($got);
assert($got === $want);
assert(dialIconCatalogItem('../escape') === null);
foreach ($want as $key) {
    $item = dialIconCatalogItem($key);
    assert($item !== null && $item['optical_scale'] >= .8 && $item['optical_scale'] <= 1.2);
    assert(is_file(__DIR__ . '/../theme/images/dial-catalog/' . $item['file']));
    assert($item['source'] !== '' && $item['license_note'] !== '' && $item['trademark_note'] !== '');
}
```

- [ ] **Step 2: Run the failing test.** Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-catalog.php`; expect missing `dial_icon_catalog.php` or missing catalog. `wallos-dial:compat` is the image built by the compatibility plan, not a published tag.
- [ ] **Step 3: Curate SVGs and manifest.** Copy only the nine named static SVGs from `../wallos-v5.8.1/images/braun-logos/`; never copy `scripts/generate-braun-logos.py` because it also defines private marks. For the three Simple Icons marks, record the exact `16.35.0` source URL (`icons/apple.svg`, `icons/claude.svg`, and `icons/netcup.svg`) and rerun a byte comparison with that tagged source before copying; all three matched in the planning audit, but the implementation must check its own source checkout. Record individual license/brand-guideline review results. For the six locally drawn marks, record the local generator as source and identify any brand resemblance. Start `catalog.json` with this entry shape, then fill the nine reviewed entries with their actual optical scales:

```json
{
  "apple-one": {
    "file": "apple-one.svg",
    "label": "Apple (Apple One)",
    "source": "Simple Icons 16.35.0: Apple",
    "source_url": "https://github.com/simple-icons/simple-icons/blob/16.35.0/icons/apple.svg",
    "license_note": "Review the individual Apple mark; Simple Icons package CC0 is not brand permission.",
    "trademark_note": "Apple is a trademark of Apple Inc.; no affiliation or endorsement.",
    "optical_scale": 0.9
  }
}
```

The example is one entry's exact schema, not the completed manifest. Review all nine against their source and visual output before committing. If one cannot be cleared, stop this task and request Luke's decision rather than changing the nine-key release contract silently.
- [ ] **Step 4: Implement a closed catalog reader and a precise privacy check.** `dialIconCatalog()` loads `catalog.json` once, rejects keys outside the nine-element constant, rejects files outside the same directory, and returns only entries whose named files exist. `dialIconCatalogItem()` returns null for any non-allow-listed key. Change `scripts/check-package.sh` so `nmcloud` and `proxy-seller` are no longer blanket-prohibited; require the nine exact SVG basenames, forbid the seven private SVG basenames and their name tokens inside `theme/`, forbid `dial_logo_map.local.php`, and keep the existing database/key scan. Do not scan the public Demo hostname as though it were private.

```php
function dialIconCatalogItem(string $key): ?array {
    if (!preg_match('/\A(?:apple-one|chatgpt|claude|jianying|netcup|nmcloud|proxy-seller|racknerd|vmiss)\z/D', $key)) return null;
    return dialIconCatalog()[$key] ?? null;
}
```

- [ ] **Step 5: Run both checks and commit.** Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-catalog.php` and `./scripts/check-package.sh`; expect both pass and `find theme/images/dial-catalog -name '*.svg' | wc -l` to return 9. Commit only these files with `git commit -m "feat: curate public Dial icon catalog"` after reviewing `git diff --check` and the staged file list.

### Task 2: Migrate and retain account-scoped icon state

**Files:**
- Create: `theme/migrations/dial_001_icon_library.php`, `theme/includes/dial_icon_repository.php`, `tests/icon-schema.php`
- Modify: `scripts/check-package.sh` (require the migration and repository), `.github/workflows/verify.yml` (run schema test in built image)

**Interfaces:** Produces `dialIconValidatePreference`, `dialIconSavePreference`, `dialIconGetPreference`, `dialIconListAssets`, `dialIconSaveAsset`, `dialIconDeleteAsset`, `dialIconAsset` with signatures from the file map. The migration runs after Wallos's numbered migrations through its existing `includes/run_migrations.php` glob; no new numbered upstream migration.

- [ ] **Step 1: Write `tests/icon-schema.php` as a failing in-memory migration and ownership test.** Create minimal `user(id)`, `subscriptions(id,user_id)` tables, include the migration twice, insert two users and subscriptions, and assert the following SQL behavior:

```php
$db = new SQLite3(':memory:');
$db->exec('CREATE TABLE user(id INTEGER PRIMARY KEY); CREATE TABLE subscriptions(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL)');
require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';
require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';
$db->exec("INSERT INTO user(id) VALUES(1),(2); INSERT INTO subscriptions(id,user_id) VALUES(11,1),(22,2)");
$db->exec("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,presentation,treatment,scale) VALUES(1,11,'original','plain','color',100)");
$db->exec('DELETE FROM subscriptions WHERE id=11');
assert((int)$db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE subscription_id=11') === 0);
```

Add assertions for `DELETE FROM user WHERE id=1` cleaning both Dial tables, while user 2's rows remain. This models deletion by the old 0.1.1 image, which will not know any Dial cleanup API.
- [ ] **Step 2: Run it red.** Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-schema.php`; expect missing migration.
- [ ] **Step 3: Add the migration.** Create `dial_icon_assets(id,user_id,label,mime,bytes,sha256,width,height,created_at)` and `dial_subscription_icons(user_id,subscription_id,source,catalog_key,asset_id,presentation,treatment,scale,updated_at)` with `CREATE TABLE IF NOT EXISTS`, `UNIQUE(user_id,sha256)` for assets, `PRIMARY KEY(user_id,subscription_id)` for preferences, `CHECK` constraints for source/presentation/treatment/scale and source-specific `catalog_key`/`asset_id`, plus `AFTER DELETE` triggers on `subscriptions` and `user`. The names here are the exact schema contract for repository functions and the rollback test. Use table `user` (singular) as Wallos does. Do not rely on `PRAGMA foreign_keys`; it is not enabled by current Wallos connections. Example critical trigger:

```sql
CREATE TRIGGER IF NOT EXISTS dial_icon_subscription_deleted
AFTER DELETE ON subscriptions BEGIN
  DELETE FROM dial_subscription_icons
  WHERE user_id = OLD.user_id AND subscription_id = OLD.id;
END;
```

The account trigger deletes its preferences and assets, including when old Wallos deletes the `user` row before its `subscriptions` rows. Treat SQL execution failure as migration failure, so container startup does not claim a healthy migrated state.
- [ ] **Step 4: Add owner-scoped repository functions and test them.** Bind all user/subscription/asset IDs with `SQLITE3_INTEGER`. `dialIconValidatePreference` verifies enum fields, integer scale 80–120, known catalog key, asset existence for that user, and the source-specific nullable fields. `dialIconSavePreference` first verifies `SELECT 1 FROM subscriptions WHERE id=:s AND user_id=:u`, then upserts only that user's row; `dialIconDeleteAsset` rejects an ID referenced by that user's subscriptions. `dialIconSaveAsset` checks count and `SUM(length(bytes))` before inserting, returns existing ID for a matching `(user_id,sha256)`, and never deduplicates across users. Assert a foreign asset ID raises and no row changes:

```php
try {
    dialIconValidatePreference($db, 1, ['source'=>'asset','asset_id'=>222,'presentation'=>'plain','treatment'=>'color','scale'=>100]);
    assert(false, 'foreign asset must fail');
} catch (InvalidArgumentException $e) {
    assert($e->getMessage() === 'icon_asset_not_owned');
}
```

- [ ] **Step 5: Run schema/repository tests and commit.** Run the red command again; expect pass. Then run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -l theme/includes/dial_icon_repository.php`, `./scripts/check-package.sh`, and `git diff --check`. Commit with `git commit -m "feat: persist account-scoped icon preferences"`.

### Task 3: Resolve one safe icon for every view

**Files:**
- Create: `theme/includes/dial_icon_resolver.php`, `tests/icon-resolver.php`
- Modify: `theme/includes/list_subscriptions.php`, `theme/includes/dial_dashboard.php`, `theme/index.php`, `theme/endpoints/subscriptions/get.php`, `theme/subscriptions.php`
- Later integration in Task 6: `theme/endpoints/subscription/get.php`, `theme/scripts/subscription-details.js`

**Interfaces:** Consumes the catalog and repository functions; produces `dialResolveIcon(SQLite3,int,array):array` and `dialRenderIcon(array,string):string`. `dialResolveIcon` is called only with a subscription row already selected for the current user; it independently checks preference and asset ownership rather than trusting row provenance.

- [ ] **Step 1: Write failing resolver cases.** `tests/icon-resolver.php` creates a fixture DB with two accounts and an original Logo, installs the migration, saves catalog/asset/original preferences, then asserts that the returned `src` is respectively `images/dial-catalog/chatgpt.svg`, `/endpoints/dial/icon.php?id=1`, or the fixture's `images/uploads/logos/original.png` path. It also asserts explicit Original bypasses a temporary private mapping file, while a missing row uses that legacy map, and an unknown key or deleted asset falls back to the original. Escape test:

```php
$html = dialRenderIcon(['kind'=>'original','src'=>'images/uploads/logos/a" onerror="bad.png',
  'variant_src'=>null,'native_text_color'=>null,'presentation'=>'plain','treatment'=>'color','scale'=>100], 'dial-service-icon');
assert(strpos($html, 'onerror="') === false);
assert(strpos($html, '&quot; onerror=&quot;') !== false);
```

Use a test helper to inject a temporary mapping file through `WALLOS_DIAL_LOGO_MAP`; do not add a real private map to the repository.
- [ ] **Step 2: Run red.** Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-resolver.php`; expect undefined resolver.
- [ ] **Step 3: Implement the descriptor and renderer.** `dialResolveIcon` prioritizes saved preference; `source=original` returns original directly, `source=catalog` uses `dialIconCatalogItem`, `source=asset` verifies the owned row; only absent preference checks `dialRedrawnLogoFor` legacy map. A failed reference falls back to original or a deterministic letter fallback. `dialRenderIcon` accepts only `dial-service-icon` or `dial-card-icon` as caller class, uses `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`, emits `alt=""` for a decorative mark and bounded CSS custom property `--dial-optical-scale` calculated from saved scale × catalog optical scale. Its wrapper exposes stable `data-dial-icon-kind`, `data-dial-icon-presentation`, `data-dial-icon-treatment`, and `data-dial-icon-scale` attributes with the canonical enum/integer values for browser assertions. Preserve `logo_variant` and `logo_text_color` when the original is chosen.
- [ ] **Step 4: Replace the scattered render paths.** In `list_subscriptions.php` and `dial_dashboard.php`, replace direct `dialRedrawnLogoFor` calls with the resolver/renderer. In `index.php`, replace the two remaining direct `renderThemedLogoImg` paths for overdue and cancellations. In `subscriptions.php` and `endpoints/subscriptions/get.php`, pass the needed current `$db/$userId` into list rendering and keep existing grouping/filter logic from the category plan. Do not place icon HTML in a SQL field or make any client-provided URL authoritative.
- [ ] **Step 5: Run tests and inspect actual rendering.** Run resolver test and package check; rebuild `wallos-dial:compat` for PHP lint and a disposable account. Inspect list/grid/dashboard/overdue/cancellation cards with Original, catalog, and legacy preferences. The asset descriptor is unit-tested now; visual asset loading is checked after Task 4 adds its endpoint. Verify one `aria`-readable adjacent service name and no broken `<img>` URL in the available sources. Commit with `git commit -m "feat: resolve icons consistently across Wallos views"`.

### Task 4: Normalize and serve My Icons safely

**Files:**
- Create: `theme/includes/dial_icon_normalize.php`, `theme/bin/dial-render-svg.sh`, `theme/endpoints/dial/icons.php`, `theme/endpoints/dial/icon.php`, `tests/icon-normalize.php`, `tests/icon-http.py`, `tests/run-icon-http.sh`
- Modify: `Dockerfile` **after** the compatibility plan's base rebase; `.github/workflows/verify.yml`, `scripts/check-package.sh`
- Compatibility-owned dependency: `theme/service-worker.php` must leave `/endpoints/dial/icon.php` network-only; do not rework its version/cache logic here.

**Interfaces:** Consumes `dialIconSaveAsset`, `dialIconDeleteAsset`, `dialIconListAssets`, `dialIconAsset`; produces `dialNormalizeIconUpload(array $file):array`. `icons.php` uses GET to list, POST with `action=upload|delete` to mutate. `icon.php?id=<positive integer>` uses GET only and returns one owned normalized raster image.

- [ ] **Step 1: Write failing normalization tests.** Generate 1×1 PNG/JPEG/WebP fixtures with GD and a simple SVG containing a circle. Assert their normalized bytes decode with GD and fit the limits. Add malicious SVG fixtures using `<!DOCTYPE>`, `xlink:href="https://example.invalid/x"`, `<foreignObject>`, `<script>`, `onload=`, `<animate>`, a 70 KiB path, and a non-image file labeled `.png`. Each must throw `InvalidArgumentException` without a library insert. Cover a renderer timeout with an injectable command-runner callable used only by the test. Example:

```php
$file = ['tmp_name'=>$badSvgPath,'name'=>'bad.svg','type'=>'image/svg+xml','size'=>filesize($badSvgPath),'error'=>UPLOAD_ERR_OK];
try { dialNormalizeIconUpload($file); assert(false); }
catch (InvalidArgumentException $e) { assert($e->getMessage() === 'unsafe_svg'); }
```

- [ ] **Step 2: Run red.** Run `docker run --rm --user 82:82 --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-normalize.php`; expect missing normalizer. The explicit non-root user matches the default Wallos image; repeat with configured PUID/PGID in the integration test.
- [ ] **Step 3: Add the renderer dependency without altering version ownership.** On the already rebased 5.8.3 Dockerfile, install Alpine `rsvg-convert`, record `apk info -v rsvg-convert` inside the image, and make `theme/bin/dial-render-svg.sh` executable. The wrapper refuses root (`id -u`), applies `ulimit -v 131072`, invokes `timeout -s KILL 5 rsvg-convert` with fixed arguments and a local temporary input, and writes a temporary PNG. The caller passes only validated integer width/height and safe temp paths, never a shell-expanded filename. Run one conversion in both architecture images before treating SVG upload as supported.
- [ ] **Step 4: Implement bounded decode/encode.** Reject PHP upload errors, sizes above 2 MiB, empty content, unknown MIME, raster dimensions over a parser-safe ceiling, and decoded images larger than the memory budget before allocating a destination. Re-encode all rasters through GD so EXIF, comments, and original bytes are discarded. For SVG, parse XML with network/entities disabled; recursively allow only `svg,g,path,circle,ellipse,rect,line,polyline,polygon` plus numeric geometry, `viewBox`, `fill`, `stroke`, opacity and bounded transform attributes; reject any `style`, `href`, `xlink`, `url(`, DTD, animation, script, embedded content, excessive element count or path length. Rasterize through the wrapper, then GD re-encode. Return the exact normalized `mime/bytes/sha256/width/height` structure; reject encoded output over 512 KiB.
- [ ] **Step 5: Add authenticated endpoints and quota tests.** `icons.php` GET responds with owned `id,label,width,height,created_at` only; POST requires `validate_endpoint.php`, `$_SESSION['loggedin']===true`, CSRF, and `!getenv('DEMO_MODE')`. `dialIconSaveAsset` uses `BEGIN IMMEDIATE`, looks for the same user's hash **before** quota checks, computes count and `SUM(length(bytes))`, then inserts or returns the duplicate ID and commits. Upload reports count/bytes on 100-image or 32 MiB rejection; delete fails if selected by any owned subscription. `icon.php` requires a logged-in session, positive integer ID and same-owner asset, returns 404 for a foreign ID, `Content-Type` from the canonical stored MIME, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, and raw raster bytes. Test duplicate hash at quota reusing the same ID and a different hash being rejected.
- [ ] **Step 6: Run HTTP access checks.** Rebuild `wallos-dial:compat` from the current source. `tests/run-icon-http.sh wallos-dial:compat` starts a candidate container with disposable DB/Logo volumes and an ephemeral loopback port, waits for `/health.php`, registers two synthetic accounts, runs `python3 tests/icon-http.py --base-url "$base_url"`, then repeats mutation checks with `DEMO_MODE=1`; a shell trap removes only these test containers and volumes. The runner also implements `--serve --fixture-out "$fixture"` as defined above, keeps that writable container alive and writes the fixture with permissions 0600 for integration Playwright tests. At this stage `icon-http.py` fetches a CSRF token, uploads allowed types, rejects cross-user **image GET** and no-CSRF/unauthenticated library writes, and verifies Demo write rejection. The foreign asset **selection** check waits for Task 5's add/edit overlay. It asserts that no SVG bytes are served and custom images have `no-store`; a separate controlled-browser check verifies the service worker does not cache the path. Run `docker run --rm --user 82:82 --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-normalize.php` and commit with `git commit -m "feat: add private reusable icon library"`.

### Task 5: Make subscription add, edit, and clone atomic

**Files:**
- Create: `theme/endpoints/subscription/add.php`, `theme/endpoints/subscription/clone.php`, `theme/includes/dial_icon_transaction.php`, `tests/icon-atomic.php`
- Modify: `theme/scripts/subscriptions.js` (submit `dial_icon_*` fields), `scripts/check-package.sh` (require endpoint overlays)
- Upstream input: official Wallos **v5.8.3** `endpoints/subscription/add.php` and `clone.php`, already reviewed by the compatibility plan. The 5.8.1 local private file is not an acceptable base.

**Interfaces:** POST fields are `dial_icon_source`, `dial_icon_catalog_key`, `dial_icon_asset_id`, `dial_icon_presentation`, `dial_icon_treatment`, `dial_icon_scale`. The endpoint converts them to the repository preference array. Empty new-form fields mean no preference row; an explicit `original` row suppresses the legacy filename map.

- [ ] **Step 1: Write a failing transactional fault test.** `tests/icon-atomic.php` creates a disposable SQLite database and two temporary Logo files. It submits a valid new subscription+catalog preference, an edit switching to Original, an invalid foreign asset, and a clone. Introduce a test-only callable fault hook at `after_stage`, `after_move`, and `before_commit`; production passes no hook. Assert on each pre-commit failure that `subscriptions` and `dial_subscription_icons` return to the previous values, the old file still exists, and any moved new file is removed. Force a preference INSERT failure with a temporary SQLite `BEFORE INSERT` abort trigger, then assert the same result. After success, assert clone preference copied but image BLOB count unchanged; force old-file cleanup failure separately and assert the committed row still references the new file.
- [ ] **Step 2: Run red.** Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-atomic.php`; expect missing transaction helper/overlay.
- [ ] **Step 3: Base the overlay on 5.8.3 and retain ownership gates.** Copy the **official 5.8.3** `add.php` into `theme/endpoints/subscription/add.php`, review its diff against the pinned image, and preserve its `category_id` owner test, payer/household, payment-method, replacement subscription and existing Logo behavior. Add `require_once` for the icon repository. Validate icon fields, catalog key and asset ownership **before** any persistent change; do not trust the hidden form values.
- [ ] **Step 4: Wrap the database and file write.** Stage the original Wallos Logo and variant under random temp names on the same filesystem and choose new non-colliding final names; begin `BEGIN IMMEDIATE`; run 5.8.3's prepared INSERT/UPDATE with `WHERE id=:id AND user_id=:userId`; use `$db->lastInsertRowID()` for a new row; move staged new files to final names; call `dialIconSavePreference`; commit. On any exception or false SQLite result, roll back and remove only the newly staged/moved files. Delete prior Logo files only after successful commit through existing `logo_cleanup.php`, scheduling retry if that cleanup fails. Keep the current JSON success/error contract used by `theme/scripts/subscriptions.js`.

```php
function dialWriteSubscriptionWithIcon(
    SQLite3 $db, int $userId, callable $writeWallosRow, ?array $preference,
    array $stagedFiles, ?callable $fault = null
): int {
    $db->exec('BEGIN IMMEDIATE');
    try {
        $id = $writeWallosRow($db); // callable(SQLite3): int, with the complete 5.8.3 INSERT/UPDATE
        dialMoveStagedLogoFiles($stagedFiles); // each item: ['temporary'=>path,'final'=>path]
        if ($fault) $fault('after_move');
        if ($preference !== null) dialIconSavePreference($db, $userId, $id, $preference);
        if ($fault) $fault('before_commit');
        if (!$db->exec('COMMIT')) throw new RuntimeException('commit_failed');
        return $id;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        dialRemoveNewLogoFiles($stagedFiles);
        throw $e;
    }
}
```

Define `dialMoveStagedLogoFiles(array $stagedFiles): void` and `dialRemoveNewLogoFiles(array $stagedFiles): void` in `theme/includes/dial_icon_transaction.php`; the fault hook belongs only to `dialWriteSubscriptionWithIcon` and is null in the HTTP endpoint. Call the `after_stage` hook directly after staging, before `BEGIN IMMEDIATE`. The excerpt shows the required ordering; preserve the full upstream insert/update fields, not only the Logo fields.
- [ ] **Step 5: Clone preference in the same transaction.** Starting from the 5.8.3 `clone.php`, verify source `id` belongs to the authenticated user and revalidate its category, currency, payer, payment method, and replacement subscription references with the same ownership rules used by the 5.8.3 add endpoint before copying them. The upstream clone copies `logo` but omits `logo_variant` and `logo_text_color`; include both columns and their values so a cloned Original Logo keeps its existing presentation. Begin a transaction, insert the cloned subscription, read the source preference, copy its scalar selection with `dialIconSavePreference` using the new ID, and commit. The `asset_id` points to the same owned BLOB; no byte duplication. On failure roll back both rows. Keep response `id` so existing `cloneSubscription()` can open the new record. In `tests/icon-atomic.php` assert a clone retains the original Logo, variant, text color, and preference while rejecting a source row with a foreign category or payment method before any insert.
- [ ] **Step 6: Run atomic and HTTP tests, then commit.** Rebuild `wallos-dial:compat` from the current source. Run `docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:compat -d zend.assertions=1 tests/icon-atomic.php`, `tests/run-icon-http.sh wallos-dial:compat` for valid/foreign add/edit/clone, `docker run --rm --entrypoint sh wallos-dial:compat -lc 'php -l /var/www/html/endpoints/subscription/add.php && php -l /var/www/html/endpoints/subscription/clone.php'`, and `git diff` review against the official 5.8.3 files. Assert original Logo after rollback through the old 0.1.1 image. Commit with `git commit -m "feat: save subscription icon choices atomically"`.

### Task 6: Build the accessible chooser and connect every view

**Files:**
- Create: `theme/scripts/dial-icon-chooser.js`, `theme/scripts/subscription-details.js`, `theme/endpoints/subscription/get.php`
- Modify: `theme/subscriptions.php`, `theme/scripts/subscriptions.js`, `theme/includes/list_subscriptions.php`, `theme/includes/dial_dashboard.php`, `theme/index.php`, `theme/styles/dial.css`, `theme/includes/header.php`, `theme/endpoints/subscriptions/get.php`
- Modify: `theme/service-worker.php`, `tests/compatibility/service-worker.sh` (append reviewed public icons and chooser script only)
- Test: `tests/icon-http.py` plus browser captures under `docs/assets/screenshots/` from an isolated account only

**Interfaces:** The chooser uses `window.DialIconChooser.open({subscriptionId, currentIcon, onSelect})`; it submits the six `dial_icon_*` fields from Task 5. `get.php` returns `dial_icon` as the canonical resolver descriptor in addition to Wallos's existing fields. `subscription-details.js` renders that descriptor with DOM methods, not `innerHTML` from a URL.

Stable browser selectors for the integration plan are `data-testid="dial-icon-chooser"`, `dial-icon-source-original|catalog|library|upload`, `dial-icon-catalog-<key>`, `dial-icon-library-<id>`, `dial-icon-presentation-plain|framed`, `dial-icon-treatment-color|mono`, `dial-icon-scale`, `dial-icon-preview`, `dial-icon-upload`, `dial-icon-save`, `dial-icon-change-<subscriptionId>`, and `dial-subscription-<subscriptionId>` on both list and grid cards. The angle-bracket suffixes mean the validated catalog key, owned integer asset ID, or owned integer subscription ID at runtime. `dial-icon-save` commits a quick Change icon action; the add/edit form keeps its existing `#save-button`. The presentation options are buttons with `aria-pressed` so the integration browser test can assert the selected state; other controls may use radio `aria-checked` or button `aria-pressed`. All controls expose translated visible labels; `data-testid` is only a stable locator, not the accessibility contract.

- [ ] **Step 1: Add failing browser/HTTP assertions.** Extend `tests/icon-http.py` to load `/subscriptions.php`, assert the chooser script and translated Icon labels, inspect `endpoints/subscription/get.php?id=<owned-id>` for a `dial_icon` object, and assert a foreign ID returns no icon descriptor. Give the integration plan a Playwright scenario based on the stable selectors above: open add/edit, select `dial-icon-catalog-chatgpt` and an owned `dial-icon-library-<id>`, select Original, change plain/framed, monochrome/color, size 80/100/120, save, reload, and check the selected ARIA state and displayed image in list, grid, dashboard, and detail popup. Record viewport width, zoom and language for each capture.
- [ ] **Step 2: Run red.** Run `tests/run-icon-http.sh wallos-dial:compat`; expect missing chooser and `dial_icon` data.
- [ ] **Step 3: Add one chooser component.** Insert a single Icon section into the already category-updated subscription form: four source choices, catalog grid, My Icons list/upload/delete controls, plain/framed, color/mono, and bounded scale controls. Include a 60 px actual-size preview on warm-white and dark backgrounds and an accessible status/error region. Use buttons/radios with `aria-pressed` or checked state, focus-visible, Escape close and focus return. Cards' Change icon action opens the same chooser for that subscription without triggering the card detail click; its `dial-icon-save` control submits through the existing add/edit transaction endpoint so the preference and subscription stay atomic. In Demo Mode, show a non-editing preview and an explanation that edits need a personal install.
- [ ] **Step 4: Connect JS and localization.** `dial-icon-chooser.js` owns chooser state, a fetch to `endpoints/dial/icons.php`, client-side preview, and submission field values; it treats server responses as authoritative. `subscriptions.js` resets chooser state in `resetForm()`, loads `subscription.dial_icon` on edit, and sends selected fields in the existing `FormData`. A quick Change icon action first loads the owned subscription's complete edit payload and submits that unchanged payload plus the new `dial_icon_*` fields to the Task 5 add/edit endpoint; it never sends a partial subscription form. Add English, `zh_cn`, `zh_tw` strings through the existing Dial language helper/translation conventions, not hardcoded English-only controls. Avoid converting a My Icons SVG in the browser; the server upload endpoint handles it safely. Append `scripts/dial-icon-chooser.js` and the nine reviewed `images/dial-catalog/*.svg` paths to the compatibility service worker's static list, then add those exact paths to `tests/compatibility/service-worker.sh`; retain the combined version key and authenticated-image network bypass.
- [ ] **Step 5: Connect detail/summary renderers.** Overlay the 5.8.3 `endpoints/subscription/get.php` to return a resolved `dial_icon` object for the owned subscription. Overlay `scripts/subscription-details.js` to render `dial_icon.src` and optional original theme variant with `document.createElement`, escaping by DOM assignment; keep its other Wallos functionality. Ensure list and grid server fragments, dashboard upcoming/manual/cancellation rows, and any statistics service-icon slot all call the Task 3 resolver. The same `presentation`, `treatment`, and `scale` classes/CSS properties apply in all slots; `alt=""` is valid only where adjacent subscription name remains visible.
- [ ] **Step 6: Run UI and accessibility review, then commit.** Rebuild `wallos-dial:compat` from current source, run PHP/JS lint, `tests/run-icon-http.sh wallos-dial:compat`, and `bash tests/compatibility/service-worker.sh wallos-dial:compat`; inspect English, Simplified Chinese and Traditional Chinese at desktop widths 900, 1440, 1920 px and browser zoom 80/100/125%. Test keyboard-only add/edit/change/delete, focus return, error announcements, and changing a subscription's icon after page reload. Use actual browser screenshots with fictional data. Commit `theme/service-worker.php`, its test, and the listed UI files with `git commit -m "feat: let users choose and style icons in Wallos"`.

### Task 7: Finish integration gates and document use

**Files:**
- Modify: `.github/workflows/verify.yml`, `.github/workflows/publish-image.yml`, `scripts/check-package.sh`, `scripts/smoke-image.sh`, `README.md`, `README.zh-CN.md`, `docs/TROUBLESHOOTING.md`, `docs/UPGRADING.md`, `docs/requirements/REQ-001-public-theme.md`
- Modify: `tests/compatibility/upgrade-rollback.sh` for icon survival and trigger cleanup across old-image writes
- Create: `docs/ICONS.md`, `docs/zh-CN/ICONS.md`
- Test: `tests/icon-catalog.php`, `icon-schema.php`, `icon-resolver.php`, `icon-normalize.php`, `icon-atomic.php`, `icon-http.py`, `tests/run-icon-http.sh`

**Interfaces:** Integration invokes the five named PHP CLI tests in the image with assertions enabled and `tests/icon-http.py` against disposable writable and Demo instances. The compatibility/release plan handles dual-architecture publication and service-worker version verification; this task supplies icon acceptance evidence to it.

- [ ] **Step 1: Make CI execute the icon suite.** Extend `verify.yml` after `docker build` with this exact pattern, plus the temporary-container HTTP script. Include the new PHP overlays in the existing lint loop:

```bash
for name in icon-catalog icon-schema icon-resolver icon-normalize icon-atomic; do
  docker run --rm --user 82:82 --entrypoint php -v "$PWD:/app:ro" -w /app wallos-dial:ci \
    -d zend.assertions=1 "tests/${name}.php"
done
./scripts/smoke-image.sh wallos-dial:ci
```

- [ ] **Step 2: Verify public/private and persistence boundaries.** Run `./scripts/check-package.sh`; run `git ls-files | rg '(^|/)(data/|dial_logo_map\.local\.php$|[^/]+\.(db|sqlite|pem|key)$|images/dial-logos/[^/]+\.svg$)'` expecting no match; inspect all nine catalog files and docs captures, and build a clean image. In that image, `find /var/www/html/images/dial-catalog -name '*.svg'` must show nine reviewed public files; `images/dial-logos` must contain no maintainer file. `docker save` and inspect layer contents for the seven forbidden SVG names. Extend `tests/compatibility/upgrade-rollback.sh` so the first 1.0 phase inserts one synthetic image asset and one catalog preference for an existing subscription, records their IDs/hash, then the old image sees the unchanged original Logo. During old-image add/edit/delete, give the temporary subscription a Dial preference using a test-only SQLite insert after the add; old-image deletion must fire the installed trigger and remove only that temporary preference. Re-upgrade must retain the original subscription's preference and asset hash, omit the deleted preference, and pass `PRAGMA integrity_check`. The script continues to accept only a candidate image name and its own mktemp volume paths. Run that extended script and `tests/run-icon-http.sh wallos-dial:compat`; keep every input synthetic or disposable.
- [ ] **Step 3: Document the visible workflow.** `docs/ICONS.md` and its Simplified-Chinese counterpart show add/edit, Change icon, My Icons, file limits/quota, Original restoration, empty/missing icon fallback, and backup/rollback behavior with actual fictional-data UI captures. Update README to say the reviewed nine service marks are included, private marks are excluded, and third-party marks are identification only with per-asset source notes. Explain that a locally mounted legacy map still works for private users but takes effect only without an explicit selection. Document that My Icons BLOBs are in SQLite backups while original Logos remain in the Logo volume.
- [ ] **Step 4: Hand off release evidence and commit docs.** Record exact 5.8.3 digest, `rsvg-convert` package version, all test commands/results, and the privacy scan in the 1.0 release checklist owned by the integration/release plan. Confirm service-worker network-only behavior for `/endpoints/dial/icon.php` under an already-controlled 0.1.1 browser and a fresh 1.0 browser. Commit with `git commit -m "docs: explain and verify Dial icon library"`; leave GitHub release and Demo switch to the separate approved publication step.

## Self-review against the design

- Public/private provenance and optical sizing: Task 1.
- Existing original Logo, idempotent migration, triggers, account isolation, and rollback: Tasks 2–3 and 7.
- Safe PNG/JPEG/WebP/SVG upload, image endpoint, 100/32 MiB quota, Demo guard: Task 4.
- Atomic add/edit/clone and Logo cleanup under Wallos 5.8.3 ownership gates: Task 5.
- Keyboard/three-language browser chooser, per-icon style, every display path, old legacy map: Tasks 3 and 6.
- CI, documentation, clean package, dual-architecture handoff: Task 7 plus compatibility/integration plans.

No task in this plan publishes externally. The implementation owner must review the complete 1.0 spec and this plan before starting Task 1, then execute the compatibility and category-manager plans in the agreed order.
