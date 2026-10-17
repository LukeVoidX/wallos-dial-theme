#!/usr/bin/env node
// Browser QA for a disposable old -> candidate -> Dial-only update on one origin.
// Requires installed Playwright/Chrome and the three local images; touches no live data.
'use strict';
const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const { createHash } = require('node:crypto');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const old = 'ghcr.io/lukevoidx/wallos-dial:0.1.1';
const candidate = process.argv[2] || 'wallos-dial:1.0.0-rc.1';
const dialOnly = process.argv[3] || 'wallos-dial:1.0.0-rc.2';
const port = process.env.DIAL_BROWSER_PORT || '43183';
const origin = `http://127.0.0.1:${port}`;
const name = `dial-browser-${process.pid}`;
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-browser.'));
fs.mkdirSync(path.join(scratch, 'db'));
fs.mkdirSync(path.join(scratch, 'logos'));
const run = (cmd, args, opts = {}) => execFileSync(cmd, args, {
  cwd: root, encoding: 'utf8', stdio: opts.stdio || 'pipe', ...opts,
}).trim();
const docker = (...args) => run('docker', args);
const dockerLogs = () => {
  const result = spawnSync('docker', ['logs', name], { encoding: 'utf8' });
  return `${result.stdout || ''}${result.stderr || ''}`;
};
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function start(image) {
  docker('run', '-d', '--name', name, '-p', `127.0.0.1:${port}:80`,
    '-v', `${scratch}/db:/var/www/html/db`,
    '-v', `${scratch}/logos:/var/www/html/images/uploads/logos`, image);
  for (let n = 0; n < 70; n++) {
    const logs = dockerLogs();
    if (logs.includes('Updated next payment dates')) {
      try {
        const response = await fetch(`${origin}/health.php`);
        if (response.ok) return;
      } catch (_) { /* Nginx can start after the log line. */ }
    }
    await pause(1000);
  }
  throw new Error(`Startup failed for ${image}: ${dockerLogs()}`);
}
function stop() { try { docker('container', 'rm', '-f', name); } catch (_) { /* already gone */ } }

async function workerState(page) {
  return page.evaluate(async () => {
    const registration = await navigator.serviceWorker.getRegistration();
    return {
      controller: navigator.serviceWorker.controller?.scriptURL || null,
      active: registration?.active?.scriptURL || null,
      controllerIsActive: navigator.serviceWorker.controller === registration?.active,
      activeState: registration?.active?.state || null,
      caches: await caches.keys(),
    };
  });
}
async function awaitStage(page, expectedScript, expectedCache) {
  const deadline = Date.now() + Number(process.env.DIAL_WORKER_TIMEOUT || 120000);
  let latest;
  while (Date.now() < deadline) {
    try {
      await page.goto(`${origin}/?dial-browser-qa=${Date.now()}`, { waitUntil: 'load' });
      latest = await workerState(page);
      const staticCaches = latest.caches.filter(key => key.startsWith('static-cache-'));
      if (latest.controller?.endsWith(expectedScript) &&
          latest.active?.endsWith(expectedScript) &&
          latest.controllerIsActive &&
          latest.activeState === 'activated' &&
          staticCaches.length === 1 && staticCaches[0] === expectedCache) {
        return latest;
      }
      await page.evaluate(async () => {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) await registration.update();
      });
    } catch (_) { /* Worker may be activating while the image switches. */ }
    await pause(1500);
  }
  const allJs = await page.evaluate(async cacheName => {
    const response = await fetch('/scripts/all.js?dial-browser-qa=1');
    const cache = await caches.open(cacheName);
    const cached = await cache.match('/scripts/all.js');
    return { fetched: (await response.text()).slice(0, 220),
      cached: cached ? (await cached.text()).slice(0, 220) : null,
      scripts: [...document.scripts].map(script => script.src).filter(Boolean).slice(-10) };
  }, expectedCache).catch(error => ({ error: String(error) }));
  throw new Error(`Worker did not reach ${expectedCache}: ${JSON.stringify({ ...latest, allJs })}`);
}

