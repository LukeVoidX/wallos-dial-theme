# Wallos Dial 1.0 Integration and Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate the reviewed Wallos 5.8.3, category-manager, and icon-system milestones into a tested Dial 1.0.0 package, then publish the reviewed release and update the isolated public Demo after Luke's release approval.

**Architecture:** Run the three component plans in order, then use one release gate to build their shared image and exercise security, data, UI, cache, and rollback checks. Promote the local `1.0.0-rc.1` candidate to `1.0.0` only after the combined checks pass; GitHub/registry/Demo writes are a final, separately approved operation.

**Tech Stack:** PHP 8.3, SQLite3, Docker Buildx, Nginx, Caddy, GitHub Actions/GHCR, Python 3, Node.js with Playwright 1.62.1, shell scripts.

**Spec:** `docs/superpowers/specs/2026-10-17-wallos-dial-1.0-design.md`

## Global Constraints

- Execute `2026-10-17-wallos-5.8.3-compatibility.md`, then `2026-10-17-wallos-dial-category-manager.md`, then `2026-10-17-wallos-dial-icon-system.md`, then this plan. Each preceding plan must leave a working local image and passing tests.
- The final image is Dial `1.0.0` on the reviewed, digest-pinned Wallos `5.8.3` base; it remains a source overlay. Do not claim automatic compatibility with untested Wallos releases.
- Never add the seven private SVG assets, private filename map, original uploads, personal database, credentials, or host configuration to source, Git history, image layers, fixtures, screenshots, or release archives. The nine public service icons require per-mark provenance review before release.
- Preserve `subscriptions.logo` and both Wallos persistent volumes. My Icons is account-scoped SQLite data; its GET endpoint `/endpoints/dial/icon.php?id=<int>` is authenticated and network-only under the service worker.
- Demo Mode and Caddy both block mutation; the public Demo uses synthetic data only. The private Wallos instance is outside this release scope.
- Do not push `main`, create a Git tag, publish GHCR/GitHub Release, or change the public Demo until the exact release packet has been reviewed and Luke explicitly confirms those actions.

## Review Focus

The combined candidate must pin these five cross-component failures to tests in the tasks below:

1. A category edited in the manager is stale when a browser returns to a cached subscriptions page: refresh the filters, groups, and add/edit choices on `pageshow` (Task 2).
2. A saved private legacy SVG map from 0.1.1 hides the public catalog or loses the original Logo: keep the paths separate and test old-map plus explicit Original preference (Task 2).
3. A controlled 0.1.1 service worker serves old `subscriptions.js` after image replacement: test both the old-worker upgrade and the same-Wallos-version Dial update (Tasks 1 and 2).
4. A new Demo route exposes a write or a private icon BLOB: probe each new GET and POST through the public proxy, then verify the app's Demo Mode denies the same POST directly (Tasks 1 and 4).
5. A green image build masks a mismatch among `VERSION`, the HTML asset version, GHCR tag, Compose, and docs: one release contract checks all of them before tag publication (Tasks 1 and 4).

---

## Cross-plan file ownership

| Plan | Main files | Test entrypoints |
| --- | --- | --- |
| 5.8.3 compatibility | `Dockerfile`, `WALLOS_VERSION`, `VERSION=1.0.0-rc.1`, Compose candidate tags, `compat/upstream-files.sha256`, `theme/service-worker.php`, `theme/includes/dial_version.php`, notification override | `tests/compatibility/upstream-security.sh`, `security-http.sh`, `hash-gate.sh`, `service-worker.sh`, `upgrade-rollback.sh` |
| Category manager | `theme/categories.php`, category endpoint, Settings category input, subscriptions toolbar/grouping, category JS/CSS | `tests/category-core.php`, `tests/category-http.sh` |
| Icon system | Public catalog, Dial icon tables/endpoints/resolver, subscription add/edit/clone, chooser JS/CSS, shared renderers | `tests/icon-*.php`, `tests/run-icon-http.sh`, `tests/icon-http.py` |
| This plan | Combined CI gate, browser journeys, docs/screenshots, final version/release packet, public Demo switch | `tests/release/ci-contract.sh`, `tests/browser/release-journey.mjs`, `tests/browser/run.sh`, `demo/smoke.sh` |

