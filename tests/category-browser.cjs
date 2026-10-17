#!/usr/bin/env node
// Self-cleaning browser exercise for the category manager using fictional data.
'use strict';
const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:category';
const port = process.env.DIAL_CATEGORY_BROWSER_PORT || '43184';
const origin = `http://127.0.0.1:${port}`;
const name = `dial-category-browser-${process.pid}`;
const reportDir = process.env.DIAL_CATEGORY_QA_DIR || path.join(root, '.superpowers/sdd/2026-10-17-wallos-dial-category-manager/screenshots');
fs.mkdirSync(reportDir, { recursive: true });
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-category-browser.'));
fs.mkdirSync(`${scratch}/db`);
fs.mkdirSync(`${scratch}/logos`);
const run = (cmd, args) => execFileSync(cmd, args, { cwd: root, encoding: 'utf8' }).trim();
const docker = (...args) => run('docker', args);
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const logs = () => { const result = spawnSync('docker', ['logs', name], { encoding: 'utf8' }); return `${result.stdout || ''}${result.stderr || ''}`; };
function stop() { try { docker('rm', '-f', name); } catch (_) { /* already stopped */ } }
async function start(demo) {
  docker('run', '-d', '--name', name, '-e', `DEMO_MODE=${demo ? 1 : 0}`,
    '-p', `127.0.0.1:${port}:80`,
    '-v', `${scratch}/db:/var/www/html/db`,
    '-v', `${scratch}/logos:/var/www/html/images/uploads/logos`, image);
  for (let i = 0; i < 45; i++) {
    if (logs().includes('Updated next payment dates')) {
      try { if ((await fetch(`${origin}/health.php`)).ok) return; } catch (_) { /* wait */ }
    }
    await pause(1000);
  }
  throw new Error(`Container startup failed: ${logs()}`);
}
const must = (condition, message) => { if (!condition) throw new Error(message); };
async function openManager(page) {
  // The synthetic no-password account first redirects through login.php.
  await page.goto(`${origin}/`, { waitUntil: 'load' });
  const response = await page.goto(`${origin}/categories.php`, { waitUntil: 'load' });
  try { await page.locator('main[data-testid="dial-category-manager"]').waitFor({ timeout: 10000 }); }
  catch (_) {
    throw new Error(`Manager did not render: ${JSON.stringify({ url: page.url(), status: response?.status(),
      title: await page.title(), html: (await page.content()).slice(0, 800), logs: logs().slice(-1800) })}`);
  }
}

