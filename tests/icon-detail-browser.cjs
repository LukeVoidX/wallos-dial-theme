#!/usr/bin/env node
// The same owned icon descriptor must render in list, dashboard and calendar detail dialogs.
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:detail';
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-icon-detail.'));
const fixturePath = path.join(scratch, 'fixture.json');
const service = spawn('bash', ['tests/run-icon-http.sh', image, '--serve', '--fixture-out', fixturePath],
  { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
let serviceLog = '';
for (const stream of [service.stdout, service.stderr]) stream.on('data', data => { serviceLog += data.toString(); });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function fixture() {
  for (let i = 0; i < 50; i++) {
    if (fs.existsSync(fixturePath)) return JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
    if (service.exitCode !== null) throw new Error('Fixture exited: ' + serviceLog);
    await pause(1000);
  }
  throw new Error('Fixture timeout: ' + serviceLog);
}
(async () => {
  let context;
  try {
    const { base_url: base, database } = await fixture();
    execFileSync('python3', ['tests/icon-detail-http.py', '--base-url', base, '--database', database],
      { cwd: root, stdio: 'pipe' });
    const id = Number(execFileSync('python3', ['-c',
      'import sqlite3,sys; print(sqlite3.connect(sys.argv[1]).execute("SELECT id FROM subscriptions WHERE name=?",("Cinema Club",)).fetchone()[0])',
      database], { encoding: 'utf8' }).trim());
    context = await chromium.launchPersistentContext(path.join(scratch, 'profile'), {
      channel: 'chrome', headless: true, serviceWorkers: 'allow', viewport: { width: 1280, height: 800 },
    });
    const response = await context.request.post(`${base}/login.php`, {
      form: { username: 'demo', password: 'dial-fixture-only' },
    });
    if (!response.ok()) throw new Error(`Login failed: ${response.status()}`);
    const page = context.pages()[0] || await context.newPage();
    const checked = [];
    for (const route of ['subscriptions.php', '', 'calendar.php']) {
      await page.goto(`${base}/${route}`, { waitUntil: 'load' });
      if (route === 'subscriptions.php') {
        await page.locator(`.subscription[data-id="${id}"] .subscription-main`).click();
      } else {
        await page.evaluate(subscriptionId => showSubscriptionDetails(null, subscriptionId), id);
      }
      const icon = page.locator('#details-logo [data-dial-icon-kind="catalog"]');
      await icon.waitFor({ timeout: 10000 });
      const state = await icon.evaluate(element => ({
        presentation: element.dataset.dialIconPresentation,
        treatment: element.dataset.dialIconTreatment,
        scale: element.dataset.dialIconScale,
        source: element.querySelector('img')?.getAttribute('src'),
        width: element.querySelector('img')?.naturalWidth,
      }));
      if (state.presentation !== 'framed' || state.treatment !== 'mono' || state.scale !== '110' ||
          state.source !== 'images/dial-catalog/chatgpt.svg' || state.width <= 0) {
        throw new Error(`${route || 'index'} detail icon mismatch: ${JSON.stringify(state)}`);
      }
      const title = await page.locator('#details-name').textContent();
      if (title !== 'Cinema Club') throw new Error(`Adjacent service name missing: ${title}`);
      checked.push(route || 'index.php');
    }
    process.stdout.write(JSON.stringify({ result: 'passed', routes: checked }) + '\n');
  } finally {
    if (context) await context.close();
    service.kill('SIGTERM');
    for (let i = 0; i < 20 && service.exitCode === null; i++) await pause(100);
    if (service.exitCode === null) service.kill('SIGKILL');
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
