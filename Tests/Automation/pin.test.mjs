import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const PIN = path.join(ROOT, 'Build/Scripts/ckeditor-pin.mjs');

function sandbox() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cke-pin-'));
  for (const f of ['packages.json', 'package.json']) fs.copyFileSync(path.join(ROOT, 'Build/ckeditor', f), path.join(dir, f));
  return dir;
}

test('pin: same major updates packages.json and exact package.json pins incl. ckeditor5 umbrella', () => {
  const dir = sandbox();
  const pk = JSON.parse(fs.readFileSync(path.join(dir, 'packages.json')));
  const v = `${pk.supportedMajor}.9.9`;
  execFileSync(process.execPath, [PIN, v], { env: { ...process.env, CKEDITOR_BUILD_DIR: dir } });
  const pj = JSON.parse(fs.readFileSync(path.join(dir, 'package.json')));
  assert.equal(JSON.parse(fs.readFileSync(path.join(dir, 'packages.json'))).ckeditorVersion, v);
  assert.equal(pj.dependencies.ckeditor5, v);
  assert.equal(pj.dependencies['@ckeditor/ckeditor5-utils'], v);
  assert.equal(pj.dependencies['@ckeditor/ckeditor5-comments'], v);
  assert.equal(pj.dependencies['@ckeditor/ckeditor5-inspector'], pk.separatelyVersioned['@ckeditor/ckeditor5-inspector']);
  assert.ok(Object.values(pj.dependencies).every(x => /^\d+\.\d+\.\d+$/.test(x)), 'exact pins only');
  assert.equal(pj.dependencies['@ckeditor/ckeditor5-theme-lark'], undefined);
});

test('pin: another major is refused (config port needed)', () => {
  const dir = sandbox();
  const pk = JSON.parse(fs.readFileSync(path.join(dir, 'packages.json')));
  const r = spawnSync(process.execPath, [PIN, `${pk.supportedMajor + 1}.0.0`], { env: { ...process.env, CKEDITOR_BUILD_DIR: dir }, encoding: 'utf8' });
  assert.equal(r.status, 3);
  assert.match(r.stderr, /different major/);
});
