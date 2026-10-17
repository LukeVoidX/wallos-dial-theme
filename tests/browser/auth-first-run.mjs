#!/usr/bin/env node
// A real, disposable first-run journey. All account details are fictional.
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const evidence = path.join(root, 'data', 'auth-install-qa');
const image = process.argv[2] || 'wallos-dial:1.0.0';
const fixture = fs.mkdtempSync(path.join(root, 'data', 'auth-install-'));
const dbDir = path.join(fixture, 'db');
const logoDir = path.join(fixture, 'logos');
const name = `dial-auth-install-${process.pid}`;
const hostUid = process.getuid?.() ?? 82;
const hostGid = process.getgid?.() ?? 82;
const password = 'Dial-QA-Synthetic-2026!';
const email = 'dial-qa@example.invalid';
const secret = 'JBSWY3DPEHPK3PXP';
fs.mkdirSync(dbDir);
fs.mkdirSync(logoDir);
fs.mkdirSync(evidence, { recursive: true });

function docker(...args) {
  return execFileSync('docker', args, { encoding: 'utf8' }).trim();
}

function sql(statement) {
  const program = `import sqlite3, sys
db = sqlite3.connect('file:' + sys.argv[1] + '?mode=rw', uri=True, timeout=5)
cursor = db.execute(sys.argv[2])
row = cursor.fetchone() if cursor.description else None
db.commit()
if row is not None: print(row[0])`;
  return execFileSync('python3', ['-c', program, path.join(dbDir, 'wallos.db'), statement],
    { encoding: 'utf8' }).trim();
}

