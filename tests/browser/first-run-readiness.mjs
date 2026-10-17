#!/usr/bin/env node
// The first HTTP response must not race the base or Dial SQLite migrations.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawnSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const image = process.argv[2] || 'wallos-dial:1.0.0';
const evidenceDir = path.join(root, 'data', 'auth-install-qa');
const fixture = fs.mkdtempSync(path.join(root, 'data', 'auth-ready-'));
const dbDir = path.join(fixture, 'db');
const logoDir = path.join(fixture, 'logos');
const dbFile = path.join(dbDir, 'wallos.db');
const name = `dial-first-run-ready-${process.pid}`;
const corruptName = `${name}-corrupt`;
const readonlyName = `${name}-readonly`;
fs.mkdirSync(evidenceDir, { recursive: true });
fs.mkdirSync(dbDir);
fs.mkdirSync(logoDir);
const events = [];
const docker = (...args) => execFileSync('docker', args, { encoding: 'utf8' }).trim();
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

function migrationComplete() {
  if (!fs.existsSync(dbFile) || fs.statSync(dbFile).size < 32768) return false;
  try {
    const program = `import sqlite3, sys
db = sqlite3.connect('file:' + sys.argv[1] + '?mode=ro', uri=True, timeout=1)
print(db.execute("SELECT COUNT(*) FROM migrations WHERE migration='migrations/dial_001_icon_library.php'").fetchone()[0])`;
    return execFileSync('python3', ['-c', program, dbFile],
      { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim() === '1';
  } catch { return false; }
}

async function probe(url) {
  try {
    const response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(1800) });
    return { status: response.status, text: (await response.text()).slice(0, 240) };
  } catch { return { status: null, text: '' }; }
}