The category and icon plans both edit `theme/subscriptions.php` and `theme/styles/dial.css`; the later icon task reads the category result and makes a focused patch, never replaces the full file with its earlier copy. Compatibility owns the version key and authenticated-image network bypass in `theme/service-worker.php`. The category plan appends its page script to the static list, and the icon plan appends its chooser script and reviewed catalog assets; neither rewrites the version or bypass logic.

### Task 1: Make the complete candidate gate fail closed

**Files:**
- Create: `scripts/verify-1.0-candidate.sh`, `tests/release/ci-contract.sh`
- Modify: `.github/workflows/verify.yml`, `.github/workflows/publish-image.yml`, `scripts/smoke-image.sh`
- Test: `tests/release/ci-contract.sh`, all three component plans' CLI/HTTP tests

**Interfaces:**
- Consumes: `tests/compatibility/{upstream-security,security-http,hash-gate,service-worker,upgrade-rollback}.sh`, `tests/category-core.php`, `tests/category-http.sh`, `tests/icon-{catalog,schema,resolver,normalize,atomic}.php`, `tests/run-icon-http.sh`.
- Produces: `scripts/verify-1.0-candidate.sh <local-image-tag>`, a single nonpublishing local/CI gate used again by the tag workflow.

- [ ] **Step 1: Write a failing CI contract.** Make `tests/release/ci-contract.sh` assert that verification runs the full gate and that a prerelease Git tag cannot reach the registry login step:

```bash
#!/usr/bin/env bash
set -euo pipefail
grep -Fq './scripts/verify-1.0-candidate.sh wallos-dial:ci' .github/workflows/verify.yml
grep -Fq './scripts/verify-1.0-candidate.sh wallos-dial:preflight' .github/workflows/publish-image.yml
grep -Fq '^[0-9]+\.[0-9]+\.[0-9]+$' .github/workflows/publish-image.yml
```

- [ ] **Step 2: Run the contract red.** Run `bash tests/release/ci-contract.sh`; expect failure because the current workflows have only the older syntax/mail/smoke checks and accept any `v*` matching `VERSION`.
- [ ] **Step 3: Implement the gate and workflow calls.** `scripts/verify-1.0-candidate.sh` checks `VERSION`/`WALLOS_VERSION`, Compose, all PHP/JS syntax, package privacy, local build, runtime smoke, mail tests, category/icon tests, and every compatibility test. Its final shell form is:

```bash
#!/usr/bin/env bash
set -euo pipefail
image=${1:?pass a local image tag}
./scripts/check-package.sh
docker compose config -q
docker compose -f compose.build.yaml config -q
docker build -t "$image" .
./scripts/smoke-image.sh "$image"
for case in tests/mail-theme.php tests/mail-mime.php tests/category-core.php \
  tests/icon-catalog.php tests/icon-schema.php tests/icon-resolver.php \
  tests/icon-normalize.php tests/icon-atomic.php; do
  docker run --rm --user 82:82 --entrypoint php -v "$PWD:/app:ro" -w /app "$image" \
    -d zend.assertions=1 "$case"
done
tests/compatibility/upstream-security.sh "$image"
tests/compatibility/security-http.sh "$image"
tests/compatibility/service-worker.sh "$image"
tests/compatibility/upgrade-rollback.sh "$image"
tests/compatibility/hash-gate.sh
tests/category-http.sh "$image"
tests/run-icon-http.sh "$image"
```

  Add the following syntax and file checks immediately after `./scripts/check-package.sh`; keep the PHP mount read-only and use the host's Node.js for JS syntax. The required-file list belongs in `scripts/check-package.sh`, not a second list in this gate:

