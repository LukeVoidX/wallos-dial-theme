#!/usr/bin/env node
// Interactive chooser acceptance against a disposable Wallos account.
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:compat';
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-chooser-browser.'));
const captures = path.join(root, '.superpowers/sdd/2026-10-17-wallos-dial-icon-system/screenshots');
fs.mkdirSync(captures, { recursive: true });
const fixturePath = path.join(scratch, 'fixture.json');
const service = spawn('bash', ['tests/run-icon-http.sh', image, '--serve', '--fixture-out', fixturePath],
  { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
let serviceLog = '';
for (const stream of [service.stdout, service.stderr]) stream.on('data', chunk => { serviceLog += chunk.toString(); });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const must = (value, message) => { if (!value) throw new Error(message); };

async function fixture() {
  for (let attempt = 0; attempt < 55; attempt++) {
    if (fs.existsSync(fixturePath)) return JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
    if (service.exitCode !== null) throw new Error('Fixture stopped: ' + serviceLog);
    await pause(1000);
  }
  throw new Error('Fixture timed out: ' + serviceLog);
}

(async () => {
  let browser;
  const demoName = `dial-chooser-demo-${process.pid}`;
  let demoRunning = false;
  try {
    const { base_url: base, database } = await fixture();
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, serviceWorkers: 'block' });
    const login = await context.request.post(`${base}/login.php`, {
      form: { username: 'demo', password: 'dial-fixture-only' },
    });
    must(login.ok(), `Login failed: ${login.status()}`);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    const geometry = async () => page.getByTestId('dial-subscription-1').evaluate(card => {
      const rect = selector => {
        const node = card.querySelector(selector);
        const { left, right, top, bottom } = node.getBoundingClientRect();
        return { left, right, top, bottom };
      };
      return { quick: rect('.dial-quick-icon-button'), menu: rect('.actions-expand'),
        payment: rect('.payment_method') };
    });
    const listGeometry = await geometry();
    must(listGeometry.quick.right <= listGeometry.menu.left + 1 &&
      listGeometry.payment.right <= listGeometry.quick.left + 1,
    `List quick action overlaps payment/menu: ${JSON.stringify(listGeometry)}`);
    await page.locator('#view-grid-button').click();
    await page.locator('#subscriptions.grid-view').waitFor();
    const gridGeometry = await geometry();
    must(gridGeometry.quick.right <= gridGeometry.menu.left + 1,
      `Grid quick action overlaps menu: ${JSON.stringify(gridGeometry)}`);
    await page.locator('#view-list-button').click();
    await page.locator('#subscriptions:not(.grid-view)').waitFor();
    await page.evaluate(() => addSubscription());
    await page.getByTestId('dial-icon-open-form').click();
    must(await page.getByTestId('dial-icon-chooser').evaluate(node => node.open), 'Chooser did not open');
    must(await page.locator('#dial-icon-chooser-title').textContent() === 'Choose an icon', 'English chooser label missing');
    await page.getByTestId('dial-icon-source-catalog').click();
    await page.getByTestId('dial-icon-catalog-chatgpt').click();
    await page.waitForFunction(() => {
      const images = [...document.querySelectorAll('#dial-icon-chooser .dial-icon-preview-art img')];
      return images.length === 2 && images.every(image => image.complete && image.naturalWidth > 0);
    });
    const darkCatalogFilter = await page.locator('.is-dark .dial-icon-preview-art img')
      .evaluate(image => getComputedStyle(image).filter);
    must(darkCatalogFilter.includes('invert('),
      `Neutral catalog symbol disappears on the dark preview: ${darkCatalogFilter}`);
    await page.getByTestId('dial-icon-presentation-framed').click();
    await page.getByTestId('dial-icon-treatment-mono').click();
    await page.getByTestId('dial-icon-scale').focus();
    await page.keyboard.press('End');
    must(await page.locator('#dial-icon-scale-value').textContent() === '120%', 'Range output failed');
    await page.screenshot({ path: path.join(captures, 'icon-chooser-en-1280.png') });
    // Browser zoom at 125% yields a 720 CSS-pixel viewport from 900 device pixels.
    await page.setViewportSize({ width: 720, height: 608 });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const overflow = await page.getByTestId('dial-icon-chooser').evaluate(node => ({
      internal: node.scrollWidth - node.clientWidth,
      offscreen: Math.max(0, node.getBoundingClientRect().right - innerWidth),
    }));
    must(overflow.internal <= 1 && overflow.offscreen <= 1,
      `Chooser overflows at 900px/125% equivalent: ${JSON.stringify(overflow)}`);
    await page.screenshot({ path: path.join(captures, 'icon-chooser-en-900-125pct.png') });
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.getByTestId('dial-icon-save').click();
    const first = await page.evaluate(() => DialIconChooser.formFields());
    must(first.dial_icon_source === 'catalog' && first.dial_icon_catalog_key === 'chatgpt' &&
      first.dial_icon_presentation === 'framed' && first.dial_icon_treatment === 'mono' &&
      first.dial_icon_scale === '120', `Catalog selection failed: ${JSON.stringify(first)}`);

    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-original').click();
    await page.keyboard.press('Escape');
    await page.getByTestId('dial-icon-chooser').waitFor({ state: 'hidden' });
    const afterEscape = await page.evaluate(() => DialIconChooser.formFields());
    must(JSON.stringify(first) === JSON.stringify(afterEscape), 'Escape did not restore selection');
    must(await page.getByTestId('dial-icon-open-form').evaluate(node => node === document.activeElement),
      'Focus did not return to opener');

    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-upload').click();
    await page.getByTestId('dial-icon-upload').setInputFiles(path.join(root, 'theme/images/dial-icon/favicon-32.png'));
    await page.locator('#dial-icon-upload-label').fill('Synthetic account mark');
    await page.locator('#dial-icon-upload-action').click();
    await page.getByTestId('dial-icon-library-1').waitFor();
    must((await page.locator('.dial-icon-library-quality').textContent()).includes('Low resolution') &&
      (await page.locator('.dial-icon-library-quality').textContent()).includes('32 × 32 px') &&
      (await page.locator('#dial-icon-status').textContent()).includes('Low resolution'),
    'A 32 px uploaded raster has no visible low-resolution warning');
    must(await page.getByTestId('dial-icon-library-1').getAttribute('aria-pressed') === 'true', 'Uploaded asset not selected');
    await page.getByTestId('dial-icon-save').click();
    const uploaded = await page.evaluate(() => DialIconChooser.formFields());
    must(uploaded.dial_icon_source === 'asset' && uploaded.dial_icon_asset_id === '1',
      `Uploaded asset selection failed: ${JSON.stringify(uploaded)}`);

    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-original').click();
    await page.getByTestId('dial-icon-save').click();
    const original = await page.evaluate(() => DialIconChooser.formFields());
    must(original.dial_icon_source === 'original' && original.dial_icon_asset_id === '', 'Original restoration failed');
    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-library').click();
    await page.getByTestId('dial-icon-library-1').waitFor();
    page.once('dialog', prompt => prompt.accept());
    await page.locator('.dial-icon-library-row').filter({ has: page.getByTestId('dial-icon-library-1') })
      .locator('.dial-icon-delete').click();
    await page.getByTestId('dial-icon-library-1').waitFor({ state: 'detached' });
    await page.keyboard.press('Escape');
    await page.screenshot({ path: path.join(scratch, 'chooser-after.png') });

    for (const [language, title] of [['zh_cn', '选择图标'], ['zh_tw', '選擇圖標']]) {
      await page.locator('#dial-language').selectOption(language);
      await page.waitForFunction(expected => document.getElementById('dial-icon-chooser-title')?.textContent === expected,
        title, { timeout: 15000 });
      await page.evaluate(() => addSubscription());
      await page.getByTestId('dial-icon-open-form').click();
      must(await page.locator('#dial-icon-chooser-title').textContent() === title,
        `${language} chooser label missing`);
      await page.keyboard.press('Escape');
      await page.evaluate(() => closeAddSubscription());
    }

    // The public Demo may preview a choice but must offer no write affordance.
    execFileSync('docker', ['run', '-d', '--name', demoName, '-e', 'DEMO_MODE=1', '-p', '127.0.0.1::80',
      '-v', `${path.dirname(database)}:/var/www/html/db`,
      '-v', `${path.resolve(path.dirname(database), '../logos')}:/var/www/html/images/uploads/logos`, image],
      { cwd: root, encoding: 'utf8' });
    demoRunning = true;
    const demoPort = execFileSync('docker', ['port', demoName, '80/tcp'], { encoding: 'utf8' }).trim().split(':').pop();
    const demoBase = `http://127.0.0.1:${demoPort}`;
    for (let attempt = 0; attempt < 30; attempt++) {
      try { if ((await fetch(`${demoBase}/health.php`)).ok) break; } catch (_) { /* wait */ }
      await pause(500);
      if (attempt === 29) throw new Error('Demo fixture did not become ready');
    }
    const demoContext = await browser.newContext({ viewport: { width: 1280, height: 800 }, serviceWorkers: 'block' });
    const demoLogin = await demoContext.request.post(`${demoBase}/login.php`, {
      form: { username: 'demo', password: 'dial-fixture-only' },
    });
    must(demoLogin.ok(), `Demo login failed: ${demoLogin.status()}`);
    const demoPage = await demoContext.newPage();
    await demoPage.goto(`${demoBase}/subscriptions.php`, { waitUntil: 'load' });
    await demoPage.evaluate(() => addSubscription());
    await demoPage.getByTestId('dial-icon-open-form').click();
    must(await demoPage.getByTestId('dial-icon-save').isDisabled() &&
      await demoPage.locator('#dial-icon-upload-action').isDisabled() &&
      await demoPage.getByTestId('dial-icon-upload').isDisabled(),
    'Demo chooser exposes a write control');
    must(await demoPage.locator('.dial-icon-demo-note').isVisible(), 'Demo explanation absent');
    await demoContext.close();
    execFileSync('docker', ['rm', '-f', demoName], { stdio: 'ignore' });
    demoRunning = false;

    // A quick icon change must not rewrite unrelated subscription values.
    const seededIds = JSON.parse(execFileSync('python3', ['-c', `
import json,sqlite3,sys
db=sqlite3.connect(sys.argv[1]); ids=[row[0] for row in db.execute('SELECT id FROM subscriptions WHERE user_id=1 ORDER BY id LIMIT 2')]
assert len(ids)==2
db.execute("UPDATE subscriptions SET cycle=5,frequency=1,auto_renew=0,notify=0,notify_days_before=17,cancellation_date=NULL WHERE id=?",(ids[0],))
db.execute("UPDATE subscriptions SET cycle=3,frequency=1,auto_renew=1,notify=0,notify_days_before=31,cancellation_date='2099-12-31' WHERE id=?",(ids[1],))
db.commit();print(json.dumps(ids));db.close()
`, database], { encoding: 'utf8' }));
    const snapshot = id => JSON.parse(execFileSync('python3', ['-c', `
import json,sqlite3,sys
db=sqlite3.connect(sys.argv[1]);db.row_factory=sqlite3.Row
fields='cycle,frequency,auto_renew,notify,notify_days_before,cancellation_date,next_payment,start_date,logo,logo_variant,logo_text_color'
row=db.execute('SELECT '+fields+' FROM subscriptions WHERE id=? AND user_id=1',(int(sys.argv[2]),)).fetchone()
print(json.dumps(dict(row)));db.close()
`, database, String(id)], { encoding: 'utf8' }));
    const quickResults = [];
    for (const id of seededIds) {
      const before = snapshot(id);
      await page.evaluate(subscriptionId => openDialSubscriptionIcon({
        stopPropagation() {}, preventDefault() {},
      }, subscriptionId), id);
      await page.getByTestId('dial-icon-chooser').waitFor({ state: 'visible' });
      await page.getByTestId('dial-icon-source-catalog').click();
      await page.getByTestId('dial-icon-catalog-chatgpt').click();
      const responsePromise = page.waitForResponse(response => response.url().includes('/endpoints/dial/subscription_icon.php'),
        { timeout: 15000 });
      await page.getByTestId('dial-icon-save').click();
      const response = await responsePromise;
      const payload = await response.json();
      must(response.ok() && payload.success === true && payload.code === 'icon_saved',
        `Quick icon update failed for ${id}: ${JSON.stringify(payload)}`);
      const after = snapshot(id);
      quickResults.push({ id, preserved: JSON.stringify(before) === JSON.stringify(after), before, after });
    }
    must(quickResults.every(item => item.preserved),
      `Quick icon update changed subscription data: ${JSON.stringify(quickResults.filter(item => !item.preserved))}`);

    // Fill this account's private library without touching the other account.
    // A real server quota response must show current usage and a path to free
    // an unused asset, rather than the chooser's generic upload error.
    execFileSync('python3', ['-c', `
import hashlib,pathlib,sqlite3,sys
db=sqlite3.connect(sys.argv[1])
sample=pathlib.Path(sys.argv[2]).read_bytes()
assert db.execute('SELECT COUNT(*) FROM dial_icon_assets WHERE user_id=1').fetchone()[0] == 0
for number in range(100):
    db.execute('INSERT INTO dial_icon_assets (user_id,label,mime,bytes,sha256,width,height) VALUES (1,?,?,?,?,1,1)',
        ('Synthetic unused ' + str(number), 'image/png', sample, hashlib.sha256(('quota-fixture-' + str(number)).encode()).hexdigest()))
db.commit();db.close()
`, database, path.join(root, 'theme/images/dial-icon/favicon-32.png')]);
    await page.locator('#dial-language').selectOption('en');
    await page.waitForFunction(() => document.getElementById('dial-icon-chooser-title')?.textContent === 'Choose an icon');
    await page.evaluate(() => addSubscription());
    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-upload').click();
    await page.locator('#dial-icon-upload-usage').waitFor({ state: 'visible' });
    await page.waitForFunction(() => document.getElementById('dial-icon-upload-usage')?.textContent.includes('100/100'));
    await page.getByTestId('dial-icon-upload').setInputFiles(path.join(root, 'theme/images/dial-icon/favicon-32.png'));
    await page.locator('#dial-icon-upload-action').click();
    await page.locator('#dial-icon-status[data-state="error"]').waitFor();
    must((await page.locator('#dial-icon-status').textContent()).includes('100/100'),
      'Count quota must explain current usage');
    await page.locator('#dial-icon-quota-manage').click();
    must(await page.locator('#dial-icon-panel-library').isVisible(),
      'Quota action must open My Icons so unused assets can be deleted');
    page.once('dialog', prompt => prompt.accept());
    await page.locator('.dial-icon-library-row .dial-icon-delete').first().click();
    await page.waitForFunction(() => document.getElementById('dial-icon-library-usage')?.textContent.includes('99/100'));
    await page.keyboard.press('Escape');

    must(errors.length === 0, `Browser errors: ${errors.join('; ')}`);
    process.stdout.write(JSON.stringify({ result: 'passed', languages: ['en','zh_cn','zh_tw'],
      catalog: first, uploaded, original, quickResults, listGeometry, gridGeometry,
      browserErrors: errors }) + '\n');
    await context.close();
  } finally {
    if (browser) await browser.close();
    if (demoRunning) {
      try { execFileSync('docker', ['rm', '-f', demoName], { stdio: 'ignore' }); } catch (_) { /* cleaned */ }
    }
    service.kill('SIGTERM');
    for (let attempt = 0; attempt < 30 && service.exitCode === null; attempt++) await pause(100);
    if (service.exitCode === null) service.kill('SIGKILL');
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