(async () => {
  let context;
  try {
    await start(false);
    docker('exec', name, 'sh', '-c', 'chmod a+rwX /var/www/html/db /var/www/html/db/wallos.db /var/www/html/images/uploads/logos');
    stop();
    run('python3', ['demo/seed.py', `${scratch}/db/wallos.db`, `${scratch}/logos`]);
    run('python3', ['-c', `import sqlite3,sys
db=sqlite3.connect(sys.argv[1]);db.execute('INSERT INTO categories (name,"order",user_id) VALUES ("Empty sample",99,1)');db.commit();db.close()`, `${scratch}/db/wallos.db`]);
    await start(false);
    context = await chromium.launchPersistentContext(`${scratch}/chrome-profile`, {
      channel: 'chrome', headless: true, serviceWorkers: 'allow', viewport: { width: 1280, height: 800 },
    });
    const page = context.pages()[0] || await context.newPage();
    const rowById = id => page.locator(`[data-testid="dial-category-row"][data-category-id="${id}"]`);
    const activateAndWaitForReload = async button => {
      const loaded = page.waitForEvent('load', { timeout: 20000 });
      await button.focus();
      await page.keyboard.press('Enter');
      try { await loaded; } catch (error) {
        throw new Error(`Action did not reload: ${JSON.stringify({
          status: await page.locator('#dial-category-status').textContent(),
          url: page.url(), focused: await page.evaluate(() => document.activeElement?.outerHTML?.slice(0, 180)),
          pageErrors,
        })}`);
      }
      await page.locator('main[data-testid="dial-category-manager"]').waitFor();
    };
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('response', async response => {
      if (!response.url().includes('/endpoints/categories/')) return;
      const type = response.headers()['content-type'] || '';
      const body = await response.text().catch(() => '');
      if (!type.includes('application/json') || body.startsWith('<')) {
        console.error('Category endpoint returned non-JSON:', response.status(),
          body.slice(0, 1000));
      }
    });
    await openManager(page);
    const countBefore = await page.locator('[data-testid="dial-category-row"]').count();
    must(countBefore === 6, `Expected 6 categories, got ${countBefore}`);
    const screenshots = [];
    const screenshot = async label => {
      const file = path.join(reportDir, `${label}.png`);
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.waitForTimeout(100);
      await page.screenshot({ path: file, fullPage: true });
      screenshots.push(file);
    };
    await screenshot('initial-1280');

    const addInput = page.locator('[data-testid="dial-category-new-name"]');
    await addInput.focus();
    await addInput.pressSequentially('AI & Tools');
    await activateAndWaitForReload(page.locator('[data-testid="dial-category-add"]'));
    await page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="AI & Tools"]') }).waitFor();
    let custom = page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="AI & Tools"]') });
    must(await custom.locator('[data-testid="dial-category-name"]').inputValue() === 'AI & Tools', 'Add encoded name twice');
    await custom.locator('[data-testid="dial-category-name"]').fill('AI & Tools revised');
    await activateAndWaitForReload(custom.locator('[data-testid="dial-category-save"]'));
    custom = page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="AI & Tools revised"]') });
    await custom.waitFor();
    const customId = await custom.getAttribute('data-category-id');
    const customInput = custom.locator('[data-testid="dial-category-name"]');
    must(await customInput.inputValue() === 'AI & Tools revised', 'Rename doubled entity');
    await activateAndWaitForReload(custom.locator('[data-testid="dial-category-move-up"]'));
    custom = rowById(customId);
    await activateAndWaitForReload(custom.locator('[data-testid="dial-category-move-down"]'));

    const empty = page.locator('[data-testid="dial-category-row"]').filter({ hasText: 'Empty sample' });
    await empty.locator('[data-testid="dial-category-delete"]').focus();
    await page.keyboard.press('Enter');
    const deleteDialog = page.locator('[data-testid="dial-category-delete-dialog"]');
    must(await deleteDialog.evaluate(node => node.open), 'Delete dialog did not open');
    await page.keyboard.press('Escape');
    must(!await deleteDialog.evaluate(node => node.open), 'Escape did not close delete dialog');
    must(await empty.locator('[data-testid="dial-category-delete"]').evaluate(node => node === document.activeElement), 'Delete focus not returned');

    const ai = page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="AI"]') });
    const aiId = await ai.getAttribute('data-category-id');
    await ai.locator('[data-testid="dial-category-merge"]').focus();
    await page.keyboard.press('Enter');
    const mergeDialog = page.locator('[data-testid="dial-category-merge-dialog"]');
    must(await mergeDialog.evaluate(node => node.open), 'Merge dialog did not open');
    await page.keyboard.press('Escape');
    must(await ai.locator('[data-testid="dial-category-merge"]').evaluate(node => node === document.activeElement), 'Merge focus not returned');
    await ai.locator('[data-testid="dial-category-merge"]').focus();
    await page.keyboard.press('Enter');
    const infraId = await page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="Infra"]') }).getAttribute('data-category-id');
    const mergeTarget = mergeDialog.locator('[data-testid="dial-category-merge-target"]');
    await mergeTarget.selectOption(infraId);
    must((await mergeDialog.locator('#dial-category-merge-help').textContent()).includes('8'), 'Merge count not shown');
    await activateAndWaitForReload(mergeDialog.locator('[data-testid="dial-category-merge-confirm"]'));
    if (await rowById(aiId).count() !== 0) {
      throw new Error(`Merge source remains: ${JSON.stringify({
        status: await page.locator('#dial-category-status').textContent(),
        dialogOpen: await mergeDialog.evaluate(node => node.open), pageErrors,
      })}`);
    }
    if (await rowById(infraId).count() !== 1) {
      throw new Error(`Merge target missing: ${JSON.stringify({ url: page.url(),
        title: await page.title(),
        rows: await page.locator('[data-testid="dial-category-row"]').evaluateAll(nodes => nodes.map(node => [node.dataset.categoryId, node.dataset.categoryName])),
        pageErrors })}`);
    }
    must(await rowById(infraId).getAttribute('data-category-count') === '16', 'Merged subscriptions lost');

    custom = rowById(customId);
    await custom.locator('[data-testid="dial-category-delete"]').focus();
    await page.keyboard.press('Enter');
    await activateAndWaitForReload(page.locator('[data-testid="dial-category-delete-confirm"]'));
    must(await rowById(customId).count() === 0, 'Empty delete failed');

    const domains = page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="Domains"]') });
    const media = page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="Media"]') });
    const domainsId = await domains.getAttribute('data-category-id');
    const mediaId = await media.getAttribute('data-category-id');
    const beforeDrag = await page.locator('[data-testid="dial-category-row"]').evaluateAll(nodes => nodes.map(node => node.dataset.categoryId));
    must(await page.evaluate(() => typeof Sortable === 'function'), 'SortableJS was not loaded');
    await empty.scrollIntoViewIfNeeded();
    const sourceBox = await domains.locator('.dial-category-drag').boundingBox();
    const targetBox = await empty.boundingBox();
    must(sourceBox && targetBox, 'Drag positions unavailable');
    must(sourceBox.y >= 0 && targetBox.y + targetBox.height <= 800,
      `Drag target outside viewport: ${JSON.stringify({ sourceBox, targetBox })}`);
    await page.evaluate(() => {
      const instance = Sortable.get(document.getElementById('dial-category-list'));
      window.__dialDragInfo = { instance: !!instance, started: false, ended: false,
        options: instance ? { disabled: instance.option('disabled'), handle: instance.option('handle'),
          draggable: instance.option('draggable'), filter: instance.option('filter') } : null,
        pointerdowns: 0, mousedowns: 0, dragstarts: 0, moves: [] };
      document.addEventListener('pointerdown', event => { if (event.target.closest('.dial-category-drag')) window.__dialDragInfo.pointerdowns++; }, true);
      document.addEventListener('mousedown', event => { if (event.target.closest('.dial-category-drag')) window.__dialDragInfo.mousedowns++; }, true);
      document.addEventListener('dragstart', event => { if (event.target.closest('.dial-category-row')) window.__dialDragInfo.dragstarts++; }, true);
      if (instance) {
        const start = instance.option('onStart');
        const move = instance.option('onMove');
        const end = instance.option('onEnd');
        instance.option('onStart', function (event) { window.__dialDragInfo.started = true; if (start) start.call(this, event); });
        instance.option('onMove', function (event) {
          window.__dialDragInfo.moves.push({ related: event.related?.dataset.categoryId,
            after: event.willInsertAfter });
          if (move) return move.call(this, event);
        });
        instance.option('onEnd', function (event) {
          window.__dialDragInfo.ended = true;
          window.__dialDragInfo.oldIndex = event.oldIndex;
          window.__dialDragInfo.newIndex = event.newIndex;
          if (end) end.call(this, event);
        });
      }
    });
    const dragLoaded = page.waitForEvent('load', { timeout: 12000 }).catch(() => null);
    await page.mouse.move(sourceBox.x + sourceBox.width / 2, sourceBox.y + sourceBox.height / 2);
    await page.mouse.down();
    await page.mouse.move(sourceBox.x + sourceBox.width / 2 + 16, sourceBox.y + sourceBox.height / 2 + 16, { steps: 8 });
    await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + 5, { steps: 28 });
    await page.waitForTimeout(180);
    await page.mouse.up();
    const reloaded = await dragLoaded;
    const ordered = await page.locator('[data-testid="dial-category-row"]').evaluateAll(nodes => nodes.map(node => node.dataset.categoryId));
    must(ordered.includes(domainsId) && ordered.includes(mediaId) && ordered.join() !== beforeDrag.join(),
      `Drag did not change category order: ${JSON.stringify({ beforeDrag, ordered,
        reloaded: !!reloaded, dragInfo: await page.evaluate(() => window.__dialDragInfo || null),
        status: await page.locator('#dial-category-status').textContent() })}`);
    await page.reload({ waitUntil: 'load' });
    const persistedOrder = await page.locator('[data-testid="dial-category-row"]').evaluateAll(nodes => nodes.map(node => node.dataset.categoryId));
    must(persistedOrder.join() === ordered.join(), 'Dragged order did not persist');

    for (const [width, label] of [[1280, '1280'], [1024, '125-percent'], [853, '150-percent']]) {
      await page.setViewportSize({ width, height: 800 });
      await page.waitForTimeout(120);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      must(overflow <= 1, `Horizontal overflow at ${label}: ${overflow}px`);
      await screenshot(`layout-${label}`);
    }
    await page.setViewportSize({ width: 1280, height: 800 });
    for (const [language, title, addLabel, mergeLabel, invalidName] of [
      ['zh_cn', '分类管理', '新增分类', '合并', '请输入不超过 60 个字符的分类名称。'],
      ['zh_tw', '分類管理', '新增分類', '合併', '請輸入不超過 60 個字元的分類名稱。'],
      ['en', 'Categories', 'Add category', 'Merge', 'Enter a category name with up to 60 characters.'],
    ]) {
      await context.addCookies([{ name: 'language', value: language, url: origin }]);
      await openManager(page);
      must((await page.locator('main h1').textContent()).trim() === title, `Wrong ${language} title`);
      must((await page.locator('[data-testid="dial-category-add"]').textContent()).includes(addLabel), `Wrong ${language} add label`);
      must((await page.locator('[data-testid="dial-category-merge-dialog"] h2').textContent()).includes(mergeLabel), `Wrong ${language} merge label`);
      await page.locator('[data-testid="dial-category-new-name"]').fill('');
      await page.locator('[data-testid="dial-category-add"]').click();
      must((await page.locator('#dial-category-status').textContent()).trim() === invalidName, `Wrong ${language} validation copy`);
    }

    await page.goto(`${origin}/subscriptions.php`, { waitUntil: 'load' });
    const subscriptionsManage = page.locator('a[href="categories.php"][data-testid="dial-category-manage-link"]');
    must(await subscriptionsManage.count() === 1, 'Subscriptions manager link absent');
    await page.setViewportSize({ width: 853, height: 800 });
    must(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Subscriptions toolbar overflows at 853px');
    await screenshot('subscriptions-853');
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.evaluate(() => window.addEventListener('pageshow', event =>
      sessionStorage.setItem('dialBackWasBfcache', String(event.persisted))));
    await subscriptionsManage.click();
    await page.locator('main[data-testid="dial-category-manager"]').waitFor();
    const infraInput = rowById(infraId).locator('[data-testid="dial-category-name"]');
    await infraInput.fill('Infra & Ops');
    await activateAndWaitForReload(rowById(infraId).locator('[data-testid="dial-category-save"]'));
    const revisionAfterEdit = await page.evaluate(() => sessionStorage.getItem('dialCategoryRevision'));
    must(!!revisionAfterEdit, 'Category revision was not stored');
    await page.goBack({ waitUntil: 'load' });
    await page.locator('#category').waitFor({ state: 'attached' });
    must((await page.locator(`#category option[value="${infraId}"]`).textContent()).trim() === 'Infra & Ops', 'Back restored a stale Add/Edit option');
    must((await page.locator(`#filter-category [data-categoryid="${infraId}"]`).textContent()).trim() === 'Infra & Ops', 'Back restored a stale filter');
    must((await page.locator(`.dial-category-group[data-category-id="${infraId}"] .dial-category-name`).textContent()).trim() === 'Infra & Ops', 'Back restored a stale group');
    const backUsedCache = await page.evaluate(() => sessionStorage.getItem('dialBackWasBfcache'));
    const syntheticReload = page.waitForEvent('load', { timeout: 10000 });
    await page.evaluate(() => {
      sessionStorage.setItem('dialCategoryRevision', `simulated-bfcache-${Date.now()}`);
      window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    });
    await syntheticReload;
    must((await page.locator(`#category option[value="${infraId}"]`).textContent()).trim() === 'Infra & Ops', 'bfcache refresh branch lost current category');
    await page.goto(`${origin}/settings.php`, { waitUntil: 'load' });
    const settingsManage = page.locator('a[href="categories.php"][data-testid="dial-category-manage-link"]');
    must(await settingsManage.count() === 1, 'Settings manager link absent');
    await settingsManage.click();
    await page.locator('main[data-testid="dial-category-manager"]').waitFor();
    must(await page.locator('.dial-settings-button.is-active').count() === 1, 'Category navigation state unclear');

    await page.goto(`${origin}/subscriptions.php`, { waitUntil: 'load' });
    await page.goto(`${origin}/settings.php`, { waitUntil: 'load' });
    const revisionBeforeSettingsEdit = await page.evaluate(() => sessionStorage.getItem('dialCategoryRevision'));
    const settingsCategory = page.locator(`#categories [data-categoryid="${infraId}"]`);
    await settingsCategory.locator('input[name="category"]').fill('Infra & Settings');
    await settingsCategory.locator('button[name="save"]').click();
    await page.waitForFunction(previous => sessionStorage.getItem('dialCategoryRevision') !== previous,
      revisionBeforeSettingsEdit, { timeout: 10000 });
    await page.goBack({ waitUntil: 'load' });
    must((await page.locator(`.dial-category-group[data-category-id="${infraId}"] .dial-category-name`).textContent()).trim() === 'Infra & Settings',
      'Back after Settings edit restored a stale category group');

    run('python3', ['-c', `import sqlite3,sys
db=sqlite3.connect(sys.argv[1]);db.execute('DELETE FROM subscriptions WHERE user_id=1');db.commit();db.close()`, `${scratch}/db/wallos.db`]);
    await page.goto(`${origin}/subscriptions.php`, { waitUntil: 'load' });
    const emptyManage = page.locator('.empty-page a[href="categories.php"][data-testid="dial-category-manage-link"]');
    must(await emptyManage.isVisible(), 'Empty subscription page hides category management');
    await screenshot('subscriptions-empty');

    stop();
    run('python3', ['-c', `import sqlite3,sys
db=sqlite3.connect(sys.argv[1]);db.execute('DELETE FROM subscriptions WHERE user_id=1');db.commit();db.close()`, `${scratch}/db/wallos.db`]);
    await start(false);
    await openManager(page);
    const starter = page.locator('[data-testid="dial-category-starter"]');
    must(await starter.isEnabled(), 'Starter unavailable for empty account');
    await starter.focus();
    await page.keyboard.press('Enter');
    const starterDialog = page.locator('[data-testid="dial-category-starter-dialog"]');
    must(await starterDialog.evaluate(node => node.open), 'Starter dialog did not open');
    must(await starterDialog.locator('[data-testid="dial-category-starter-delete"]:checked').count() === 0, 'Starter preselected deletion');
    await page.keyboard.press('Escape');
    must(await starter.evaluate(node => node === document.activeElement), 'Starter focus not returned');
    await starter.focus();
    await page.keyboard.press('Enter');
    must(await starterDialog.locator('[data-testid="dial-category-starter-delete"]').count() > 0, 'No empty deletion choices');
    const emptyId = await page.locator('[data-testid="dial-category-row"]').filter({ hasText: 'Empty sample' }).getAttribute('data-category-id');
    await starterDialog.locator(`[data-category-id="${emptyId}"]`).check();
    await activateAndWaitForReload(starterDialog.locator('[data-testid="dial-category-starter-confirm"]'));
    must(await rowById(emptyId).count() === 0, 'Selected starter deletion not applied');
    must(await page.locator('[data-testid="dial-category-row"]').filter({ has: page.locator('input[value="AI"]') }).count() === 1, 'Missing AI starter not added');

    stop();
    await start(true);
    await openManager(page);
    must(await page.locator('[data-testid="dial-category-readonly"]').count() === 1, 'Demo read-only message absent');
    must(await page.locator('[data-testid="dial-category-add"]').count() === 0, 'Demo add button exposed');
    must(pageErrors.length === 0, `Browser errors: ${pageErrors.join('; ')}`);
    console.log(JSON.stringify({ result: 'passed', language: ['en', 'zh_cn', 'zh_tw'],
      keyboard: ['add', 'rename', 'up', 'down', 'merge open/confirm', 'delete', 'starter'],
      mergeTargetSelect: 'selected by Playwright; native keyboard pending headed Chrome QA',
      pointer: ['drag'], focusReturn: ['merge', 'delete', 'starter'],
      layoutWidths: [1280, 1024, 853], demoReadOnly: true,
      backNavigation: { updated: true, bfcacheObserved: backUsedCache, persistedEventBranch: 'simulated and reloaded' },
      settingsCategoryRevision: true, emptyPageManageLink: true,
      screenshotCount: screenshots.length, pageErrors }, null, 2));
  } finally {
    if (context) await context.close();
    stop();
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