let started = false;
try {
  docker('run', '-d', '--rm', '--name', name, '-e', 'TZ=UTC', '-p', '127.0.0.1::80',
    '-v', `${dbDir}:/var/www/html/db`, '-v', `${logoDir}:/var/www/html/images/uploads/logos`, image);
  started = true;
  const port = docker('port', name, '80/tcp').split(':').at(-1);
  const base = `http://127.0.0.1:${port}`;
  let ready = false;
  for (let attempt = 0; attempt < 300; attempt++) {
    const [health, registration] = await Promise.all([
      probe(`${base}/health.php`), probe(`${base}/registration.php`),
    ]);
    // SQLite readers can contend with an upstream DDL migration. Probe HTTP
    // first and inspect the migration marker only after a page answers 200.
    const migrated = (health.status === 200 || registration.status === 200)
      ? migrationComplete() : null;
    events.push({ attempt, migrated, health: health.status, registration: registration.status,
      registrationFatal: /Fatal error|database is locked|no such table/.test(registration.text) });
    assert(health.status !== 200 || migrated,
      `health returned 200 before Dial migration at attempt ${attempt}`);
    assert(registration.status === null || registration.status < 500,
      `registration failed during first run: HTTP ${registration.status}, ${registration.text}`);
    assert(!/Fatal error|database is locked|no such table/.test(registration.text),
      `registration returned an incomplete bootstrap page: ${registration.text}`);
    if (migrated && health.status === 200 && registration.status === 200) {
      assert.match(registration.text, /<!DOCTYPE html>/i, 'registration rendered after readiness');
      ready = true;
      break;
    }
    await sleep(60);
  }
  assert(ready, 'container never reached a migrated, healthy registration page');
  const burst = await Promise.all(Array.from({ length: 8 }, () => probe(`${base}/registration.php`)));
  assert(burst.every(response => response.status === 200 && !/Fatal error/.test(response.text)),
    'concurrent first-run registration requests failed after readiness');
  // A bind mount can report host ownership under Colima even when the mapped
  // www-data user can write. Exercise real SQLite and Logo writes instead.
  let writable = false;
  for (let attempt = 0; attempt < 12; attempt++) {
    const command = `php -r '$db=new SQLite3("/var/www/html/db/wallos.db"); if (!$db->exec("CREATE TABLE dial_qa_write_check(id INTEGER)")) exit(1); if (!$db->exec("DROP TABLE dial_qa_write_check")) exit(1);' && touch /var/www/html/images/uploads/logos/.dial-qa-write-check && rm /var/www/html/images/uploads/logos/.dial-qa-write-check`;
    const result = spawnSync('docker', ['exec', '--user', '82:82', name, 'sh', '-ec', command],
    { encoding: 'utf8' });
    writable = result.status === 0;
    if (writable) break;
    await sleep(100);
  }
  assert(writable, 'mapped www-data cannot write SQLite and Logo volume');
  console.log(`PASS fresh startup gate: ${events.length} probes, 8 concurrent registration requests`);

  const corrupt = fs.mkdtempSync(path.join(root, 'data', 'auth-corrupt-'));
  try {
    fs.mkdirSync(path.join(corrupt, 'db'));
    fs.mkdirSync(path.join(corrupt, 'logos'));
    fs.writeFileSync(path.join(corrupt, 'db', 'wallos.db'), 'not a SQLite database\n');
    docker('run', '-d', '--name', corruptName, '-p', '127.0.0.1::80',
      '-v', `${path.join(corrupt, 'db')}:/var/www/html/db`,
      '-v', `${path.join(corrupt, 'logos')}:/var/www/html/images/uploads/logos`, image);
    const corruptPort = docker('port', corruptName, '80/tcp').split(':').at(-1);
    let state;
    for (let attempt = 0; attempt < 80; attempt++) {
      const badHealth = await probe(`http://127.0.0.1:${corruptPort}/health.php`);
      assert.notEqual(badHealth.status, 200, 'invalid database must never pass health');
      state = JSON.parse(docker('inspect', '--format', '{{json .State}}', corruptName));
      if (state.Status === 'exited') break;
      await sleep(100);
    }
    assert.equal(state?.Status, 'exited', 'failed bootstrap must exit');
    assert.notEqual(state.ExitCode, 0, 'failed bootstrap needs nonzero exit');
    assert.notEqual(state.Health?.Status, 'healthy', 'failed bootstrap must not become healthy');
    console.log(`PASS corrupt-database fail closed: exit ${state.ExitCode}`);
  } finally {
    try { docker('rm', '-f', corruptName); } catch { /* failed startup */ }
    fs.rmSync(corrupt, { recursive: true, force: true });
  }

  // Both read-only personal SVG mounts are shown as optional in the README.
  // Boot must not attempt to chown through them.
  const readonly = fs.mkdtempSync(path.join(root, 'data', 'auth-readonly-'));
  try {
    for (const subdir of ['db', 'logos', 'icons']) fs.mkdirSync(path.join(readonly, subdir));
    const mapping = path.join(readonly, 'logo-map.local.php');
    fs.writeFileSync(mapping, '<?php\nreturn [];\n');
    fs.copyFileSync(path.join(root, 'theme/images/dial-icon/favicon.svg'),
      path.join(readonly, 'icons', 'qa-neutral.svg'));
    docker('run', '-d', '--name', readonlyName, '-p', '127.0.0.1::80',
      '-v', `${path.join(readonly, 'db')}:/var/www/html/db`,
      '-v', `${path.join(readonly, 'logos')}:/var/www/html/images/uploads/logos`,
      '-v', `${mapping}:/var/www/html/includes/dial_logo_map.local.php:ro`,
      '-v', `${path.join(readonly, 'icons')}:/var/www/html/images/dial-logos:ro`, image);
    const readonlyPort = docker('port', readonlyName, '80/tcp').split(':').at(-1);
    let readonlyHealthy = false;
    let readonlyState;
    for (let attempt = 0; attempt < 100; attempt++) {
      readonlyHealthy = (await probe(`http://127.0.0.1:${readonlyPort}/health.php`)).status === 200;
      readonlyState = JSON.parse(docker('inspect', '--format', '{{json .State}}', readonlyName));
      if (readonlyHealthy || readonlyState.Status === 'exited') break;
      await sleep(100);
    }
    const readonlyLogs = spawnSync('docker', ['logs', '--tail=12', readonlyName], { encoding: 'utf8' });
    assert(readonlyHealthy,
      `read-only personal SVG mounts blocked first run: ${readonlyState?.Status}, ${(readonlyLogs.stdout || '') + (readonlyLogs.stderr || '')}`);
    docker('exec', readonlyName, 'sh', '-ec',
      'test -r /var/www/html/includes/dial_logo_map.local.php && test -r /var/www/html/images/dial-logos/qa-neutral.svg');
    console.log('PASS optional read-only personal SVG mounts');
  } finally {
    try { docker('rm', '-f', readonlyName); } catch { /* failed startup */ }
    fs.rmSync(readonly, { recursive: true, force: true });
  }
} finally {
  let logs = '';
  if (started) {
    const result = spawnSync('docker', ['logs', '--tail=70', name], { encoding: 'utf8' });
    logs = (result.stdout || '') + (result.stderr || '');
    try { docker('stop', name); } catch { /* failed startup */ }
  }
  fs.writeFileSync(path.join(evidenceDir, 'first-run-readiness.json'),
    JSON.stringify({ image, events, logs }, null, 2));
  fs.rmSync(fixture, { recursive: true, force: true });
}
