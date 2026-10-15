import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseBlock, withBlock, requestFromTask, loadState, saveState } from '../../Build/Scripts/ckeditor-state.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

test('block round trip keeps the human text and replaces the old block', () => {
  const d1 = withBlock('Hello\n  approve', { id: 'CKE-1.2.3-20261015', status: 'awaiting' });
  assert.deepEqual(parseBlock(d1), { id: 'CKE-1.2.3-20261015', status: 'awaiting' });
  const d2 = withBlock(d1, { id: 'CKE-1.2.3-20261015', status: 'pr_open' });
  assert.equal(parseBlock(d2).status, 'pr_open');
  assert.match(d2, /^Hello\n  approve\n\n```ckeditor-automation\n/);
  assert.equal((d2.match(/ckeditor-automation-state/g) || []).length, 1);
  // plain-text description (ClickUp strips the fence) still parses
  assert.equal(parseBlock('Hello\nckeditor-automation\nckeditor-automation-state: {"id":"x","status":"failed"}\n').status, 'failed');
  assert.equal(parseBlock('nothing'), null);
});

test('requestFromTask: block, legacy title, unrelated task', () => {
  const r = requestFromTask({ id: 't1', markdown_description: withBlock('x', { id: 'CKE-48.5.2-20261015', targetVersion: '48.5.2', status: 'building' }) });
  assert.equal(r.status, 'building'); assert.equal(r.clickupTaskId, 't1');
  const l = requestFromTask({ id: 't2', name: 'Update CKEditor to 48.6.0 (TYPO3 13.4/14.3)', date_created: String(Date.parse('2026-11-01T03:30:00Z')), status: { type: 'open' } });
  assert.equal(l.id, 'CKE-48.6.0-20261101'); assert.equal(l.status, 'awaiting'); assert.equal(l.legacy, true);
  assert.equal(requestFromTask({ id: 't3', name: 'Update CKEditor to 48.6.0 (TYPO3 13.4/14.3)', status: { type: 'closed' } }).status, 'merged');
  assert.equal(requestFromTask({ id: 't4', name: 'Something else' }), null);
});

test('loadState/saveState with a mocked ClickUp client: only changed tasks are written', async () => {
  const tasks = [
    { id: 'a', name: 'Update CKEditor to 48.5.2 (TYPO3 13.4/14.3)', markdown_description: withBlock('A', { id: 'CKE-48.5.2-20261001', targetVersion: '48.5.2', status: 'pr_open', requestedAt: '2026-10-01T00:00:00Z', meta: { lastCheckedAt: '2026-10-01T00:00:00Z', tracks: { '13.4': { branch: '48.5.2' } } } }) },
    { id: 'b', name: 'Update CKEditor to 48.6.0 (TYPO3 13.4/14.3)', markdown_description: withBlock('B', { id: 'CKE-48.6.0-20261101', targetVersion: '48.6.0', status: 'awaiting', requestedAt: '2026-11-01T00:00:00Z' }) },
  ];
  const calls = [];
  const client = { async request(m, p, body) {
    calls.push([m, p]);
    if (m === 'GET') { assert.match(p, /^\/list\/L1\/task\?include_closed=true&.*tags\[\]=ckeditor-update/); return { tasks, last_page: true }; }
    return {};
  }, async updateTask(id, body) { calls.push(['PUT', id, body]); } };
  const state = await loadState({ client, listId: 'L1' });
  assert.deepEqual(state.requests.map(r => r.id), ['CKE-48.5.2-20261001', 'CKE-48.6.0-20261101']);
  assert.equal(state.tracks['13.4'].branch, '48.5.2'); // meta loaded
  state.requests[1].status = 'approved';
  await saveState(state, { client });
  const puts = calls.filter(c => c[0] === 'PUT');
  // a: meta moves to the newest task -> rewritten without meta; b: status + meta
  assert.deepEqual(puts.map(p => p[1]).sort(), ['a', 'b']);
  const b = parseBlock(puts.find(p => p[1] === 'b')[2].markdown_content);
  assert.equal(b.status, 'approved'); assert.equal(b.meta.tracks['13.4'].branch, '48.5.2');
  assert.match(puts.find(p => p[1] === 'b')[2].markdown_content, /^B\n/);
  calls.length = 0;
  await saveState(state, { client });
  assert.equal(calls.length, 0); // unchanged -> no writes
});

test('no automation/state branch is used anywhere in the automation', () => {
  const files = ['.github/workflows/ckeditor-core-monitor.yml', '.github/workflows/ckeditor-approval-poller.yml', '.github/workflows/ckeditor-rebuild.yml',
    ...fs.readdirSync(path.join(ROOT, 'Build/Scripts')).map(f => `Build/Scripts/${f}`)];
  for (const f of files) {
    const src = fs.readFileSync(path.join(ROOT, f), 'utf8');
    assert.doesNotMatch(src, /automation\/state|state-branch|\.automation-state|STATE_FILE/, f);
  }
  assert.equal(fs.existsSync(path.join(ROOT, 'Build/Scripts/ckeditor-state-branch.sh')), false);
  for (const f of ['ckeditor-core-monitor.yml', 'ckeditor-approval-poller.yml']) {
    assert.match(fs.readFileSync(path.join(ROOT, '.github/workflows', f), 'utf8'), /contents: read/, f);
  }
});