(async () => {
  let context;
  try {
    await start(old);
    docker('exec', name, 'sh', '-c', 'chmod a+rwX /var/www/html/db /var/www/html/db/wallos.db /var/www/html/images/uploads/logos');
    stop();
    run('python3', ['demo/seed.py', `${scratch}/db/wallos.db`, `${scratch}/logos`]);
    const hash = docker('run', '--rm', '--entrypoint', 'php', old,
      '-r', 'echo password_hash("DialFixture-2026!", PASSWORD_DEFAULT);');
    run('python3', ['-c', `import sqlite3,sys
db=sqlite3.connect(sys.argv[1]);db.execute('UPDATE user SET password=? WHERE id=1',(sys.argv[2],));db.execute('UPDATE admin SET login_disabled=0 WHERE id=1');db.commit();db.close()`,
    `${scratch}/db/wallos.db`, hash]);
    await start(old);
    context = await chromium.launchPersistentContext(`${scratch}/chrome-profile`, {
      channel: 'chrome', headless: true, serviceWorkers: 'allow',
    });
    const page = context.pages()[0] || await context.newPage();
    const pageErrors = [];
    const failedRequests = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('requestfailed', request => failedRequests.push(`${request.url()} ${request.failure()?.errorText}`));
    async function login() {
      const response = await context.request.post(`${origin}/login.php`, {
        form: { username: 'demo', password: 'DialFixture-2026!' },
      });
      if (!response.ok()) throw new Error(`Login HTTP ${response.status()}`);
    }
    await login();
    const stages = [];
    stages.push({ image: old, ...(await awaitStage(page, '/service-worker.js', 'static-cache-v8')) });
    await page.goto(`${origin}/subscriptions.php`);
    stop();

    await start(candidate);
    await login();
    stages.push({ image: candidate, ...(await awaitStage(page, '/service-worker.php', 'static-cache-v5.8.3-dial-1.0.0-rc.1')) });
    const candidateAssets = await page.evaluate(async () => ({
      css: await fetch('/styles/dial.css?dial=1.0.0-rc.1').then(async r => ({ status: r.status, body: await r.text() })),
      js: await fetch('/scripts/dial-interaction.js?dial=1.0.0-rc.1').then(async r => ({ status: r.status, body: await r.text() })),
      worker: await fetch('/service-worker.js').then(r => r.text()),
      phpWorker: await fetch('/service-worker.php').then(r => r.text()),
    }));
    const candidateCssNetwork = await context.request.get(`${origin}/styles/dial.css?dial=1.0.0-rc.1`).then(r => r.text());
    const candidateJsNetwork = await context.request.get(`${origin}/scripts/dial-interaction.js?dial=1.0.0-rc.1`).then(r => r.text());
    if (candidateAssets.css.status !== 200 || candidateAssets.js.status !== 200 ||
        candidateAssets.css.body !== candidateCssNetwork || candidateAssets.js.body !== candidateJsNetwork ||
        !candidateAssets.worker.includes("importScripts('service-worker.php');") ||
        !candidateAssets.phpWorker.includes('static-cache-v5.8.3-dial-1.0.0-rc.1')) {
      throw new Error('Candidate CSS/JS/worker response differs from the active image');
    }
    await page.goto(`${origin}/subscriptions.php`);
    stop();

    await start(dialOnly);
    await login();
    stages.push({ image: dialOnly, ...(await awaitStage(page, '/service-worker.php', 'static-cache-v5.8.3-dial-1.0.0-rc.2')) });
    await page.reload({ waitUntil: 'load' });
    const freshBody = await page.evaluate(async () =>
      (await fetch('/scripts/dial-interaction.js?dial=1.0.0-rc.2')).text());
    const fresh = freshBody.includes('dial-cache-check-rc2');
    const freshNetwork = await context.request.get(`${origin}/scripts/dial-interaction.js?dial=1.0.0-rc.2`).then(r => r.text());
    if (!fresh) {
      const networkBody = await context.request.get(`${origin}/scripts/dial-interaction.js?dial=1.0.0-rc.2`).then(r => r.text());
      const cached = await page.evaluate(async () => {
        const details = {};
        for (const key of await caches.keys()) {
          const cache = await caches.open(key);
          const response = await cache.match('/scripts/dial-interaction.js?dial=1.0.0-rc.2', { ignoreSearch: true });
          details[key] = response ? (await response.text()).includes('dial-cache-check-rc2') : null;
        }
        const response = await fetch('/scripts/dial-interaction.js?dial=1.0.0-rc.2');
        details.fetched = { url: response.url, fromMarker: (await response.text()).includes('dial-cache-check-rc2') };
        return details;
      });
      throw new Error(`Dial-only update still serves rc.1 JS body: ${JSON.stringify({ network: networkBody.includes('dial-cache-check-rc2'), cached, worker: await workerState(page) })}`);
    }
    if (freshBody !== freshNetwork) throw new Error('Dial-only update browser body differs from network body');
    if (pageErrors.length) throw new Error(`Browser page errors: ${pageErrors.join('; ')}`);
    const report = { origin, stages, candidateAssets: {
      css: { status: candidateAssets.css.status, sha256: createHash('sha256').update(candidateAssets.css.body).digest('hex') },
      js: { status: candidateAssets.js.status, sha256: createHash('sha256').update(candidateAssets.js.body).digest('hex') },
      legacyStub: true, combinedCache: true,
    }, dialOnlyMarker: fresh,
    dialOnlyJsSha256: createHash('sha256').update(freshBody).digest('hex'),
    pageErrors, failedRequests };
    console.log(JSON.stringify(report, null, 2));
  } finally {
    if (context) await context.close();
    stop();
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
