#!/usr/bin/env node
// Integrated Wallos Dial browser journey. Every account and subscription is fictional.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const output = path.resolve(process.argv[3] || 'data/qa-1.0');
const owner = fixture.accounts?.find(account => account.role === 'owner');
assert(owner?.username && owner?.password, 'fixture has no owner credentials');
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(fixture.base_url), 'fixture must bind to localhost');
assert(fixture.database?.includes('/data/.dial-icon-http.'), 'fixture database must be disposable');
fs.mkdirSync(output, { recursive: true });

const profile = fs.mkdtempSync(path.join(output, '.chromium-profile-'));
const browserChannel = process.env.DIAL_BROWSER_CHANNEL || (os.platform() === 'darwin' ? 'chrome' : 'chromium');
const browserOptions = {
  headless: true, serviceWorkers: 'allow', viewport: { width: 1440, height: 900 },
};
if (browserChannel !== 'chromium') browserOptions.channel = browserChannel;
const base = fixture.base_url;
const report = {
  image: process.env.DIAL_QA_IMAGE || 'local-image',
  source: 'isolated synthetic fixture',
  browser: browserChannel,
  languages: [], widths: [], zoom: [], routes: [], screenshots: [], steps: [],
  errors: [],
};
let context;
let page;
let demoName;

async function step(name, work) {
  try {
    const result = await work();
    report.steps.push({ name, result: 'passed' });
    process.stdout.write(`PASS ${name}\n`);
    return result;
  } catch (error) {
    throw new Error(`${name}: ${error.message}`, { cause: error });
  }
}

async function capture(label, fullPage = true) {
  const filename = `${label}.png`;
  await page.screenshot({ path: path.join(output, filename), fullPage, animations: 'disabled' });
  report.screenshots.push(filename);
}

async function assertNoOverflow(label) {
  const bounds = await page.evaluate(() => ({
    viewport: window.innerWidth,
    scroll: document.documentElement.scrollWidth,
    client: document.documentElement.clientWidth,
  }));
  assert(bounds.scroll <= bounds.client + 1, `${label}: horizontal overflow ${JSON.stringify(bounds)}`);
}

async function setLanguage(language) {
  if (await page.locator('#dial-language').inputValue() !== language) {
    await Promise.all([
      page.waitForEvent('load', { timeout: 20000 }),
      page.locator('#dial-language').selectOption(language),
    ]);
  }
  assert.equal(await page.locator('#dial-language').inputValue(), language);
  report.languages.push(language);
}

const cardFor = () => page.getByTestId(/^dial-subscription-\d+$/)
  .filter({ hasText: 'Synthetic QA subscription' }).first();

async function cardId() {
  const id = (await cardFor().getAttribute('data-testid'))?.split('-').at(-1);
  assert.match(id || '', /^[1-9]\d*$/);
  return id;
}

async function quickOpen(id) {
  await cardFor().hover();
  await page.getByTestId(`dial-icon-change-${id}`).click();
  await page.getByTestId('dial-icon-chooser').waitFor({ state: 'visible' });
}

async function quickSave(id, kind) {
  const response = page.waitForResponse(response => response.url().includes('/endpoints/dial/subscription_icon.php'));
  await page.getByTestId('dial-icon-save').click();
  const saved = await response;
  const payload = await saved.json();
  assert(saved.ok() && payload.success === true, `quick icon save: ${saved.status()} ${JSON.stringify(payload)}`);
  await page.waitForFunction(({ id, kind }) => {
    const card = document.querySelector(`[data-testid="dial-subscription-${id}"]`);
    return !!card?.querySelector(`[data-dial-icon-kind="${kind}"]`);
  }, { id, kind });
}