```bash
find theme -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do
  docker run --rm --entrypoint php -v "$PWD:/app:ro" -w /app "$image" -l "/app/$file" >/dev/null
done
find theme -type f -name '*.js' -print0 | while IFS= read -r -d '' file; do
  node --check "$file"
done
```

  In `publish-image.yml`, compare the tag to `v$(cat VERSION)` **and** reject non-stable version strings before registry login. Use this exact shell check inside the tag-validation step:

```bash
version=$(cat VERSION)
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
test "$GITHUB_REF_NAME" = "v$version"
```

  Invoke the gate before the Buildx push and from `verify.yml` on each push/PR. The tag workflow runs its nonpublishing gate before the registry login step.
- [ ] **Step 4: Run green and review scope.** Run `bash tests/release/ci-contract.sh` and `./scripts/verify-1.0-candidate.sh wallos-dial:ci`; check each component test's output, not only the script exit code. Confirm the script does not call `docker push`, `git push`, `gh release`, or a remote Demo host.
- [ ] **Step 5: Commit.** Run `git add scripts/verify-1.0-candidate.sh tests/release/ci-contract.sh .github/workflows/verify.yml .github/workflows/publish-image.yml scripts/smoke-image.sh` and commit `test: gate the full Dial 1.0 candidate`.

### Task 2: Exercise the integrated interface in a real browser

**Files:**
- Create: `tests/browser/package.json`, `tests/browser/package-lock.json`, `tests/browser/release-journey.mjs`, `tests/browser/run.sh`
- Modify: `scripts/verify-1.0-candidate.sh`, `tests/release/ci-contract.sh`, `theme/scripts/subscriptions.js` only if the test exposes a stale `pageshow` state after the category plan
- Test: `tests/browser/run.sh`, actual Chromium screenshots in a disposable local runtime

**Interfaces:**
- Consumes: `tests/run-icon-http.sh <image> --serve --fixture-out <absolute-json-path>`; its JSON provides `base_url` and `accounts[]` with `role`, `username`, `password`. Consumes the icon and category `data-testid` contracts in their plans.
- Produces: `tests/browser/run.sh <image>`, returning nonzero on a broken integrated journey and saving fictional-data screenshots in a temporary output directory.

- [ ] **Step 1: Write the browser assertions.** Create `tests/browser/package.json` as below, generate its lockfile with `npm install --package-lock-only --prefix tests/browser`, and make `release-journey.mjs` read the fixture path from `process.argv[2]`, log in with the account whose role is `owner`, and assert the category/icon controls and reload behavior.

```json
{"private":true,"type":"module","dependencies":{"playwright":"1.62.1"}}
```

  Core assertions:

```js
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const owner = fixture.accounts.find(a => a.role === 'owner');
assert(owner && fixture.base_url.startsWith('http://127.0.0.1:'));
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
try {
  await page.goto(`${fixture.base_url}/login.php`);
  await page.locator('#username').fill(owner.username);
  await page.locator('#password').fill(owner.password);
  await page.locator('input[type="submit"]').click();
  await page.goto(`${fixture.base_url}/subscriptions.php`);
  assert(await page.getByTestId('dial-category-manage-link').first().isVisible());
  await page.getByTestId('dial-category-manage-link').first().click();
  assert(await page.getByTestId('dial-category-manager').isVisible());
  await page.getByTestId('dial-category-new-name').fill('AI & Tools');
  await page.getByTestId('dial-category-add').click();
  assert(await page.getByTestId('dial-category-manager').getByText('AI & Tools').isVisible());
  await page.goBack();
  assert.equal(await page.locator('#category option', { hasText: 'AI & Tools' }).count(), 1);
  await page.locator('button[onclick="addSubscription()"]').first().click();
  await page.locator('#name').fill('Synthetic QA subscription');
  await page.locator('#price').fill('12.50');
  const due = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
  await page.locator('#next_payment').fill(due);
  await page.locator('#category').selectOption({ label: 'AI & Tools' });
  assert(await page.getByTestId('dial-icon-chooser').isVisible());
  await page.getByTestId('dial-icon-source-catalog').click();
  await page.getByTestId('dial-icon-catalog-chatgpt').click();
  assert(await page.getByTestId('dial-icon-preview').isVisible());
  await page.locator('#save-button').click();
  await page.getByText('Synthetic QA subscription').first().waitFor({ state: 'visible' });
  await page.reload();
  assert(await page.getByText('Synthetic QA subscription').first().isVisible());
  for (const width of [900, 1440, 1920]) {
    await page.setViewportSize({ width, height: 900 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    assert.equal(overflow, false, `horizontal overflow at ${width}px`);
  }
```

  Continue inside the same `try` block using the icon plan's stable card and chooser selectors. The add-form Save above persists the catalog choice. A quick Change action then persists styling, and a second quick Change returns to Original:

```js
const cardFor = () => page.getByTestId(/^dial-subscription-\d+$/)
  .filter({ hasText: 'Synthetic QA subscription' }).first();
const cardId = (await cardFor().getAttribute('data-testid')).split('-').at(-1);
assert.match(cardId, /^\d+$/);
assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
assert.equal(await cardFor().locator('img[src*="/dial-catalog/chatgpt.svg"]').count(), 1);
await page.locator('#view-list-button').click();
await page.waitForFunction(() => !document.querySelector('#subscriptions').classList.contains('grid-view'));
assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
await page.locator('#view-grid-button').click();
await page.waitForFunction(() => document.querySelector('#subscriptions').classList.contains('grid-view'));
assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
await page.getByTestId(`dial-icon-change-${cardId}`).click();
await page.getByTestId('dial-icon-presentation-framed').click();
await page.getByTestId('dial-icon-treatment-mono').click();
await page.getByTestId('dial-icon-scale').evaluate(input => {
  input.value = '120';
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
});
assert.equal(await page.getByTestId('dial-icon-presentation-framed').getAttribute('aria-pressed'), 'true');
await page.getByTestId('dial-icon-save').click();
await page.reload();
assert.equal(await cardFor().locator('[data-dial-icon-presentation="framed"][data-dial-icon-treatment="mono"][data-dial-icon-scale="120"]').count(), 1);
await page.getByTestId(`dial-icon-change-${cardId}`).click();
await page.getByTestId('dial-icon-source-original').click();
await page.getByTestId('dial-icon-save').click();
await page.reload();
assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 0);
```

  The chooser plan must use a button with `aria-pressed` on the presentation choice; other radio controls may use `aria-checked`. The category plan's `pageshow` test above checks Back-cache refresh; run the same path in a persistent Chromium context so a back/forward cache can actually be used. Add this keyboard and locale pass after the Original assertion:

```js
const changeButton = page.getByTestId(`dial-icon-change-${cardId}`);
await changeButton.focus();
await page.keyboard.press('Enter');
assert(await page.getByTestId('dial-icon-chooser').isVisible());
await page.keyboard.press('Escape');
assert.equal(await changeButton.evaluate(node => node === document.activeElement), true);
for (const language of ['en', 'zh_cn', 'zh_tw']) {
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#dial-language').selectOption(language)
  ]);
  assert.equal(await page.locator('#dial-language').inputValue(), language);
  assert(await page.getByText('Synthetic QA subscription').first().isVisible());
}
const output = process.argv[3];
fs.mkdirSync(output, { recursive: true });
for (const width of [900, 1440, 1920]) {
  await page.setViewportSize({ width, height: 900 });
  await page.screenshot({ path: `${output}/subscriptions-zh_tw-${width}-100.png`, fullPage: true });
}
for (const [name, route] of [
  ['overview', '/'], ['categories', '/categories.php'],
  ['calendar', '/calendar.php'], ['stats', '/stats.php']
]) {
  await page.goto(`${fixture.base_url}${route}`);
  await page.screenshot({ path: `${output}/${name}-zh_tw-1440-100.png`, fullPage: true });
}
fs.writeFileSync(`${output}/qa-manifest.json`, JSON.stringify({
  image: process.env.DIAL_QA_IMAGE, language: 'zh_tw', widths: [900, 1440, 1920],
  zoom: 100, data: 'isolated synthetic fixture'
}, null, 2));
} finally { await browser.close(); }
```

  The code checks both list and grid. Check dashboard, detail popup, and any statistics service-icon slot through their own rendered selectors and the icon plan's HTTP resolver test; capture each real view and inspect it in Step 4. Add English/Simplified-Chinese captures and the manually checked 80%/125% zoom observations to `qa-manifest.json`; save fictional data only.
