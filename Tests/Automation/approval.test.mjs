import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { evaluate, parseCommand, parseApprovers, timing, commentText } from '../../Build/Scripts/ckeditor-approval.mjs';

const fx = JSON.parse(fs.readFileSync(new URL('./Fixtures/comments.json', import.meta.url)));
const C = fx.comments;
const run = (list, extra = {}) => evaluate({ comments: list, request: fx.request, approverIds: fx.approvers, now: Date.parse('2026-11-02T00:00:00Z'), ...extra });

test('parseCommand', () => {
  assert.deepEqual(parseCommand('approve CKE-48.5.2-20261101'), { action: 'approve', id: 'CKE-48.5.2-20261101' });
  assert.deepEqual(parseCommand('  SKIP   CKE-48.5.2-20261101  '), { action: 'skip', id: 'CKE-48.5.2-20261101' });
  assert.deepEqual(parseCommand('approve'), { action: 'approve', id: null });
  assert.deepEqual(parseCommand(' Skip. '), { action: 'skip', id: null });
  assert.equal(parseCommand('approve later'), null);
  assert.equal(parseCommand('please approve CKE-48.5.2-20261101'), null);
  assert.equal(parseCommand('approve CKE-48.5.2-20261101 and more'), null);
  assert.equal(parseCommand('approve CKE-1.0.0-1\nskip CKE-1.0.0-1'), null); // multi-line (e.g. our own request text)
  assert.equal(parseCommand('approve CKE-1.0.0-1; rm -rf /'), null);
});

test('parseApprovers', () => assert.deepEqual(parseApprovers(' 94966947, 123 ;456'), ['94966947', '123', '456']));
test('commentText supports rich text parts', () => assert.equal(commentText(C.richTextReply), 'approve CKE-48.5.2-20261101'));

test('valid approve from an allowed user', () => {
  const r = run([C.approve]);
  assert.equal(r.decision.action, 'approve'); assert.equal(r.decision.commentId, '5'); assert.equal(r.decision.username, 'Vivek');
  assert.deepEqual(r.rejections, []);
});

test('comments older than the request are ignored', () => {
  const r = run([C.oldApprove]);
  assert.equal(r.decision, null); assert.deepEqual(r.processed, []);
});

test('disallowed user is rejected with a reason', () => {
  const r = run([C.strangerApprove]);
  assert.equal(r.decision, null);
  assert.match(r.rejections[0].reason, /not an allowed approver/);
  assert.deepEqual(r.processed, ['2']);
});

test('wrong request id is rejected', () => {
  const r = run([C.wrongId]);
  assert.match(r.rejections[0].reason, /does not match/);
});

test('free text mentioning the command is ignored', () => {
  assert.equal(run([C.chatter]).decision, null);
  assert.deepEqual(run([C.chatter]).rejections, []);
});

test('earliest valid comment wins, later ones are only recorded', () => {
  const r = run([C.laterSkip, C.approve, C.strangerApprove, C.wrongId]);
  assert.equal(r.decision.action, 'approve');
  assert.equal(r.rejections.length, 2);
  assert.deepEqual(r.processed.sort(), ['2', '3', '5', '6']);
});

test('skip', () => assert.equal(run([C.skip]).decision.action, 'skip'));

test('duplicates: processed ids are not evaluated again', () => {
  const r = run([C.approve, C.strangerApprove], { processedIds: ['5', '2'] });
  assert.equal(r.decision, null); assert.deepEqual(r.rejections, []);
});

test('threaded reply counts and replies go to the thread parent', () => {
  const r = run([C.richTextReply]);
  assert.equal(r.decision.action, 'approve');
  const s = run([{ ...C.richTextReply, user: { id: 1, username: 'x' } }]);
  assert.equal(s.rejections[0].replyTo, '900');
});

test('approval after expiry is rejected', () => {
  const r = run([C.approve], { now: Date.parse('2026-12-02T00:00:00Z') });
  assert.equal(r.decision, null); assert.match(r.rejections[0].reason, /expired/);
});

test('timing: reminder after 7 days (once), expiry after 30', () => {
  const req = { ...fx.request, remindersSent: 0 };
  assert.equal(timing(req, Date.parse('2026-11-03T00:00:00Z')), null);
  assert.equal(timing(req, Date.parse('2026-11-08T04:00:00Z')), 'remind');
  assert.equal(timing({ ...req, remindersSent: 1 }, Date.parse('2026-11-09T00:00:00Z')), null);
  assert.equal(timing(req, Date.parse('2026-12-01T03:30:00Z')), 'expire');
});
