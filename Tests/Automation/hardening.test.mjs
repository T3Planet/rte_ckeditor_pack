import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { bumpKind, bumpVersion, emconfVersion, setEmconfVersion, changelogEntry, prependChangelog, topChangelog, releaseProblems } from '../../Build/Scripts/ckeditor-release.mjs';
import { pollableRequest, timing, evaluate, revertUnstarted } from '../../Build/Scripts/ckeditor-approval.mjs';
import { buildPrompt } from '../../.agents/agents/src/prompt.mjs';
import { parseArgs } from '../../.agents/agents/src/ckeditor-upgrade.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const hasPhp = spawnSync('php', ['-v']).status === 0;

test('version bump: patch for same CKEditor major, minor for a new major', () => {
  assert.equal(bumpKind('48.5.2', '48.6.0'), 'patch');
  assert.equal(bumpKind('47.6.2', '48.5.2'), 'minor');
  assert.equal(bumpVersion('3.2.2', 'patch'), '3.2.3');
  assert.equal(bumpVersion('3.2.2', 'minor'), '3.3.0');
  const src = "<?php\n$EM_CONF[$_EXTKEY] = [\n    'version' => '3.2.2',\n];\n";
  assert.equal(emconfVersion(setEmconfVersion(src, '3.3.0')), '3.3.0');
});

