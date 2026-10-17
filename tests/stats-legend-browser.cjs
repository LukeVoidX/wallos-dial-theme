#!/usr/bin/env node
// Reproduce a stored household name reaching the real statistics legend.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync, spawn } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:commit-arm64';
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-stats-legend.'));
const fixturePath = path.join(scratch, 'fixture.json');
const fixtureProcess = spawn('bash',
  ['tests/run-icon-http.sh', image, '--serve', '--fixture-out', fixturePath],
  { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
let fixtureLog = '';
for (const stream of [fixtureProcess.stdout, fixtureProcess.stderr]) {
  stream.on('data', chunk => { fixtureLog += chunk.toString(); });
}
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function fixture() {
  for (let attempt = 0; attempt < 90; attempt++) {
    if (fs.existsSync(fixturePath)) return JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
    if (fixtureProcess.exitCode !== null) throw new Error(`Fixture exited: ${fixtureLog}`);
    await pause(500);
  }
  throw new Error(`Fixture timed out: ${fixtureLog}`);
}

(async () => {
  let browser;
  try {
    const { base_url: base, database, accounts } = await fixture();
    assert.match(base, /^http:\/\/127\.0\.0\.1:\d+$/);
    assert(database.includes('/data/.dial-icon-http.'), 'Database must be disposable');
    const injectedName = 'Research <img src=x onerror=window.__dialLegendXss=1>';
    execFileSync('python3', ['-c', `
import html, sqlite3, sys
db = sqlite3.connect(sys.argv[1])
name = html.escape(sys.argv[2], quote=True)
member = db.execute('INSERT INTO household(name,user_id) VALUES (?,1)', (name,)).lastrowid
db.execute('UPDATE subscriptions SET payer_user_id=? WHERE id=(SELECT id FROM subscriptions WHERE user_id=1 AND price>0 ORDER BY id LIMIT 1)', (member,))
assert db.execute('SELECT COUNT(DISTINCT payer_user_id) FROM subscriptions WHERE user_id=1').fetchone()[0] == 2
db.commit()
db.close()
`, database, injectedName], { cwd: root });

    const launch = { headless: true };
    const channel = process.env.DIAL_BROWSER_CHANNEL || (os.platform() === 'darwin' ? 'chrome' : 'chromium');
    if (channel !== 'chromium') launch.channel = channel;
    browser = await chromium.launch(launch);
    const context = await browser.newContext({ serviceWorkers: 'block' });
    const owner = accounts.find(account => account.role === 'owner');
    assert(owner, 'Owner fixture account missing');
    const login = await context.request.post(`${base}/login.php`, {
      form: { username: owner.username, password: owner.password },
    });
    assert(login.ok(), `Login failed: ${login.status()}`);
    const page = await context.newPage();
    await page.goto(`${base}/stats.php`, { waitUntil: 'load' });
    const legend = page.locator('#memberSplitChart + .graph-legend');
    await legend.locator('.graph-legend-item').first().waitFor({ timeout: 15000 });
    assert.equal(await legend.locator('.graph-legend-item').count(), 2,
      'Both account-owned households must have nonzero spend');
    assert.equal(await legend.locator('img').count(), 0,
      'A stored household name was parsed as HTML in the legend');
    const labels = await legend.locator('.graph-legend-item').allTextContents();
    assert(labels.some(label => label.includes(injectedName)),
      'The household name must remain literal text in the legend');
    assert.equal(await page.evaluate(() => window.__dialLegendXss), undefined,
      'The injected household event handler executed');
    process.stdout.write('Statistics member legend renders stored names as text.\n');
    await context.close();
  } finally {
    if (browser) await browser.close();
    fixtureProcess.kill('SIGTERM');
    for (let attempt = 0; attempt < 30 && fixtureProcess.exitCode === null; attempt++) await pause(100);
    if (fixtureProcess.exitCode === null) fixtureProcess.kill('SIGKILL');
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
