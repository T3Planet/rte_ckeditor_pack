/**
 * End-to-end: monitor -> NEW per-version task on (fake) ClickUp -> human comments on it -> poller -> dispatch -> PR link on that task.
 * No network: core versions come from a fixture, ClickUp is the fake server, `gh` is a stub script.
 * State lives ONLY in the ClickUp task descriptions (machine-readable block); no file, no git branch.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { startFakeClickUp } from './fake-clickup-server.mjs';
import { parseBlock } from '../../Build/Scripts/ckeditor-state.mjs';

const run = promisify(execFile);
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const FX = path.join(ROOT, 'Tests/Automation/Fixtures');
const VIVEK = { id: 94966947, username: 'Vivek' };
const STRANGER = { id: 5, username: 'Stranger' };
const TASK = '86d4108yt';

async function setup() {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'cke-e2e-'));
  const cu = await startFakeClickUp();
  // extension checkout stand-in that still bundles 47.6.2 (the real Contrib/ may already be newer)
  const ext = path.join(tmp, 'ext');
  fs.mkdirSync(path.join(ext, 'Resources/Public/Contrib/@ckeditor'), { recursive: true });
  fs.writeFileSync(path.join(ext, 'Resources/Public/Contrib/@ckeditor/ckeditor5-utils.js'), 'const Ci="47.6.2";export{Ci as version};');
  const ghLog = path.join(tmp, 'gh.log');
  const gh = path.join(tmp, 'gh');
  fs.writeFileSync(gh, `#!/bin/sh\necho "$@" >> "${ghLog}"\n`, { mode: 0o755 });
  const env = {
    ...process.env, EXT_DIR: ext, CLICKUP_LIST_ID: '901609568667',
    CLICKUP_API_BASE: cu.base, CLICKUP_API_TOKEN: cu.token, CLICKUP_TASK_ID: TASK,
    CLICKUP_APPROVER_IDS: String(VIVEK.id), CLICKUP_APPROVER_NAMES: 'Vivek',
    CORE_VERSIONS_FILE: path.join(FX, 'core-48.json'), DISPATCH_IN_SCRIPT: '1', GH_BIN: gh,
    DRY_RUN: '0', GITHUB_OUTPUT: '', GITHUB_STEP_SUMMARY: '',
  };
  const node = (script, extra = {}, args = []) => run(process.execPath, [path.join(ROOT, 'Build/Scripts', script), ...args], { env: { ...env, ...extra }, cwd: tmp });
  // the state as stored on ClickUp: one block per task
  const state = () => {
    const requests = cu.tasks.map(t => ({ ...parseBlock(t.markdown_description), clickupTaskId: t.id })).filter(r => r.id);
    const meta = requests.map(r => r.meta).filter(Boolean).pop() || {};
    return { requests, meta };
  };
  return { tmp, cu, env, node, state, ghLog };
}

test('approve flow: request, rejected stranger, approval, dispatch, rebuild transitions, no duplicates', async () => {
  const { tmp, cu, node, state, ghLog } = await setup();
  try {
    await node('check-core-ckeditor.mjs');
    let s = state();
    assert.equal(s.requests.length, 1);
    const req = s.requests[0];
    assert.match(req.id, /^CKE-48\.5\.2-\d{8}$/);
    assert.equal(req.status, 'awaiting'); assert.equal(req.major, true); assert.equal(req.fromVersion, '47.6.2');
    assert.equal(cu.tasks.length, 1);
    const task = cu.tasks[0];
    assert.equal(req.clickupTaskId, task.id);
    assert.equal(task.list, '901609568667');
    assert.equal(task.name, 'Update CKEditor to 48.5.2 (TYPO3 13.4/14.3)');
    assert.deepEqual(task.tags, ['ckeditor-update']);
    assert.deepEqual(task.assignees, [VIVEK.id]);
    assert.match(task.markdown_description, /^\s+approve$/m);
    assert.match(task.markdown_description, /MAJOR UPDATE/);
    assert.match(task.markdown_description, /```ckeditor-automation\nckeditor-automation-state: \{.*\}\n```\n$/);
    assert.equal(s.meta.tracks['13.4'].branch, '48.5.2'); // monitor meta on the newest task
    assert.equal(cu.comments.length, 0); // nothing posted on the general task
    assert.equal(fs.readdirSync(tmp).some(f => /state/.test(f)), false); // no state file anywhere

    // second monitor run: same version -> no new request, no new comment, no duplicate task
    const before = cu.comments.length;
    await node('check-core-ckeditor.mjs');
    assert.equal(state().requests.length, 1); assert.equal(cu.comments.length, before);
    assert.equal(cu.tasks.length, 1);

    // poller with no reply yet
    await node('ckeditor-approval.mjs');
    assert.equal(state().requests[0].status, 'awaiting');

    await new Promise(r => setTimeout(r, 5));
    cu.addComment(TASK, 'approve', VIVEK); // on the old general task: ignored
    const bad = cu.addComment(task.id, 'approve', STRANGER);
    cu.addComment(task.id, 'Approve', VIVEK);
    await node('ckeditor-approval.mjs');
    s = state();
    assert.equal(s.requests[0].status, 'approved');
    assert.equal(s.requests[0].decision.username, 'Vivek');
    assert.ok(s.requests[0].processedCommentIds.includes(bad.id));
    assert.ok(cu.comments.some(c => c.parentId === bad.id && /not an allowed approver/.test(c.comment_text)));
    assert.ok(cu.comments.some(c => c.taskId === task.id && /^Approved by Vivek/.test(c.comment_text)));
    const gh = fs.readFileSync(ghLog, 'utf8').trim().split('\n');
    assert.deepEqual(gh, [`workflow run ckeditor-rebuild.yml -f request_id=${req.id} -f target_version=48.5.2`]);
    // human text of the description is kept when the block is rewritten
    assert.match(cu.tasks[0].markdown_description, /^\[CKEditor update request CKE-48\.5\.2-/);
    assert.equal((cu.tasks[0].markdown_description.match(/ckeditor-automation-state:/g) || []).length, 1);

    // poller again: nothing awaiting -> no second dispatch
    await node('ckeditor-approval.mjs');
    assert.equal(fs.readFileSync(ghLog, 'utf8').trim().split('\n').length, 1);

    // rebuild-side state transitions (CLI used by ckeditor-rebuild.yml) + ClickUp post from file
    await node('ckeditor-state.mjs', {}, ['require-status', req.id, 'approved,failed']);
    await node('ckeditor-state.mjs', {}, ['status', req.id, 'building', 'build={"runUrl":"https://example.invalid/run/1"}']);
    assert.equal(state().requests[0].status, 'building');
    await assert.rejects(node('ckeditor-state.mjs', {}, ['require-status', req.id, 'approved,failed']));
    const { stdout } = await node('ckeditor-state.mjs', {}, ['get', req.id]);
    assert.equal(JSON.parse(stdout).build.runUrl, 'https://example.invalid/run/1');
    const msg = path.join(tmp, 'msg.txt');
    fs.writeFileSync(msg, `PR opened (draft, major) for ${req.id}: https://example.invalid/pr/1`);
    await node('ckeditor-clickup.mjs', { REQUEST_ID: req.id }, [msg]);
    assert.ok(cu.comments.some(c => c.taskId === task.id && c.comment_text.startsWith('PR opened (draft, major)')));
    await node('ckeditor-state.mjs', {}, ['status', req.id, 'pr_open', 'prUrl=https://example.invalid/pr/1']);
    s = state();
    assert.equal(s.requests[0].status, 'pr_open'); assert.equal(s.requests[0].prUrl, 'https://example.invalid/pr/1');
    assert.equal(s.requests[0].decision.username, 'Vivek'); // other fields survive CLI updates
    const { stdout: body } = await node('ckeditor-pr-body.mjs', { EXT_DIR: ROOT }, [req.id]);
    assert.match(body, new RegExp(`https://app\\.clickup\\.com/t/${task.id}`));
  } finally { await cu.close(); }
});

test('failed rebuild: retry with a new approve on the same task', async () => {
  const { cu, node, state, ghLog } = await setup();
  try {
    await node('check-core-ckeditor.mjs');
    const req = state().requests[0];
    await new Promise(r => setTimeout(r, 5));
    cu.addComment(req.clickupTaskId, 'approve', VIVEK);
    await node('ckeditor-approval.mjs');
    await node('ckeditor-state.mjs', {}, ['status', req.id, 'failed', 'build={"failedStep":"smoke"}']);
    await node('ckeditor-approval.mjs');
    assert.equal(fs.readFileSync(ghLog, 'utf8').trim().split('\n').length, 1); // old approve not reused
    await new Promise(r => setTimeout(r, 5));
    cu.addComment(req.clickupTaskId, 'approve', VIVEK);
    await node('ckeditor-approval.mjs');
    assert.equal(state().requests[0].status, 'approved');
    assert.equal(fs.readFileSync(ghLog, 'utf8').trim().split('\n').length, 2);
    // monitor does not create a second task for the same version while it is failed/pr_open
    await node('check-core-ckeditor.mjs');
    assert.equal(cu.tasks.length, 1);
  } finally { await cu.close(); }
});

test('skip flow, reminder and expiry', async () => {
  const { cu, node, state } = await setup();
  try {
    await node('check-core-ckeditor.mjs');
    const req = state().requests[0];
    const day = 86400e3, t0 = Date.parse(req.requestedAt);
    await node('ckeditor-approval.mjs', { NOW: new Date(t0 + 8 * day).toISOString() });
    assert.equal(state().requests[0].remindersSent, 1);
    assert.ok(cu.comments.some(c => /^Reminder:/.test(c.comment_text)));
    await node('ckeditor-approval.mjs', { NOW: new Date(t0 + 9 * day).toISOString() });
    assert.equal(state().requests[0].remindersSent, 1); // only once

    // skip
    assert.ok(cu.comments.some(c => c.taskId === req.clickupTaskId && /^Reminder:/.test(c.comment_text)));
    cu.addComment(req.clickupTaskId, `skip ${req.id}`, VIVEK); // legacy form still accepted
    await node('ckeditor-approval.mjs');
    assert.equal(state().requests[0].status, 'skipped');
    // monitor must not ask again for a skipped version, even after the task was closed
    cu.tasks[0].status = { status: 'closed', type: 'closed' };
    const n = cu.comments.length;
    await node('check-core-ckeditor.mjs');
    assert.equal(state().requests.length, 1); assert.equal(cu.comments.length, n); assert.equal(cu.tasks.length, 1);

    // expiry: fresh list (old task untagged)
    cu.tasks[0].tags = [];
    await node('check-core-ckeditor.mjs');
    assert.equal(cu.tasks.length, 2);
    const r2 = state().requests.find(r => r.status === 'awaiting');
    await node('ckeditor-approval.mjs', { NOW: new Date(Date.parse(r2.expiresAt) + 1000).toISOString() });
    assert.equal(state().requests.find(r => r.id === r2.id && r.clickupTaskId === cu.tasks[1].id).status, 'expired');
  } finally { await cu.close(); }
});

test('legacy task without a block (created before the ClickUp state) is adopted', async () => {
  const { cu, node, state, ghLog } = await setup();
  try {
    const created = Date.now() - 60e3;
    cu.tasks.push({ id: 'legacy1', list: '901609568667', name: 'Update CKEditor to 48.5.2 (TYPO3 13.4/14.3)', markdown_description: '[CKEditor update request CKE-48.5.2-x]\n  approve', tags: [{ name: 'ckeditor-update' }], status: { status: 'to do', type: 'open' }, date_created: String(created) });
    await node('check-core-ckeditor.mjs');
    assert.equal(cu.tasks.length, 1); // no duplicate
    assert.ok(parseBlock(cu.tasks[0].markdown_description)?.meta); // block added
    cu.addComment('legacy1', 'approve', VIVEK);
    await node('ckeditor-approval.mjs');
    assert.equal(state().requests[0].status, 'approved');
    assert.match(fs.readFileSync(ghLog, 'utf8'), /target_version=48\.5\.2/);
  } finally { await cu.close(); }
});

test('diverging majors: warning only, once, no request', async () => {
  const { cu, node, state } = await setup();
  try {
    const fx = path.join(FX, 'core-divergent.json');
    await node('check-core-ckeditor.mjs', { CORE_VERSIONS_FILE: fx });
    assert.equal(state().requests.length, 0); assert.equal(cu.tasks.length, 0);
    assert.equal(cu.comments.filter(c => /different majors/.test(c.comment_text)).length, 1);
    await node('check-core-ckeditor.mjs', { CORE_VERSIONS_FILE: fx });
    assert.equal(cu.comments.filter(c => /different majors/.test(c.comment_text)).length, 1);
  } finally { await cu.close(); }
});

test('dry run writes nothing and posts nothing', async () => {
  const { cu, node } = await setup();
  try {
    const { stdout } = await node('check-core-ckeditor.mjs', { DRY_RUN: '1' });
    assert.match(stdout, /\[DRY_RUN\] ClickUp new task in list 901609568667 "Update CKEditor to 48\.5\.2/);
    assert.equal(cu.comments.length, 0); assert.equal(cu.tasks.length, 0); assert.equal(cu.stats.updates, 0);
  } finally { await cu.close(); }
});