test('CHANGELOG entry is prepended and parsed back', () => {
  const first = prependChangelog(null, changelogEntry('3.2.3', '48.5.2', '48.6.0', '2026-10-15'));
  assert.match(first, /^# Changelog\n\n## 3\.2\.3 - 2026-10-15\n/);
  const second = prependChangelog(first, changelogEntry('3.3.0', '48.6.0', '49.0.0', '2026-11-01'));
  assert.equal(topChangelog(second).version, '3.3.0');
  assert.match(topChangelog(second).text, /49\.0\.0/);
  assert.doesNotMatch(topChangelog(second).text, /3\.2\.3/);
  assert.equal((second.match(/^# Changelog/gm) || []).length, 1);
});

test('release-ready gate', () => {
  const ok = {
    target: '48.6.0', fromCk: '48.5.2', contribVersion: '48.6.0', packagesVersion: '48.6.0',
    changedFiles: ['Resources/Public/Contrib/@ckeditor/ckeditor5-core.js', 'Build/ckeditor/package.json', 'ext_emconf.php', 'CHANGELOG.md', 'README.md'],
    patches: [{ name: 'ckeditor48-adapt.patch', major: 48, applied: true }],
    baseExtVersion: '3.2.2', extVersion: '3.2.3', changelog: changelogEntry('3.2.3', '48.5.2', '48.6.0', '2026-10-15'),
  };
  assert.deepEqual(releaseProblems(ok), []);
  const bad = releaseProblems({ ...ok, changedFiles: [...ok.changedFiles, 'Resources/Public/Contrib-12/x.js', 'ext_localconf.php'], contribVersion: '48.5.2', patches: [{ name: 'ckeditor48-adapt.patch', major: 48, applied: false }], extVersion: '3.3.0' });
  assert.ok(bad.some(p => p.includes('Contrib-12')));
  assert.ok(bad.some(p => p.includes('unexpected changed file: ext_localconf.php')));
  assert.ok(bad.some(p => p.includes('Contrib build reports CKEditor 48.5.2')));
  assert.ok(bad.some(p => p.includes('should be applied')));
  assert.ok(bad.some(p => p.includes('expected 3.2.3')));
  assert.ok(releaseProblems({ ...ok, patches: [{ name: 'ckeditor48-adapt.patch', major: 48, applied: true }], target: '47.9.0', contribVersion: '47.9.0', packagesVersion: '47.9.0', changelog: changelogEntry('3.2.3', '48.5.2', '47.9.0', 'x') })
    .some(p => p.includes('should not be applied')));
});

test('a failed dispatch puts the same approve comment back', () => {
  const request = {
    id: 'CKE-48.6.0-20261001', status: 'approved', targetVersion: '48.6.0',
    decision: { commentId: '9', username: 'Vivek' }, processedCommentIds: ['1', '9'], history: [],
  };
  assert.equal(revertUnstarted(request, { previousStatus: 'awaiting', commentId: '9', at: '2026-10-15T00:00:00Z' }), true);
  assert.equal(request.status, 'awaiting');
  assert.equal(request.decision, null);
  assert.deepEqual(request.processedCommentIds, ['1']);
  assert.equal(revertUnstarted({ ...request, status: 'building' }, { previousStatus: 'awaiting' }), false);
  const retry = { status: 'approved', decision: {}, processedCommentIds: ['9'], history: [] };
  revertUnstarted(retry, { previousStatus: 'failed', commentId: '9' });
  assert.equal(retry.status, 'failed');
});

test('retry: a failed request is polled again and a new approve is accepted even after expiry', () => {
  const failed = { id: 'CKE-48.6.0-20261001', status: 'failed', requestedAt: '2026-10-01T00:00:00Z', expiresAt: '2026-10-31T00:00:00Z' };
  assert.equal(pollableRequest({ requests: [{ id: 'old', status: 'pr_open' }, failed] }), failed);
  assert.equal(pollableRequest({ requests: [failed, { id: 'n', status: 'awaiting' }] }).id, 'n');
  assert.equal(pollableRequest({ requests: [{ status: 'merged' }] }), null);
  const now = Date.parse('2026-12-01T00:00:00Z');
  assert.equal(timing(failed, now), null);
  const comments = [
    { id: '1', date: String(Date.parse('2026-10-02T00:00:00Z')), comment_text: 'approve', user: { id: 7 } },
    { id: '2', date: String(Date.parse('2026-11-30T00:00:00Z')), comment_text: 'approve', user: { id: 7 } },
  ];
  const res = evaluate({ comments, request: failed, approverIds: ['7'], processedIds: ['1'], now });
  assert.equal(res.decision?.commentId, '2');
});

test('Cursor runner: prompt and arguments', () => {
  const p = buildPrompt({ target: '49.0.0', from: '48.5.2', requestId: 'CKE-49.0.0-20261101', skillPath: '.agents/skills/ckeditor-upgrade/SKILL.md' });
  assert.match(p, /NEW CKEDITOR MAJOR/);
  assert.match(p, /Do NOT commit, push/);
  assert.match(p, /48 migration patch must be applied/);
  assert.match(p, /static function \(\): array/);
  assert.match(buildPrompt({ target: '47.9.0', from: '47.6.2', requestId: 'x', skillPath: 's' }), /Do not apply Build\/ckeditor\/migrations\/ckeditor48/);
  assert.deepEqual(parseArgs(['--target', '48.6.0', '--dry-run']), { dryRun: true, help: false, target: '48.6.0' });
  assert.throws(() => parseArgs(['--target', 'latest']));
  assert.ok(fs.existsSync(path.join(ROOT, '.agents/skills/ckeditor-upgrade/SKILL.md')));
  const r = spawnSync(process.execPath, [path.join(ROOT, '.agents/agents/src/ckeditor-upgrade.mjs'), '--target', '48.6.0', '--dry-run'], { encoding: 'utf8', env: { ...process.env, CURSOR_API_KEY: '' } });
  assert.equal(r.status, 0, r.stderr);
  assert.match(r.stdout, /\[dry-run\] prompt:/);
});

test('check-javascript-modules.php: 48 patch result passes, the leaking version fails', { skip: !hasPhp && 'php not installed' }, () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'cke-jsm-'));
  execFileSync('git', ['worktree', 'add', '-q', '--detach', tmp, 'HEAD'], { cwd: ROOT });
  try {
    const script = path.join(ROOT, 'Build/Scripts/check-javascript-modules.php');
    const run = (file) => spawnSync('php', [script, file, `--ext-root=${tmp}`, '--typo3=13.4.0', '--skip-files'], { encoding: 'utf8' });
    assert.equal(run(path.join(tmp, 'Configuration/JavaScriptModules.php')).status, 0);
    execFileSync('git', ['apply', path.join(ROOT, 'Build/ckeditor/migrations/ckeditor48-adapt.patch')], { cwd: tmp });
    const patched = run(path.join(tmp, 'Configuration/JavaScriptModules.php'));
    assert.equal(patched.status, 0, patched.stderr);
    const leaking = run(path.join(ROOT, 'Tests/Automation/Fixtures/JavaScriptModules-48-leaking.php'));
    assert.equal(leaking.status, 1);
    assert.match(leaking.stderr, /overwrote the caller variable \$package/);
  } finally {
    execFileSync('git', ['worktree', 'remove', '--force', tmp], { cwd: ROOT });
  }
});
