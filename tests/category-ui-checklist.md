# Dial category manager browser QA

Observed 2026-10-17 with Chrome 155 via Playwright, Wallos Dial 1.0.0-rc.1, and a disposable account containing 30 fictional subscriptions. Reproduce with `NODE_PATH=<installed Playwright node_modules> node tests/category-browser.cjs wallos-dial:category`. Synthetic screenshots and the JSON run report are retained in the ignored `.superpowers/sdd/2026-10-17-wallos-dial-category-manager/` QA workspace.

- [x] Keyboard: add `AI & Tools`, rename, activate Save, and verify one-layer display after reload. The core test also repeats the same name save.
- [x] Keyboard: move a row up and down; the order persists after reload.
- [x] Pointer: drag by the handle; the changed order persists after reload.
- [x] Merge: select a distinct target, review the moved count, confirm, and verify the source disappears while all subscriptions remain.
- [x] Merge and delete dialogs: Escape closes each and returns focus to its triggering button.
- [x] Delete: an empty category requires confirmation; a used category offers Merge; protected id 1 has no destructive controls.
- [x] Starter: the preview has unchecked exact-row deletion choices. Escape makes no write; confirming a selected row removes only that row and adds missing starter names.
- [x] Starter dialog: Escape closes it and returns focus to its triggering button.
- [x] Demo: names/counts and read-only explanation render; no mutation controls are exposed; direct API POSTs fail in the HTTP test.
- [x] English, Simplified Chinese, Traditional Chinese: title, Add, Merge, and invalid-name feedback change with language; stored category names remain unchanged.
- [x] Desktop layout at 1280, 1024, and 853 CSS pixels: no horizontal overflow or overlapping controls in synthetic screenshots. The latter two widths approximate 125% and 150% zoom on a 1280-pixel viewport.
- [ ] Manual follow-up: test the native merge-target select with keyboard in headed Chrome; headless Chrome 155 did not change even a two-option standalone `<select>` with ArrowDown.
- [ ] Manual follow-up: test Chrome's actual 125%/150% browser zoom; the automated run tested equivalent effective viewport widths.
- [x] Return to subscriptions with browser Back after a category rename: Add/Edit option, filter, and grouped heading all show the new name. Headless Chrome performed a normal Back navigation; a synthetic `pageshow.persisted=true` event confirmed the refresh branch reloads. An actual bfcache restoration remains unobserved in this run.
- [x] Renaming a category from the inherited Settings control updates the revision marker, and returning to the subscription list shows the new name.
- [x] With zero subscriptions, the empty state still shows a working Manage categories entry. At 853 CSS pixels, the populated subscription toolbar has no horizontal overflow; a synthetic screenshot is retained with the run report.
- [x] Isolated Demo origin with `DEMO_MODE=1`: category writes return the application `demo_read_only` failure. A local Caddy clone serves `GET /categories.php` and returns 404 for both category POST endpoints (`tests/category-demo-edge.sh`). No public server configuration was changed.
- [x] Wallos 5.8.3 add and edit requests using another account's category ID return its explicit ownership error; subscription rows and synthetic Logo files are byte-for-byte unchanged (`tests/category-http.sh`).