- [ ] **Step 2: Create the fixture runner, then run the browser assertions.** `tests/browser/run.sh` starts `tests/run-icon-http.sh "$image" --serve --fixture-out "$fixture"` in the background, waits for the mode-0600 JSON, installs the locked Playwright browser, invokes `node tests/browser/release-journey.mjs "$fixture"`, and terminates the fixture process in a trap. Create it with this shell sequence:

```bash
#!/usr/bin/env bash
set -euo pipefail
image=${1:-wallos-dial:compat}
scratch=$(mktemp -d)
fixture="$scratch/fixture.json"
output=${DIAL_QA_OUTPUT:-"$PWD/data/qa-1.0"}
mkdir -p "$output"
tests/run-icon-http.sh "$image" --serve --fixture-out "$fixture" &
fixture_pid=$!
cleanup() { kill "$fixture_pid" 2>/dev/null || true; wait "$fixture_pid" 2>/dev/null || true; rm -rf "$scratch"; }
trap cleanup EXIT
for attempt in $(seq 1 50); do test -s "$fixture" && break; sleep 0.2; done
test -s "$fixture"
npm ci --prefix tests/browser
if test "$(uname -s)" = Linux; then
  tests/browser/node_modules/.bin/playwright install --with-deps chromium
else
  tests/browser/node_modules/.bin/playwright install chromium
fi
DIAL_QA_IMAGE="$image" node tests/browser/release-journey.mjs "$fixture" "$output"
printf 'Synthetic browser evidence: %s\n' "$output"
```

  Run `bash tests/browser/run.sh wallos-dial:compat` before any integration fixes. A missing contract or stale page fails with a named assertion; if all checks already pass, record that no product code change is needed.
- [ ] **Step 3: Fix only evidenced defects and wire browser CI.** If `pageshow` restores stale category options, make `theme/scripts/subscriptions.js` reload the category-dependent page on `event.persisted` or re-fetch its complete data, then verify form state is preserved or intentionally reset with visible feedback. Append this exact line after the icon HTTP suite in `scripts/verify-1.0-candidate.sh` so both source CI and the prepublish tag workflow exercise the real browser:

```bash
bash tests/browser/run.sh "$image"
```

  Add `grep -Fq 'bash tests/browser/run.sh "$image"' scripts/verify-1.0-candidate.sh` to `tests/release/ci-contract.sh`. The integration gate now requires Node.js, the locked Playwright package and Chromium; keep the Linux browser dependency install in the runner above. Confirm the CI job has enough time for the Docker lifecycle plus browser suite and adjust its `timeout-minutes` to 45 if the measured run exceeds the default.
- [ ] **Step 4: Run green and inspect screenshots.** Rebuild the current local candidate, run `bash tests/browser/run.sh wallos-dial:compat`, then inspect the English and Chinese dashboard, list/grid, chooser, and category manager captures saved under ignored `data/qa-1.0/` at 900/1440/1920 px. Check 80%, 100%, and 125% browser zoom manually in the same isolated runtime; record any layout limitation before docs change. Task 3 copies only reviewed, fictional-data captures into the public docs.
- [ ] **Step 5: Commit.** Add only the browser test/runner/lockfile, `scripts/verify-1.0-candidate.sh`, `tests/release/ci-contract.sh`, and a focused `subscriptions.js` correction if the test found one; commit `test: verify integrated Dial 1.0 browser flows`.

### Task 3: Update the installation story and release evidence

