#!/usr/bin/env node
// Real Service Worker cache and account-switch checks on a synthetic fixture.
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const image = process.argv[2] || 'wallos-dial:compat';
fs.mkdirSync(path.join(root, 'data'), { recursive: true });
const scratch = fs.mkdtempSync(path.join(root, 'data', '.dial-icon-cache.'));
const fixturePath = path.join(scratch, 'fixture.json');
const service = spawn('bash', ['tests/run-icon-http.sh', image, '--serve', '--fixture-out', fixturePath],
  { cwd: root, stdio: ['ignore', 'pipe', 'pipe'] });
let serviceLog = '';
for (const stream of [service.stdout, service.stderr]) stream.on('data', chunk => { serviceLog += chunk.toString(); });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const must = (yes, message) => { if (!yes) throw new Error(message); };
async function fixture() {
  for (let i = 0; i < 50; i++) {
    if (fs.existsSync(fixturePath)) return JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
    if (service.exitCode !== null) throw new Error('Fixture exited early: ' + serviceLog);
    await pause(1000);
  }
  throw new Error('Fixture did not become ready: ' + serviceLog);
}
async function login(context, base, username) {
  const response = await context.request.post(`${base}/login.php`, {
    form: { username, password: 'dial-fixture-only' },
  });
  must(response.ok(), `${username} login failed: ${response.status()}`);
}
(async () => {
  let context;
  try {
    const { base_url: base } = await fixture();
    const profile = path.join(scratch, 'profile');
    context = await chromium.launchPersistentContext(profile, {
      channel: 'chrome', headless: true, serviceWorkers: 'block', viewport: { width: 1000, height: 700 },
    });
    let page = context.pages()[0] || await context.newPage();
    await page.goto(`${base}/login.php`, { waitUntil: 'load' });
    await page.evaluate(async () => {
      const old = await caches.open('pages-cache-v1');
      await old.put('/endpoints/dial/icons.php', new Response('{"old":"private"}',
        { headers: { 'Content-Type': 'application/json' } }));
      await old.put('/endpoints/subscriptions/get.php?sort=old', new Response('previous account',
        { headers: { 'Content-Type': 'text/html' } }));
      const current = await caches.open('pages-cache-v2');
      await current.put('/index.php', new Response('previous account overview',
        { headers: { 'Content-Type': 'text/html' } }));
    });
    must((await page.evaluate(() => caches.keys())).includes('pages-cache-v1'), 'old cache fixture absent');
    await context.close();

    context = await chromium.launchPersistentContext(profile, {
      channel: 'chrome', headless: true, serviceWorkers: 'allow', viewport: { width: 1000, height: 700 },
    });
    await login(context, base, 'demo');
    page = context.pages()[0] || await context.newPage();
    await page.goto(`${base}/`, { waitUntil: 'load' });
    await page.waitForFunction(() => navigator.serviceWorker.controller !== null, { timeout: 20000 });
    const cacheKeys = await page.evaluate(() => caches.keys());
    must(!cacheKeys.includes('pages-cache-v1'), '0.1.1 private pages cache survived activation');
    must(!cacheKeys.includes('pages-cache-v2'), '1.0 account pages cache survived activation');
    await page.goto(`${base}/subscriptions.php`, { waitUntil: 'load' });
    const cachedPages = await page.evaluate(async () => {
      const output = [];
      for (const key of await caches.keys()) {
        const cache = await caches.open(key);
        for (const request of await cache.keys()) {
          const url = new URL(request.url);
          if (/^\/(?:index|subscriptions|profile|settings|calendar|stats|admin|about|login)\.php$/.test(url.pathname)) {
            output.push(request.url);
          }
        }
      }
      return output;
    });
    must(cachedPages.length === 0, `account page entered CacheStorage: ${cachedPages.join(', ')}`);

    const token = await page.evaluate(() => window.csrfToken);
    must(typeof token === 'string' && token.length > 10, 'CSRF token missing');
    const png = fs.readFileSync(path.join(root, 'theme/images/dial-icon/favicon-32.png'));
    const uploaded = await context.request.post(`${base}/endpoints/dial/icons.php`, {
      headers: { 'X-CSRF-Token': token },
      multipart: { action: 'upload', label: 'Synthetic cache icon',
        icon: { name: 'sample.png', mimeType: 'image/png', buffer: png } },
    });
    must(uploaded.ok(), `upload failed: ${uploaded.status()} ${await uploaded.text()}`);
    const iconId = (await uploaded.json()).id;
    must(Number.isInteger(iconId), 'upload returned no icon ID');
    const privateResponse = await page.evaluate(async id => {
      const image = await fetch(`/endpoints/dial/icon.php?id=${id}`);
      const list = await fetch('/endpoints/dial/icons.php');
      const fragment = await fetch('/endpoints/subscriptions/get.php?sort=name');
      return { imageStatus: image.status, listStatus: list.status,
        fragmentStatus: fragment.status, imageType: image.headers.get('content-type'),
        listType: list.headers.get('content-type') };
    }, iconId);
    must(privateResponse.imageStatus === 200 && privateResponse.listStatus === 200 &&
      privateResponse.fragmentStatus === 200,
      `controlled private fetch failed: ${JSON.stringify(privateResponse)}`);
    const cached = await page.evaluate(async () => {
      const output = [];
      for (const key of await caches.keys()) {
        const cache = await caches.open(key);
        for (const request of await cache.keys()) output.push(request.url);
      }
      return output.filter(url => /\/endpoints\/(?:dial\/icons?\.php|subscriptions?\/get\.php)/.test(url));
    });
    must(cached.length === 0, `private response entered CacheStorage: ${cached.join(', ')}`);

    await page.goto(`${base}/logout.php`, { waitUntil: 'load' });
    await page.close();
    await login(context, base, 'second');
    page = await context.newPage();
    await page.goto(`${base}/`, { waitUntil: 'load' });
    const cross = await page.evaluate(async id => {
      const response = await fetch(`/endpoints/dial/icon.php?id=${id}`);
      return response.status;
    }, iconId);
    must(cross === 404, `second account saw first account image: ${cross}`);
    process.stdout.write(JSON.stringify({ result: 'passed', cacheKeys, privateResponse,
      privateCached: cached.length, accountPagesCached: cachedPages.length,
      foreignImageStatus: cross }) + '\n');
  } finally {
    if (context) await context.close();
    service.kill('SIGTERM');
    for (let i = 0; i < 20 && service.exitCode === null; i++) await pause(100);
    if (service.exitCode === null) service.kill('SIGKILL');
    fs.rmSync(scratch, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
