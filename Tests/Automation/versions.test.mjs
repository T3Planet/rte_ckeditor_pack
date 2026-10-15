import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cmp, major, sharedTarget, requestId, versionFromJs } from '../../Build/Scripts/ckeditor-lib.mjs';
import { decide, diffTracks } from '../../Build/Scripts/check-core-ckeditor.mjs';
import { emptyState } from '../../Build/Scripts/ckeditor-state.mjs';

const tracks = (b13, b14, r = '47.6.0') => ({ '13.4': { branch: b13, release: r, releaseTag: 'v13.4.35' }, '14.3': { branch: b14, release: r, releaseTag: 'v14.3.7' } });

test('version compare', () => {
  assert.ok(cmp('48.5.2', '47.6.2') > 0);
  assert.ok(cmp('47.6.10', '47.6.9') > 0);
  assert.equal(cmp('47.6.2', '47.6.2'), 0);
  assert.ok(cmp('47.6.0', '47.10.0') < 0);
  assert.equal(major('48.5.2'), 48);
});

test('version is read from compiled ckeditor5-utils.js', () => {
  assert.equal(versionFromJs('let h;const Ci="47.6.2";export{Ci as version}'), '47.6.2');
  assert.equal(versionFromJs('nothing here'), null);
});

test('shared target rules', () => {
  assert.deepEqual(sharedTarget('48.5.2', '48.5.2'), { target: '48.5.2', divergent: false, majorDivergent: false });
  assert.equal(sharedTarget('48.5.2', '48.6.0').target, '48.6.0');           // same major -> higher
  assert.equal(sharedTarget('48.5.2', '48.6.0').divergent, true);
  const d = sharedTarget('47.6.4', '48.5.2');
  assert.equal(d.majorDivergent, true); assert.equal(d.target, null); assert.equal(d.lower, '47.6.4');
  assert.equal(sharedTarget(null, '48.5.2').target, '48.5.2');
});

test('request id format', () => {
  assert.equal(requestId('48.5.2', new Date('2026-11-01T03:30:00Z')), 'CKE-48.5.2-20261101');
});

test('diffTracks reports changes only when a previous value exists', () => {
  assert.deepEqual(diffTracks({}, tracks('48.5.2', '48.5.2')), []);
  const c = diffTracks(tracks('47.6.0', '47.6.0'), tracks('48.5.2', '47.6.0'));
  assert.equal(c.length, 1); assert.equal(c[0].track, '13.4'); assert.equal(c[0].major, true);
});

test('decide: request when shared target is newer than the extension', () => {
  const d = decide(emptyState(), tracks('48.5.2', '48.5.2'), '47.6.2');
  assert.equal(d.action, 'request'); assert.equal(d.target, '48.5.2'); assert.equal(d.major, true);
});

test('decide: minor update is not major', () => {
  const d = decide(emptyState(), tracks('47.7.0', '47.6.9'), '47.6.2');
  assert.equal(d.action, 'request'); assert.equal(d.target, '47.7.0'); assert.equal(d.major, false);
});

test('decide: nothing when extension is up to date, or release trigger is older', () => {
  assert.equal(decide(emptyState(), tracks('47.6.2', '47.6.2'), '47.6.2').action, 'none');
  assert.equal(decide(emptyState(), tracks('48.5.2', '48.5.2'), '47.6.2', 'release').action, 'none');
});

test('decide: do not ask twice for the same version (skipped/expired/open)', () => {
  for (const status of ['awaiting', 'skipped', 'expired', 'pr_open', 'failed']) {
    const s = emptyState(); s.requests.push({ id: 'CKE-48.5.2-20261101', targetVersion: '48.5.2', status });
    assert.equal(decide(s, tracks('48.5.2', '48.5.2'), '47.6.2').action, 'none', status);
  }
});

test('decide: newer version supersedes the open request', () => {
  const s = emptyState(); s.requests.push({ id: 'CKE-48.5.2-20261101', targetVersion: '48.5.2', status: 'awaiting' });
  const d = decide(s, tracks('48.6.0', '48.6.0'), '47.6.2');
  assert.equal(d.action, 'request'); assert.deepEqual(d.supersede, ['CKE-48.5.2-20261101']);
});

test('decide: no new request while a build is running', () => {
  const s = emptyState(); s.requests.push({ id: 'CKE-48.5.2-20261101', targetVersion: '48.5.2', status: 'building' });
  assert.equal(decide(s, tracks('48.6.0', '48.6.0'), '47.6.2').action, 'none');
});

test('decide: diverging majors -> warning once', () => {
  const s = emptyState();
  const d = decide(s, tracks('47.6.4', '48.5.2'), '47.6.2');
  assert.equal(d.action, 'warn-divergent');
  s.warnings.divergent = d.key;
  assert.equal(decide(s, tracks('47.6.4', '48.5.2'), '47.6.2').action, 'none');
});