async function runDemoReadOnly() {
  const dbDir = path.dirname(fixture.database);
  const logosDir = path.resolve(dbDir, '..', 'logos');
  demoName = `dial-release-demo-${process.pid}`;
  execFileSync('docker', ['run', '-d', '--name', demoName, '-e', 'DEMO_MODE=1',
    '-p', '127.0.0.1::80', '-v', `${dbDir}:/var/www/html/db`,
    '-v', `${logosDir}:/var/www/html/images/uploads/logos`, report.image],
  { stdio: 'ignore' });
  const address = execFileSync('docker', ['port', demoName, '80/tcp'], { encoding: 'utf8' }).trim();
  const demoBase = `http://127.0.0.1:${address.split(':').at(-1)}`;
  for (let attempt = 0; attempt < 45; attempt++) {
    try { if ((await fetch(`${demoBase}/health.php`)).ok) break; } catch (_) { /* startup */ }
    if (attempt === 44) throw new Error('Demo fixture did not start');
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  const demoLogin = await context.request.post(`${demoBase}/login.php`, {
    form: { username: owner.username, password: owner.password },
  });
  assert(demoLogin.ok(), `Demo login: ${demoLogin.status()}`);
  const demoPage = await context.newPage();
  try {
    await demoPage.goto(`${demoBase}/categories.php`, { waitUntil: 'load' });
    assert(await demoPage.getByTestId('dial-category-readonly').isVisible());
    assert.equal(await demoPage.getByTestId('dial-category-add').count(), 0);
    await demoPage.goto(`${demoBase}/subscriptions.php`, { waitUntil: 'load' });
    await demoPage.evaluate(() => addSubscription());
    await demoPage.getByTestId('dial-icon-open-form').click();
    assert(await demoPage.getByTestId('dial-icon-save').isDisabled());
    assert(await demoPage.getByTestId('dial-icon-upload').isDisabled());
    assert(await demoPage.locator('.dial-icon-demo-note').isVisible());
  } finally {
    await demoPage.close();
  }
}

try {
  context = await chromium.launchPersistentContext(profile, browserOptions);
  const login = await context.request.post(`${base}/login.php`, {
    form: { username: owner.username, password: owner.password },
  });
  assert(login.ok(), `owner login: ${login.status()}`);
  page = context.pages()[0] || await context.newPage();
  page.on('pageerror', error => report.errors.push(error.message));

  await step('clean 30-record showcase captures', async () => {
    await page.goto(`${base}/`, { waitUntil: 'load' });
    await capture('showcase-overview-en-1440-100');
    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    assert.equal(await page.getByTestId(/^dial-subscription-\d+$/).count(), 30);
    await capture('showcase-subscriptions-en-1440-100');
    await page.locator('#view-grid-button').click();
    await page.waitForFunction(() => document.querySelector('#subscriptions')?.classList.contains('grid-view'));
    await capture('showcase-subscriptions-grid-en-1440-100');
    await page.locator('#view-list-button').click();
    await page.waitForFunction(() => !document.querySelector('#subscriptions')?.classList.contains('grid-view'));
    await page.evaluate(() => addSubscription());
    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-chooser').waitFor({ state: 'visible' });
    await page.getByTestId('dial-icon-source-catalog').click();
    await page.getByTestId('dial-icon-catalog-chatgpt').click();
    await page.waitForFunction(() => {
      const images = [...document.querySelectorAll('#dial-icon-chooser .dial-icon-preview-art img')];
      return images.length === 2 && images.every(image => image.complete && image.naturalWidth > 0);
    }, null, { timeout: 10000 });
    await capture('showcase-icon-chooser-en-1440-100', false);
    await page.goto(`${base}/categories.php`, { waitUntil: 'load' });
    // The manager also shows Wallos's built-in uncategorized sentinel.
    assert.equal(await page.getByTestId('dial-category-row').count(), 5);
    await capture('showcase-categories-en-1440-100');
    await page.goto(`${base}/stats.php`, { waitUntil: 'load' });
    await page.waitForFunction(() => document.querySelectorAll('.apexcharts-canvas').length >= 3,
      null, { timeout: 10000 });
    await page.waitForTimeout(2000);
    await capture('showcase-stats-en-1440-100');
    report.cleanShowcase = { subscriptions: 30, populatedCategories: 4,
      uncategorizedSentinel: 1, data: 'synthetic only' };
  });

  await step('category manager and browser Back refresh', async () => {
    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    const manage = page.getByTestId('dial-category-manage-link').first();
    assert(await manage.isVisible());
    await manage.click();
    assert(await page.getByTestId('dial-category-manager').isVisible());
    await page.getByTestId('dial-category-new-name').fill('AI & Tools');
    await Promise.all([
      page.waitForEvent('load', { timeout: 20000 }),
      page.getByTestId('dial-category-add').click(),
    ]);
    assert.equal(await page.getByTestId('dial-category-row').filter({
      has: page.locator('input[value="AI & Tools"]'),
    }).count(), 1);
    await capture('categories-en-1440-100');
    await page.goBack({ waitUntil: 'load' });
    await page.waitForFunction(() => [...document.querySelectorAll('#category option')]
      .some(option => option.textContent.trim() === 'AI & Tools'));
    assert.equal(await page.locator('#category option').filter({ hasText: 'AI & Tools' }).count(), 1);
    assert(await page.locator('#subscriptions').isVisible());
  });

  await step('add subscription with catalog icon', async () => {
    await page.evaluate(() => addSubscription());
    await page.locator('#name').fill('Synthetic QA subscription');
    await page.locator('#price').fill('12.50');
    const due = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    await page.locator('#next_payment').fill(due);
    await page.locator('#category').selectOption({ label: 'AI & Tools' });
    await page.getByTestId('dial-icon-open-form').click();
    assert(await page.getByTestId('dial-icon-chooser').isVisible());
    await page.getByTestId('dial-icon-source-catalog').click();
    await page.getByTestId('dial-icon-catalog-chatgpt').click();
    assert(await page.getByTestId('dial-icon-preview').isVisible());
    await capture('chooser-en-1440-100', false);
    await page.getByTestId('dial-icon-save').click();
    const response = page.waitForResponse(response => response.url().includes('/endpoints/subscription/add.php'));
    await page.locator('#save-button').click();
    const saved = await response;
    const addResult = await saved.json();
    assert(saved.ok() && addResult.status === 'Success',
      `add subscription: ${saved.status()} ${JSON.stringify(addResult)}`);
    await cardFor().waitFor({ state: 'visible' });
    assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
    await page.reload({ waitUntil: 'load' });
    assert(await cardFor().isVisible());
    assert.equal(await cardFor().locator('img[src*="dial-catalog/chatgpt.svg"]').count(), 1);
  });

  const id = await cardId();
  await step('list, grid, edit, detail and dashboard icon', async () => {
    await page.locator('#view-list-button').click();
    await page.waitForFunction(() => !document.querySelector('#subscriptions').classList.contains('grid-view'));
    assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
    await capture('subscriptions-list-en-1440-100');
    await page.locator('#view-grid-button').click();
    await page.waitForFunction(() => document.querySelector('#subscriptions').classList.contains('grid-view'));
    assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 1);
    await capture('subscriptions-grid-en-1440-100');

    await page.evaluate(subscriptionId => openEditSubscription({ stopPropagation() {} }, subscriptionId), Number(id));
    await page.waitForFunction(subscriptionId => document.querySelector('#id')?.value === subscriptionId,
      id);
    await page.getByTestId('dial-icon-open-form').click();
    await page.getByTestId('dial-icon-source-catalog').click();
    await page.getByTestId('dial-icon-catalog-claude').click();
    await page.getByTestId('dial-icon-save').click();
    // Wallos 5.8.3 uses add.php for both create and update (the hidden id selects update).
    const response = page.waitForResponse(response => response.url().includes('/endpoints/subscription/add.php'));
    await page.locator('#save-button').click();
    const edited = await response;
    const editResult = await edited.json();
    assert(edited.ok() && editResult.status === 'Success',
      `edit subscription: ${edited.status()} ${JSON.stringify(editResult)}`);
    await page.waitForFunction(subscriptionId => !!document.querySelector(
      `[data-testid="dial-subscription-${subscriptionId}"] img[src*="dial-catalog/claude.svg"]`), id);
    // Wallos stores selected names with one HTML entity layer. All four detail
    // lookups must display human text after the page reloads its server JSON.
    execFileSync('python3', ['-c', `
import sqlite3,sys
db=sqlite3.connect(sys.argv[1]); sid=int(sys.argv[2])
owner=db.execute('SELECT user_id FROM subscriptions WHERE id=?',(sid,)).fetchone()[0]
member=db.execute('SELECT id FROM household WHERE user_id=? ORDER BY id LIMIT 1',(owner,)).fetchone()[0]
payment=db.execute('SELECT id FROM payment_methods WHERE user_id=? ORDER BY id LIMIT 1',(owner,)).fetchone()[0]
replacement=db.execute('SELECT id FROM subscriptions WHERE user_id=? AND id<>? ORDER BY id LIMIT 1',(owner,sid)).fetchone()[0]
db.execute("UPDATE household SET name='Demo &amp; Member' WHERE id=? AND user_id=?",(member,owner))
db.execute("UPDATE payment_methods SET name='Card &amp; Credits' WHERE id=? AND user_id=?",(payment,owner))
db.execute("UPDATE subscriptions SET name='Replacement &amp; Plan' WHERE id=? AND user_id=?",(replacement,owner))
db.execute('UPDATE subscriptions SET payer_user_id=?,payment_method_id=?,replacement_subscription_id=? WHERE id=? AND user_id=?',(member,payment,replacement,sid,owner))
db.commit();db.close()
`, fixture.database, id]);
    await page.reload({ waitUntil: 'load' });
    assert.equal(await cardFor().locator('img[src*="dial-catalog/claude.svg"]').count(), 1);

    await cardFor().locator('.subscription-main').click();
    await page.locator('#details-logo [data-dial-icon-kind="catalog"]').waitFor();
    assert.equal(await page.locator('#details-name').textContent(), 'Synthetic QA subscription');
    assert.equal(await page.locator('#details-category').textContent(), 'AI & Tools',
      'Detail category must decode one stored HTML entity layer');
    assert.equal(await page.locator('#details-payer').textContent(), 'Demo & Member');
    assert.equal(await page.locator('#details-payment-name').textContent(), 'Card & Credits');
    assert.equal(await page.locator('#details-replacement').textContent(), 'Replacement & Plan');
    await capture('subscription-detail-en-1440-100', false);
    await page.evaluate(() => closeSubscriptionDetails());

    await page.goto(`${base}/`, { waitUntil: 'load' });
    const payment = page.locator('.dial-payment-row').filter({ hasText: 'Synthetic QA subscription' });
    await payment.waitFor({ state: 'visible' });
    assert.equal(await payment.locator('[data-dial-icon-kind="catalog"]').count(), 1);
    await capture('overview-en-1440-100');
    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
  });

  await step('quick Change styling and My Icons upload', async () => {
    await quickOpen(id);
    await page.getByTestId('dial-icon-presentation-framed').click();
    await page.getByTestId('dial-icon-treatment-mono').click();
    await page.getByTestId('dial-icon-scale').evaluate(input => {
      input.value = '120';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    assert.equal(await page.getByTestId('dial-icon-presentation-framed').getAttribute('aria-pressed'), 'true');
    await quickSave(id, 'catalog');
    await page.reload({ waitUntil: 'load' });
    assert.equal(await cardFor().locator('[data-dial-icon-presentation="framed"]' +
      '[data-dial-icon-treatment="mono"][data-dial-icon-scale="120"]').count(), 1);

    await quickOpen(id);
    await page.getByTestId('dial-icon-source-upload').click();
    await page.getByTestId('dial-icon-upload').setInputFiles('theme/images/dial-icon/favicon-32.png');
    await page.locator('#dial-icon-upload-label').fill('Synthetic QA mark');
    await page.locator('#dial-icon-upload-action').click();
    const libraryChoice = page.getByTestId(/^dial-icon-library-\d+$/).first();
    await libraryChoice.waitFor({ state: 'visible' });
    assert.equal(await libraryChoice.getAttribute('aria-pressed'), 'true');
    await capture('chooser-my-icons-en-1440-100', false);
    await quickSave(id, 'asset');
    await page.reload({ waitUntil: 'load' });
    assert.equal(await cardFor().locator('img[src*="/endpoints/dial/icon.php?id="]').count(), 1);
  });

  await step('Original restores source, keyboard Escape returns focus', async () => {
    await quickOpen(id);
    await page.getByTestId('dial-icon-source-original').click();
    await quickSave(id, 'fallback');
    await page.reload({ waitUntil: 'load' });
    assert.equal(await cardFor().locator('[data-dial-icon-kind="asset"]').count(), 0);
    assert.equal(await cardFor().locator('[data-dial-icon-kind="catalog"]').count(), 0);
    const change = page.getByTestId(`dial-icon-change-${id}`);
    await change.focus();
    await page.keyboard.press('Enter');
    await page.getByTestId('dial-icon-chooser').waitFor({ state: 'visible' });
    await page.keyboard.press('Escape');
    assert.equal(await change.evaluate(node => node === document.activeElement), true);
  });

  await step('three languages, desktop widths, zoom and remaining routes', async () => {
    for (const language of ['en', 'zh_cn', 'zh_tw']) {
      await setLanguage(language);
      assert(await cardFor().isVisible());
      await capture(`subscriptions-${language}-1440-100`);
      await page.goto(`${base}/categories.php`, { waitUntil: 'load' });
      assert(await page.getByTestId('dial-category-manager').isVisible());
      await capture(`categories-${language}-1440-100`);
      await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    }
    for (const width of [900, 1440, 1920]) {
      await page.setViewportSize({ width, height: 900 });
      await assertNoOverflow(`subscriptions ${width}px`);
      report.widths.push(width);
      await capture(`subscriptions-zh_tw-${width}-100`);
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    // CSS zoom is a repeatable automated layout approximation; manual browser
    // chrome zoom still belongs in the final visual review.
    for (const percent of [80, 100, 125]) {
      await page.evaluate(value => { document.documentElement.style.zoom = `${value}%`; }, percent);
      await assertNoOverflow(`subscriptions CSS zoom ${percent}%`);
      report.zoom.push({ percent, method: 'CSS zoom approximation' });
      await capture(`subscriptions-zh_tw-1440-${percent}`);
    }
    await page.evaluate(() => { document.documentElement.style.zoom = ''; });
    for (const [name, route] of [
      ['overview', '/'], ['calendar', '/calendar.php'], ['stats', '/stats.php'],
    ]) {
      await page.goto(`${base}${route}`, { waitUntil: 'load' });
      if (name === 'stats') {
        // ApexCharts renders after window.onload, then animates paths. A load
        // event alone captures empty axes and a partial donut.
        await page.waitForFunction(() => document.querySelectorAll('.apexcharts-canvas').length >= 3,
          null, { timeout: 10000 });
        await page.waitForTimeout(2000);
        const chartState = await page.evaluate(() => ({
          canvases: document.querySelectorAll('.apexcharts-canvas').length,
          donutWidth: document.querySelector('#cycleSplitChart .apexcharts-pie-series')
            ?.getBoundingClientRect().width || 0,
          projectionBarHeights: [...document.querySelectorAll('#projectionChart .apexcharts-bar-area')]
            .map(bar => bar.getBoundingClientRect().height),
        }));
        assert(chartState.donutWidth > 200 && chartState.projectionBarHeights.length >= 10 &&
          chartState.projectionBarHeights.filter(height => height > 40).length >= 8,
        `statistics charts did not finish rendering: ${JSON.stringify(chartState)}`);
        report.statsChartWait = { settleMs: 2000, ...chartState };
      }
      await assertNoOverflow(name);
      report.routes.push(route);
      await capture(`${name}-zh_tw-1440-100`);
    }
  });

  await step('stored names render once across desktop views', async () => {
    // Wallos validate() stores one HTML-entity layer. Exercise actual browser
    // text and attribute values after that layer passes through every view.
    execFileSync('python3', ['-c', `
import sqlite3,sys
from datetime import date,timedelta
db=sqlite3.connect(sys.argv[1])
db.execute("UPDATE subscriptions SET name='Cinema &amp; &quot;Club&quot; &lt;tag&gt;', price=9999.99, next_payment=?,auto_renew=1 WHERE user_id=1 AND name='Cinema Club'",(date.today().isoformat(),))
db.execute("UPDATE subscriptions SET name='Northstar &amp; &#039;VPS&#039;',next_payment=?,auto_renew=0,start_date='2010-01-01' WHERE user_id=1 AND name='Northstar VPS'",((date.today()-timedelta(days=1)).isoformat(),))
db.execute("UPDATE subscriptions SET name='Orbit &amp; Models',price=0.01,cancellation_date=? WHERE user_id=1 AND name='Orbit Models'",((date.today()+timedelta(days=2)).isoformat(),))
db.execute("UPDATE payment_methods SET name='Card &amp; Credits' WHERE user_id=1")
db.commit();db.close()
`, fixture.database]);

    await page.goto(`${base}/`, { waitUntil: 'load' });
    const upcomingName = page.locator('.dial-payment-row .dial-service-name')
      .filter({ hasText: 'Cinema' }).first();
    assert.equal(await upcomingName.textContent(), 'Cinema & "Club" <tag>');
    assert.equal(await page.locator('.overdue-subscriptions .subscription-item-title')
      .filter({ hasText: 'Northstar' }).first().textContent(), "Northstar & 'VPS'");
    assert.equal(await page.locator('.cancellation-subscriptions .subscription-item-title')
      .filter({ hasText: 'Orbit' }).first().textContent(), 'Orbit & Models');

    await page.goto(`${base}/calendar.php`, { waitUntil: 'load' });
    const calendarEvent = page.locator('.calendar-event')
      .filter({ has: page.locator('.calendar-event-name', { hasText: 'Cinema' }) }).first();
    assert.equal(await calendarEvent.locator('.calendar-event-name').textContent(), 'Cinema & "Club" <tag>');
    assert((await calendarEvent.getAttribute('aria-label')).includes('Cinema & "Club" <tag>'));
    assert((await calendarEvent.getAttribute('data-detail')).includes('Cinema & "Club" <tag>'));

    await page.goto(`${base}/stats.php`, { waitUntil: 'load' });
    const statNames = await page.locator('.statistic .subtitle').allTextContents();
    assert(statNames.includes('Cinema & "Club" <tag>'), `Expensive stat name: ${JSON.stringify(statNames)}`);
    assert(statNames.includes('Orbit & Models'), `Cheapest stat name: ${JSON.stringify(statNames)}`);
    assert(statNames.includes("Northstar & 'VPS'"), `Oldest stat name: ${JSON.stringify(statNames)}`);

    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    await page.evaluate(() => addSubscription());
    const replacementOption = await page.locator('#replacement_subscription_id option')
      .filter({ hasText: 'Cinema' }).first().textContent();
    assert.equal(replacementOption.trim(), 'Cinema & "Club" <tag>');
    await page.evaluate(() => closeAddSubscription());
    const paymentTooltip = await page.locator('.payment_method[title*="Card"]')
      .first().getAttribute('title');
    assert(paymentTooltip.includes('Card & Credits'), `Payment tooltip: ${paymentTooltip}`);
  });

  await step('Demo UI denies category and icon writes', runDemoReadOnly);
  assert.deepEqual(report.errors, [], `browser script errors: ${JSON.stringify(report.errors)}`);
  fs.rmSync(path.join(output, 'qa-failure.json'), { force: true });
  fs.writeFileSync(path.join(output, 'qa-manifest.json'), JSON.stringify(report, null, 2) + '\n');
  process.stdout.write(JSON.stringify({ result: 'passed', image: report.image,
    steps: report.steps.length, languages: report.languages,
    widths: report.widths, screenshots: report.screenshots.length }) + '\n');
} catch (error) {
  if (page && !page.isClosed()) {
    try { await capture('failure-current-view'); } catch (_) { /* diagnostic best effort */ }
  }
  fs.writeFileSync(path.join(output, 'qa-failure.json'), JSON.stringify({
    step: error.message, browserErrors: report.errors, url: page?.url() || '',
  }, null, 2) + '\n');
  console.error(error);
  process.exitCode = 1;
} finally {
  if (demoName) {
    try { execFileSync('docker', ['rm', '-f', demoName], { stdio: 'ignore' }); }
    catch (_) { /* already gone */ }
  }
  if (context) await context.close();
  fs.rmSync(profile, { recursive: true, force: true });
}
