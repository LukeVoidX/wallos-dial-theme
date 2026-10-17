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
- [ ] Task 5: return to subscriptions with browser Back and verify category choices, filters, and groups reflect the saved state.
