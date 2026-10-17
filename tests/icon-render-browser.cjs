#!/usr/bin/env node
// Isolated visual/asset QA for server-rendered Dial icons with fictional data.
'use strict';
const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:compat';
const port = process.env.DIAL_ICON_BROWSER_PORT || '43185';
const origin = `http://127.0.0.1:${port}`;
const name = `dial-icon-render-browser-${process.pid}`;
const reportDir = process.env.DIAL_ICON_QA_DIR || path.join(root, '.superpowers/sdd/2026-10-17-wallos-dial-icon-system/screenshots');
fs.mkdirSync(reportDir, { recursive: true });
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-icon-browser.'));
fs.mkdirSync(`${scratch}/db`);
fs.mkdirSync(`${scratch}/logos`);
const run = (cmd, args) => execFileSync(cmd, args, { cwd: root, encoding: 'utf8' }).trim();
const docker = (...args) => run('docker', args);
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const logs = () => { const r = spawnSync('docker', ['logs', name], { encoding: 'utf8' }); return `${r.stdout || ''}${r.stderr || ''}`; };
function stop() { try { docker('rm', '-f', name); } catch (_) { /* already stopped */ } }
async function start() {
  docker('run', '-d', '--name', name, '-e', 'DEMO_MODE=0', '-p', `127.0.0.1:${port}:80`,
    '-v', `${scratch}/db:/var/www/html/db`,
    '-v', `${scratch}/logos:/var/www/html/images/uploads/logos`, image);
  for (let i = 0; i < 45; i++) {
    if (logs().includes('Updated next payment dates')) {
      try { if ((await fetch(`${origin}/health.php`)).ok) return; } catch (_) { /* wait */ }
    }
    await pause(1000);
  }
  throw new Error(`Synthetic image failed to start: ${logs()}`);
}
(async () => {
  let context;
  try {
    await start();
    docker('exec', name, 'sh', '-c', 'chmod a+rwX /var/www/html/db /var/www/html/db/wallos.db /var/www/html/images/uploads/logos');
    stop();
    run('python3', ['demo/seed.py', `${scratch}/db/wallos.db`, `${scratch}/logos`]);
    run('python3', ['-c', `import sqlite3,sys
db=sqlite3.connect(sys.argv[1])
for name in ('Cinema Club','Northstar VPS','Orbit Models'):
 id=db.execute('SELECT id FROM subscriptions WHERE user_id=1 AND name=?',(name,)).fetchone()[0]
 db.execute("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,catalog_key,presentation,treatment,scale) VALUES(1,?,'catalog','chatgpt','framed','mono',110)",(id,))
db.commit();db.close()`, `${scratch}/db/wallos.db`]);
    await start();
    run('python3', ['-c', `import sqlite3,sys
from datetime import date,timedelta
db=sqlite3.connect(sys.argv[1])
db.execute("UPDATE subscriptions SET auto_renew=0,next_payment=? WHERE name='Northstar VPS'",((date.today()-timedelta(days=1)).isoformat(),))
db.execute("UPDATE subscriptions SET cancellation_date=? WHERE name='Orbit Models'",((date.today()+timedelta(days=2)).isoformat(),))
db.commit();db.close()`, `${scratch}/db/wallos.db`]);
    context = await chromium.launchPersistentContext(`${scratch}/chrome-profile`, {
      channel: 'chrome', headless: true, serviceWorkers: 'allow', viewport: { width: 1280, height: 800 },
    });
    const page = context.pages()[0] || await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${origin}/`, { waitUntil: 'load' });
    await page.goto(`${origin}/`, { waitUntil: 'load' });
    await page.locator('.dial-payment-row').first().waitFor();
    if (await page.locator('[data-dial-icon-kind="catalog"]').count() < 1) throw new Error('Dashboard icons absent');
    const summarySections = {
      overdue: await page.locator('.overdue-subscriptions .dial-summary-service [data-dial-icon-kind="catalog"]').count(),
      cancellation: await page.locator('.cancellation-subscriptions .dial-summary-service [data-dial-icon-kind="catalog"]').count(),
    };
    if (!summarySections.overdue || !summarySections.cancellation) {
      throw new Error(`Dashboard icon summary section missing: ${JSON.stringify(summarySections)}`);
    }
    await page.screenshot({ path: path.join(reportDir, 'dashboard-icons-1280.png'), fullPage: true });
    await page.goto(`${origin}/subscriptions.php`, { waitUntil: 'load' });
    if (await page.locator('[data-dial-icon-kind="catalog"]').count() < 3) throw new Error('Subscription icons absent');
    const images = await page.locator('img[src*="dial-catalog/chatgpt.svg"]').evaluateAll(nodes =>
      nodes.map(img => ({ width: img.naturalWidth, height: img.naturalHeight, src: img.currentSrc })));
    if (images.length < 3 || images.some(item => item.width <= 0 || item.height <= 0)) {
      throw new Error(`Catalog asset failed to load: ${JSON.stringify(images)}`);
    }
    await page.screenshot({ path: path.join(reportDir, 'subscriptions-icons-1280.png'), fullPage: true });
    await page.setViewportSize({ width: 853, height: 800 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    if (overflow > 1) throw new Error(`853px icon layout overflows by ${overflow}px`);
    await page.screenshot({ path: path.join(reportDir, 'subscriptions-icons-853.png'), fullPage: true });
    if (errors.length) throw new Error(`Browser errors: ${errors.join('; ')}`);
    process.stdout.write(JSON.stringify({ result: 'passed', images: images.length, summarySections, overflow, errors }, null, 2) + '\n');
  } finally {
    if (context) await context.close();
    stop();
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
