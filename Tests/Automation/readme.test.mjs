import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { updateCompat, extensionMajor } from '../../Build/Scripts/update-readme-compat.mjs';
import { renderBody } from '../../Build/Scripts/ckeditor-pr-body.mjs';

const root = new URL('../../', import.meta.url);

test('README compatibility row of the current major is updated, others untouched', () => {
  const readme = fs.readFileSync(new URL('README.md', root), 'utf8');
  const maj = extensionMajor(fs.readFileSync(new URL('ext_emconf.php', root), 'utf8'));
  const res = updateCompat(readme, maj, '48.5.2');
  const row = res.readme.split('\n').find(l => l.startsWith(`| v${maj}.x`));
  assert.match(row, /\|\s48\.5\.2\s+\|/);
  assert.equal(row.length, readme.split('\n').find(l => l.startsWith(`| v${maj}.x`)).length); // alignment kept
  const others = (s) => s.split('\n').filter(l => !l.startsWith(`| v${maj}.x`));
  assert.deepEqual(others(res.readme), others(readme));
});

test('PR body template is filled', () => {
  const tpl = fs.readFileSync(new URL('.github/PULL_REQUEST_TEMPLATE/ckeditor-update.md', root), 'utf8');
  const body = renderBody(tpl, { id: 'CKE-48.5.2-20261101', targetVersion: '48.5.2', fromVersion: '47.6.2', major: true, decision: { username: 'Vivek', date: '2026-11-02T00:00:00Z' }, tracks: { '13.4': { branch: '48.5.2' }, '14.3': { branch: '48.5.2' } }, clickupTaskId: '86d4108yt' }, {});
  assert.doesNotMatch(body, /\{\{\w+\}\}/);
  assert.match(body, /47\.6\.2` -> `48\.5\.2/);
  assert.match(body, /app\.clickup\.com\/t\/86d4108yt/);
});