function totp(secretText) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const letter of secretText) bits += alphabet.indexOf(letter).toString(2).padStart(5, '0');
  const key = Buffer.from(bits.match(/.{8}/g).map(byte => parseInt(byte, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
  const digest = crypto.createHmac('sha1', key).update(counter).digest();
  const offset = digest[digest.length - 1] & 15;
  return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).toString().padStart(6, '0');
}

async function authSurface(page, route, label, expectedForm) {
  await page.goto(`${base}/${route}`, { waitUntil: 'load' });
  assert.equal(new URL(page.url()).pathname, `/${route.split('?')[0]}`, `${label} route`);
  await page.screenshot({ path: path.join(evidence, `${label}.png`), fullPage: true });
  const visual = await page.evaluate(() => ({
    brand: getComputedStyle(document.querySelector('.auth-brand')).backgroundColor,
    brandImage: getComputedStyle(document.querySelector('.auth-brand')).backgroundImage,
    page: getComputedStyle(document.body).backgroundColor,
    favicon: document.querySelector('link[rel="icon"][type="image/svg+xml"]')?.getAttribute('href') || '',
    hasForm: !!document.querySelector('form'),
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    toggleX: document.querySelector('#theme-toggle').getBoundingClientRect().x,
    viewport: window.innerWidth,
  }));
  assert.equal(visual.page, 'rgb(246, 243, 233)', `${label}: Dial warm canvas`);
  assert.equal(visual.brand, 'rgb(246, 243, 233)', `${label}: Dial brand surface`);
  assert.equal(visual.brandImage, 'none', `${label}: no upstream blue gradient`);
  assert.match(visual.favicon, /images\/dial-icon\/favicon\.svg/, `${label}: Dial favicon`);
  assert.equal(visual.hasForm, expectedForm, `${label}: form preserved`);
  assert.equal(visual.overflow, false, `${label}: no horizontal overflow`);
  assert(visual.toggleX > visual.viewport - 100, `${label}: theme toggle stays top right`);
  await page.locator('#theme-toggle').click();
  const dark = await page.evaluate(() => {
    const field = document.querySelector('input[type="text"],input[type="password"],select');
    return {
      page: getComputedStyle(document.body).backgroundColor,
      brand: getComputedStyle(document.querySelector('.auth-brand')).backgroundColor,
      brandImage: getComputedStyle(document.querySelector('.auth-brand')).backgroundImage,
      input: field ? getComputedStyle(field).backgroundColor : null,
      icon: getComputedStyle(document.querySelector('#theme-toggle')).color,
    };
  });
  assert.equal(dark.page, 'rgb(28, 28, 26)', `${label}: Dial dark canvas`);
  assert.equal(dark.brand, 'rgb(28, 28, 26)', `${label}: Dial dark brand`);
  assert.equal(dark.brandImage, 'none', `${label}: dark mode keeps flat brand`);
  if (dark.input !== null) assert.equal(dark.input, 'rgb(44, 43, 39)', `${label}: input background changes with text immediately`);
  assert.equal(dark.icon, 'rgb(246, 243, 233)', `${label}: theme toggle remains legible immediately`);
  if (label === 'registration-first-run') {
    await page.screenshot({ path: path.join(evidence, 'registration-dark.png'), fullPage: true });
  }
  await page.locator('#theme-toggle').click();
  console.log(`PASS ${label} auth surface`);
}

let browser;
let base;
try {
  docker('run', '-d', '--rm', '--name', name, '-e', 'TZ=UTC',
    '-e', `PUID=${hostUid}`, '-e', `PGID=${hostGid}`, '-p', '127.0.0.1::80',
    '-v', `${dbDir}:/var/www/html/db`, '-v', `${logoDir}:/var/www/html/images/uploads/logos`, image);
  const port = docker('port', name, '80/tcp').split(':').at(-1);
  base = `http://127.0.0.1:${port}`;
  let healthy = false;
  for (let attempt = 0; attempt < 60; attempt++) {
    try { healthy = (await fetch(`${base}/health.php`)).ok; } catch { /* startup */ }
    if (healthy) break;
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  assert(healthy, 'isolated Wallos did not become healthy');
  // The upstream /health.php can answer before its first-run migrations finish.
  let migrated = false;
  for (let attempt = 0; attempt < 60; attempt++) {
    // Opening a nonexistent file with sqlite3 creates an empty database and
    // races Wallos's own first-run bootstrap. Wait for its file first.
    try {
      if (fs.existsSync(path.join(dbDir, 'wallos.db'))
          && fs.statSync(path.join(dbDir, 'wallos.db')).size >= 32768) {
        migrated = sql("SELECT COUNT(*) FROM migrations WHERE migration='migrations/dial_001_icon_library.php';") === '1';
      }
    }
    catch { /* database still initializing */ }
    if (migrated) break;
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  assert(migrated, 'first-run migrations were not complete');

  const channel = process.env.DIAL_BROWSER_CHANNEL || (process.platform === 'darwin' ? 'chrome' : 'chromium');
  const browserOptions = { headless: true };
  if (channel !== 'chromium') browserOptions.channel = channel;
  browser = await chromium.launch(browserOptions);
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));

  await authSurface(page, 'registration.php', 'registration-first-run', true);
  await page.locator('#username').fill('dial_auth_qa');
  await page.locator('#firstname').fill('Demo');
  await page.locator('#lastname').fill('Installer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#confirm_password').fill(password);
  await page.locator('#currency').selectOption('USD');
  await page.locator('input[type="submit"]').click();
  await page.waitForURL(/\/login\.php\?registered=true/, { timeout: 20000 });
  assert.equal(sql('SELECT COUNT(*) FROM user;'), '1', 'registration persisted one account');
  console.log('PASS first-run registration');

  await authSurface(page, 'login.php', 'login', true);
  await authSurface(page, 'verifyemail.php?token=invalid', 'verifyemail-invalid', false);

  sql(`UPDATE admin SET smtp_address='127.0.0.1', server_url='${base}';`);
  await authSurface(page, 'passwordreset.php', 'passwordreset-request', true);

  sql(`UPDATE user SET totp_enabled=1 WHERE username='dial_auth_qa';`);
  sql(`INSERT INTO totp (user_id,totp_secret,backup_codes) VALUES (1,'${secret}','[]');`);
  await page.goto(`${base}/login.php`, { waitUntil: 'load' });
  await page.locator('#username').fill('dial_auth_qa');
  await page.locator('#password').fill(password);
  await page.locator('input[type="submit"]').click();
  await page.waitForURL(/\/totp\.php/, { timeout: 20000 });
  await authSurface(page, 'totp.php', 'totp-challenge', true);
  await page.locator('#one-time-code').fill(totp(secret));
  await page.locator('input[type="submit"]').click();
  try {
    await page.waitForURL(url => url.pathname === '/' || url.pathname === '/subscriptions.php', { timeout: 20000 });
  } catch (error) {
    await page.screenshot({ path: path.join(evidence, 'totp-after-submit.png') });
    throw new Error(`TOTP login stayed at ${page.url()}: ${JSON.stringify(await page.locator('.error-box').allTextContents())}`, { cause: error });
  }
  assert.equal(errors.length, 0, `browser errors: ${errors.join('; ')}`);
  assert(await page.locator('.dial-site-header').count() > 0, 'authenticated Dial header visible');
  console.log('PASS TOTP to authenticated Dial page');
  if (new URL(page.url()).pathname === '/subscriptions.php') {
    assert.equal(await page.locator('img[src*="images/siteimages/empty.png"]').count(), 0,
      'fresh account keeps the Dial empty-state design');
    assert(await page.locator('.dial-empty-page').isVisible(), 'Dial empty state visible');
    assert(await page.getByText('Add first subscription', { exact: true }).isVisible(),
      'add-first-subscription control remains available');
    assert(await page.getByText('Manage categories', { exact: true }).isVisible(),
      'category manager remains available from the empty state');
    await page.screenshot({ path: path.join(evidence, 'fresh-empty-ledger.png'), fullPage: true });
    console.log('PASS fresh ledger empty-state actions');

    await page.getByTestId('dial-category-manage-link').click();
    await page.getByTestId('dial-category-manager').waitFor({ state: 'visible' });
    await page.getByTestId('dial-category-new-name').fill('QA Infrastructure');
    await Promise.all([
      page.waitForEvent('load', { timeout: 20000 }),
      page.getByTestId('dial-category-add').click(),
    ]);
    assert.equal(await page.getByTestId('dial-category-row').filter({
      has: page.locator('input[value="QA Infrastructure"]'),
    }).count(), 1, 'fresh account can create a category');
    await page.screenshot({ path: path.join(evidence, 'fresh-category-manager.png'), fullPage: true });
    console.log('PASS fresh category manager');

    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    await page.evaluate(() => addSubscription());
    await page.locator('#name').fill('Synthetic installer subscription');
    await page.locator('#price').fill('12.50');
    await page.locator('#next_payment').fill(new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10));
    await page.locator('#category').selectOption({ label: 'QA Infrastructure' });
    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-catalog').click();
    await page.getByTestId('dial-icon-catalog-vmiss').click();
    await page.getByTestId('dial-icon-presentation-framed').click();
    await page.getByTestId('dial-icon-save').click();
    const addResponse = page.waitForResponse(response => response.url().includes('/endpoints/subscription/add.php'));
    await page.locator('#save-button').click();
    const added = await addResponse;
    const payload = await added.json();
    assert(added.ok() && payload.status === 'Success', `fresh subscription add: ${JSON.stringify(payload)}`);
    await page.reload({ waitUntil: 'load' });
    const card = page.getByTestId(/^dial-subscription-\d+$/)
      .filter({ hasText: 'Synthetic installer subscription' }).first();
    await card.waitFor({ state: 'visible' });
    assert.equal(await card.locator('[data-dial-icon-kind="catalog"][data-dial-icon-presentation="framed"]').count(), 1,
      'new subscription kept its catalog icon style');
    console.log('PASS fresh subscription with catalog icon style');

    const subscriptionId = (await card.getAttribute('data-testid')).split('-').at(-1);
    await card.hover();
    await page.getByTestId(`dial-icon-change-${subscriptionId}`).click();
    await page.getByTestId('dial-icon-source-upload').click();
    await page.getByTestId('dial-icon-upload').setInputFiles(path.join(root, 'theme/images/dial-icon/favicon-32.png'));
    await page.locator('#dial-icon-upload-label').fill('Synthetic installer icon');
    await page.locator('#dial-icon-upload-action').click();
    await page.getByTestId(/^dial-icon-library-\d+$/).first().waitFor({ state: 'visible' });
    const iconResponse = page.waitForResponse(response => response.url().includes('/endpoints/dial/subscription_icon.php'));
    await page.getByTestId('dial-icon-save').click();
    const iconSaved = await iconResponse;
    assert(iconSaved.ok() && (await iconSaved.json()).success === true, 'My Icons selection saved');
    await page.reload({ waitUntil: 'load' });
    assert.equal(await card.locator('[data-dial-icon-kind="asset"]').count(), 1,
      'personal icon persists after reload');
    console.log('PASS fresh My Icons upload and selection');
  }
} finally {
  if (browser) await browser.close();
  try { docker('stop', name); } catch { /* failed startup */ }
  fs.rmSync(fixture, { recursive: true, force: true });
}