**Files:**
- Modify: `README.md`, `README.zh-CN.md`, `CHANGELOG.md`, `NOTICE.md`, `docs/UPGRADING.md`, `docs/DEMO.md`, `docs/SHOWCASE.md`, `docs/index.html`, `docs/zh-CN/index.html`, `docs/requirements/REQ-001-public-theme.md`, `demo/README.md`, `demo/seed.py`, `demo/reset.sh`, `demo/Caddyfile.site.example`, `demo/smoke.sh`
- Create: `docs/RELEASE-NOTES-1.0.0.md`, `docs/RELEASE-CHECKLIST-1.0.0.md`, `tests/release/docs-contract.py`, new real-app screenshots under `docs/assets/screenshots/`
- Test: `python3 tests/release/docs-contract.py`, `demo/smoke.sh <local-demo-url>`, local relative-link audit

**Interfaces:**
- Consumes: the passed candidate image, browser captures from Task 2, version/asset manifests from all component plans.
- Produces: a source-visible English/Chinese install path, exact 0.1.1→1.0.0 migration/rollback instructions, and a release packet with measured evidence rather than unchecked claims.

- [ ] **Step 1: Write a failing documentation contract.** `tests/release/docs-contract.py` checks active documentation for the exact 1.0.0/5.8.3 version pair, the icon/category instructions, the Demo read-only boundary, and every relative Markdown link target:

```python
from pathlib import Path
import re
root = Path(__file__).resolve().parents[2]
required = {
    'README.md': ('Wallos 5.8.3', 'Manage categories', 'My Icons'),
    'README.zh-CN.md': ('5.8.3', '分类', '图标'),
    'docs/UPGRADING.md': ('0.1.1', '1.0.0', 'rollback'),
    'docs/DEMO.md': ('read-only', 'categories.php'),
    'demo/README.md': ('1.0.0', 'synthetic'),
}
for name, phrases in required.items():
    content = (root / name).read_text()
    for phrase in phrases:
        assert phrase in content, f'{name}: missing {phrase}'
    for target in re.findall(r'\]\(([^)#]+)(?:#[^)]+)?\)', content):
        if not re.match(r'^[a-z]+://', target):
            assert (root / name).parent.joinpath(target).exists(), (name, target)
```

- [ ] **Step 2: Run red.** Run `python3 tests/release/docs-contract.py`; expect the current 0.1.1 docs and missing category/icon instructions to fail.
- [ ] **Step 3: Write the real documentation and Demo config.** Update the named files with the exact pinned base, pull-only Compose, fresh install, current DB + Logo volume backup, icon library behavior, local private SVG map migration path, category manager, 0.1.1→1.0.0 rollback behavior, and limits of future upstream compatibility. The upgrade guide must give the two volume paths, a preflight SQLite `PRAGMA integrity_check`, backup commands, a single-container cutover, post-upgrade readback, and the old-image rollback sequence against a disposable copy. Mark historical REQ-001 explicitly as the 0.1.0 scope and link it to the approved 1.0 design; retain its historical no-brand-SVG statement, while the active README, NOTICE, and icon catalog documents describe the reviewed nine-icon 1.0 package. Make `demo/seed.py` and `demo/reset.sh` descriptions version-neutral and keep all data fictional. In `demo/Caddyfile.site.example`, permit GET/HEAD `/categories.php` and `/service-worker.php`, and keep POST allow-listed only for the per-visitor language endpoint; all category/icon/subscription mutation routes remain blocked. Extend `demo/smoke.sh` to assert new read pages return 200, custom image GET without an account does not disclose bytes, and each new mutation route is denied. Use Task 2's exact application captures for the icon chooser and manager, and refresh the repository showcase without using private SVGs.

  Keep history explicit: the existing 0.1.1 email comparison remains labeled as a 0.1.1 fixture; new screenshots say which 1.0 candidate image and fictional seed produced them. In both READMEs give separate links to the icon guide, category guide, installation, upgrade, and rollback. On both product pages update the visible version, tested Wallos version, meta description, JSON-LD version, and compatibility FAQ together. On `demo/README.md` replace its 0.1.1 sample image tag with 1.0.0 and describe the manager as view-only. The Demo edge check can be implemented as:

```bash
edge=${2:-}
if test -n "$edge"; then
  curl -fsS "${edge%/}/categories.php" | grep -Fq 'dial-category-readonly'
  curl -fsS "${edge%/}/service-worker.php" | grep -Fq 'static-cache-5.8.3-dial-1.0.0'
  for route in /endpoints/categories/category.php /endpoints/categories/dial_manage.php /endpoints/dial/icons.php /endpoints/subscription/add.php; do
    status=$(curl -sS -o /dev/null -w '%{http_code}' -X POST "${edge%/}$route")
    test "$status" = 404
  done
fi
```
- [ ] **Step 4: Run green and inspect claims.** Run `python3 tests/release/docs-contract.py`, `bash tests/release/ci-contract.sh`, `./scripts/check-package.sh`, and `demo/smoke.sh` against an isolated Demo Mode instance. Read every active version/compatibility claim once more against `VERSION`, `WALLOS_VERSION`, and the built image label. Verify screenshot pixels show fictional records and a real candidate runtime.
- [ ] **Step 5: Commit.** Add only the listed docs/Demo/test/capture paths and commit `docs: prepare Wallos Dial 1.0 release`.

### Task 4: Promote the reviewed candidate and publish only after approval

**Files:**
- Modify: `VERSION`, `Dockerfile`, `compose.yaml`, `compose.build.yaml`, `demo/compose.yaml`, `theme/includes/dial_version.php`, `docs/RELEASE-CHECKLIST-1.0.0.md`, bilingual README/release notes and product pages only for final version alignment
- Test: full candidate gate, dual-architecture image build/smoke, public GitHub/GHCR/Demo readback after approved publication

**Interfaces:**
- Consumes: all previous plans' commits, the test output and screenshot evidence from Tasks 1–3, and Luke's explicit approval of the exact release packet.
- Produces: Git tag `v1.0.0`, GHCR `ghcr.io/lukevoidx/wallos-dial:1.0.0` for `linux/amd64` and `linux/arm64`, GitHub Release, and the same image on the isolated public Demo.

- [ ] **Step 1: Write a failing version contract.** Add `tests/release/version-contract.sh` to compare root `VERSION`, Docker image label, `DIAL_VERSION`, both Compose files, the `docs/RELEASE-NOTES-1.0.0.md` heading, and the GHCR tag. Require a stable `1.0.0` at promotion:

```bash
#!/usr/bin/env bash
set -euo pipefail
test "$(cat VERSION)" = 1.0.0
test "$(cat WALLOS_VERSION)" = 5.8.3
grep -Fq 'org.opencontainers.image.version="1.0.0"' Dockerfile
grep -Fq "DIAL_VERSION = '1.0.0'" theme/includes/dial_version.php
grep -Fq 'ghcr.io/lukevoidx/wallos-dial:1.0.0' compose.yaml
grep -Fq 'wallos-dial:1.0.0' compose.build.yaml
grep -Fq 'ghcr.io/lukevoidx/wallos-dial:1.0.0' demo/compose.yaml
grep -Fq '# Wallos Dial 1.0.0' docs/RELEASE-NOTES-1.0.0.md
if test "$#" -gt 0; then
  actual=$(docker image inspect "$1" --format '{{index .Config.Labels "org.opencontainers.image.version"}}')
  test "$actual" = 1.0.0
fi
```

- [ ] **Step 2: Run red.** Run `bash tests/release/version-contract.sh`; expect failure while the local milestone is `1.0.0-rc.1`.
- [ ] **Step 3: Promote locally, test, and create the exact review packet.** Update `VERSION`, the Docker label, both normal Compose tags, `demo/compose.yaml`'s default tag, `DIAL_VERSION`, and active docs to `1.0.0`. Then run `bash tests/release/version-contract.sh`, `./scripts/verify-1.0-candidate.sh wallos-dial:preflight`, `bash tests/release/version-contract.sh wallos-dial:preflight`, and `bash tests/browser/run.sh wallos-dial:preflight`. Use Buildx/QEMU to build and smoke each architecture locally or in a nonpublishing CI job:

```bash
docker buildx build --platform linux/amd64 --load -t wallos-dial:preflight-amd64 .
./scripts/smoke-image.sh wallos-dial:preflight-amd64
docker buildx build --platform linux/arm64 --load -t wallos-dial:preflight-arm64 .
./scripts/smoke-image.sh wallos-dial:preflight-arm64
```

  In a Docker `--internal` test network with synthetic recipients and a Mailpit capture, exercise renewal and cancellation cron, verification and password-reset cron, and the authenticated SMTP-test endpoint. Inspect all five captured MIME messages for HTML, a plain-text part, the expected locale, and the configured fictional Server URL; no external SMTP destination is used. Set only local, explicitly allow-listed capture URLs for the non-email notification channels and verify their expected request receipts or document each channel that cannot be exercised. A failure or missing five-path mail evidence blocks 1.0 publication. Finish `docs/RELEASE-CHECKLIST-1.0.0.md` with exact commit SHA, image source digest, two architecture results, database/Logo backup and restore results, the five mail captures and non-email receipts, browser/screenshots, private-asset scan, release text, Demo rollback image, and every unresolved limitation. Fetch `origin/main` and verify that it is an ancestor of the local candidate; resolve any new remote commits before requesting publication. Read the public Demo container's **current** image digest, mount paths, Compose location, reset job, and Caddy site read-only; compare them with the demo-only marker and repository snippet. Record the exact image/DB restore commands for that observed installation in the release packet before seeking approval. Commit the local candidate as `release: prepare Wallos Dial 1.0.0`; do not push or tag yet.
- [ ] **Step 4: Ask Luke to approve the concrete public release.** Present the commit SHA, full release notes, proposed GHCR tag and locally verified architecture IDs, pinned upstream digest, package privacy check, observed Demo image/mounts/host and exact rollback commands, plus confirmation that the private Wallos container/data is outside the action. Wait for explicit approval of GitHub/GHCR publication and the public Demo switch. This is a required gate before the next step; the future GHCR digest is verified only after publication.
- [ ] **Step 5: Publish and read back after approval.** Push the reviewed `main` commit; create and push `v1.0.0`; wait for GHCR's amd64/arm64 manifest and CI. Publish GitHub Release from the reviewed notes, attach its PR artifact only if a PR was actually created, and verify anonymous pull. Read the current public Demo's image, mounts, backup and reverse proxy state before touching it; backup its synthetic DB/logo volumes, switch only `wallos-dial-public-demo` to the 1.0.0 image, verify health and `demo/smoke.sh` through HTTPS, then inspect overview/subscriptions/categories and blocked writes anonymously. Roll back the public Demo image and its matching synthetic DB backup if the health, data, or read-only checks fail. Keep the private Wallos instance unchanged. Record the public GitHub Release URL, GHCR digest, Demo response evidence, and post-release date in the checklist.

```bash
git push origin main
git tag -a v1.0.0 -m 'Wallos Dial 1.0.0'
git push origin v1.0.0
sha=$(git rev-parse 'v1.0.0^{}')
for attempt in $(seq 1 24); do
  run_id=$(gh run list --workflow publish-image.yml --limit 30 --json databaseId,headSha,event \
    --jq ".[] | select(.headSha==\"$sha\" and .event==\"push\") | .databaseId" | head -n 1)
  test -n "$run_id" && break
  sleep 5
done
test -n "$run_id"
gh run watch "$run_id" --exit-status
docker buildx imagetools inspect ghcr.io/lukevoidx/wallos-dial:1.0.0
gh release create v1.0.0 --title 'Wallos Dial 1.0.0' --notes-file docs/RELEASE-NOTES-1.0.0.md
```

- [ ] **Step 6: Commit the verification record.** Update the release checklist with exact public readback and rollback evidence, commit `docs: record Wallos Dial 1.0.0 publication`, and push that documentation commit only if the release approval covered this post-release record. Release announcements on X, NodeSeek, V2EX, or video are separate later decisions.
