#!/usr/bin/env node
/**
 * Daily poller: reads approve/skip comments on the per-version ClickUp task the monitor created
 * (request.clickupTaskId; legacy requests without one use CLICKUP_TASK_ID).
 *
 * Rules (PLAN.md section 6.2 / 7):
 *  - only comments newer than the request, not processed before
 *  - text must be exactly `approve` / `skip` (optionally followed by the request ID; case-insensitive, one line)
 *  - author's numeric ClickUp user id must be in CLICKUP_APPROVER_IDS (comma separated)
 *  - if an ID is given it must be the CURRENT request id; earliest valid comment wins
 *  - invalid commands get one explanatory reply; every evaluated command id is recorded
 *  - reminder after REMIND_AFTER_DAYS (7), expiry after the request's expiresAt (30 days)
 *  - approve -> status approved + `gh workflow run ckeditor-rebuild.yml -f request_id=.. -f target_version=..`
 *  - retry: a request whose rebuild FAILED is polled again; a new `approve` on the same task restarts
 *    the rebuild (`skip` closes it). No reminders/expiry for failed requests.
 * Comment text is parsed here only; it never reaches a shell or a workflow expression.
 *
 * State: read from / written to the per-version ClickUp tasks (ckeditor-state.mjs), no file, no branch.
 * Env: CLICKUP_LIST_ID, CLICKUP_API_TOKEN, CLICKUP_TASK_ID, CLICKUP_APPROVER_IDS, DRY_RUN, NOW (testing),
 *      DISPATCH_IN_SCRIPT=1 + GH_BIN (default gh) to call `gh workflow run` directly (otherwise outputs for the workflow), GITHUB_REF_NAME (branch to run the rebuild on), GITHUB_SERVER_URL/REPOSITORY/RUN_ID
 */
import { execFileSync } from 'node:child_process';
import { ClickUp, notify, isMain, DAY } from './ckeditor-lib.mjs';
import { loadState, saveState, findRequest } from './ckeditor-state.mjs';

export const COMMAND_RE = /^\s*(approve|skip)(?:\s+(CKE-[\w.-]+))?\s*[.!]?\s*$/i;

/** Normalise ClickUp comment text (comment_text, or the comment[] rich text parts). */
export function commentText(c) {
  if (typeof c.comment_text === 'string') return c.comment_text;
  if (Array.isArray(c.comment)) return c.comment.map(p => p.text || '').join('');
  return '';
}

export function parseCommand(text) {
  const m = String(text ?? '').replace(/\u00a0/g, ' ').trim().match(COMMAND_RE);
  return m ? { action: m[1].toLowerCase(), id: m[2] || null } : null;
}

export const parseApprovers = (s) => String(s || '').split(/[\s,;]+/).filter(Boolean).map(String);

/**
 * Pure evaluation.
 * @returns {{ decision: null|{action,commentId,userId,username,date}, rejections: Array<{commentId,reason,replyTo}>, processed: string[] }}
 */
export function evaluate({ comments, request, approverIds, processedIds = [], now = Date.now() }) {
  const requestedAt = Date.parse(request.requestedAt);
  const expired = request.status !== 'failed' && request.expiresAt && now >= Date.parse(request.expiresAt);
  const processed = new Set(processedIds.map(String));
  const candidates = comments
    .filter(c => !processed.has(String(c.id)) && Number(c.date) > requestedAt)
    .map(c => ({ c, cmd: parseCommand(commentText(c)) }))
    .filter(x => x.cmd)
    .sort((a, b) => Number(a.c.date) - Number(b.c.date));

  const out = { decision: null, rejections: [], processed: [] };
  for (const { c, cmd } of candidates) {
    const id = String(c.id), userId = String(c.user?.id ?? '');
    out.processed.push(id);
    if (out.decision) continue; // earliest valid wins, later ones are just recorded
    let reason = null;
    if (!approverIds.includes(userId)) reason = `user ${c.user?.username || userId} is not an allowed approver`;
    else if (cmd.id && cmd.id !== request.id) reason = `request id ${cmd.id} does not match the open request ${request.id}`;
    else if (expired) reason = `request ${request.id} expired on ${request.expiresAt.slice(0, 10)}`;
    if (reason) { out.rejections.push({ commentId: id, reason, replyTo: c.parentId || id }); continue; }
    out.decision = { action: cmd.action, commentId: id, userId, username: c.user?.username || userId, date: new Date(Number(c.date)).toISOString() };
  }
  return out;
}

/** The request the poller looks at: awaiting approval, else the latest failed one (retry by a new `approve`). */
export function pollableRequest(state) {
  return state.requests.find(r => r.status === 'awaiting')
    || [...state.requests].reverse().find(r => r.status === 'failed')
    || null;
}

/**
 * Dispatch never started (gh workflow run failed). Put the request back so the same
 * approve comment is eligible on the next poll. No-op unless status is still `approved`
 * (a rebuild that already moved to building/failed is left alone).
 */
export function revertUnstarted(request, { previousStatus, commentId, at = new Date().toISOString() } = {}) {
  if (!request || request.status !== 'approved') return false;
  const status = previousStatus === 'failed' ? 'failed' : 'awaiting';
  request.status = status;
  request.decision = null;
  if (commentId) {
    request.processedCommentIds = (request.processedCommentIds || []).filter(id => String(id) !== String(commentId));
  }
  request.history = [...(request.history || []), { status, at, revert: 'dispatch-failed' }];
  return true;
}

/** Reminder / expiry when there is no decision. */
export function timing(request, now = Date.now(), remindAfterDays = 7) {
  if (request.status === 'failed') return null;
  if (request.expiresAt && now >= Date.parse(request.expiresAt)) return 'expire';
  if (!request.remindersSent && now - Date.parse(request.requestedAt) >= remindAfterDays * DAY) return 'remind';
  return null;
}

export async function main() {
  const env = process.env;
  const dryRun = ['1', 'true'].includes(env.DRY_RUN);
  const now = env.NOW ? Date.parse(env.NOW) : Date.now();
  const state = await loadState();
  const request = pollableRequest(state);
  if (!request) { console.log('No request awaiting approval (or a retry after a failed rebuild).'); return; }
  const retry = request.status === 'failed';

  const approverIds = parseApprovers(env.CLICKUP_APPROVER_IDS);
  if (!approverIds.length) throw new Error('CLICKUP_APPROVER_IDS is empty: nobody could approve.');
  const client = new ClickUp();
  const requestedAt = Date.parse(request.requestedAt);
  const taskId = request.clickupTaskId || env.CLICKUP_TASK_ID;
  if (!taskId) throw new Error(`Request ${request.id} has no ClickUp task id`);
  const say = (text, replyTo) => notify(text, { dryRun, client, taskId, replyTo });
  const top = await client.getTaskComments(taskId, requestedAt);
  let replies = [];
  if (request.requestCommentId) replies = (await client.getReplies(request.requestCommentId)).map(r => ({ ...r, parentId: request.requestCommentId }));
  const comments = [...top, ...replies];
  console.log(`Fetched ${top.length} task comments and ${replies.length} replies for ${request.id}.`);

  const res = evaluate({ comments, request, approverIds, processedIds: request.processedCommentIds || [], now });
  const runUrl = env.GITHUB_RUN_ID ? `${env.GITHUB_SERVER_URL}/${env.GITHUB_REPOSITORY}/actions/runs/${env.GITHUB_RUN_ID}` : '(local run)';

  for (const r of res.rejections) {
    console.log(`Rejected comment ${r.commentId}: ${r.reason}`);
    await say(`Not accepted: ${r.reason}. Comment "approve" or "skip" on this task (allowed approvers only).`, r.replyTo);
  }
  request.processedCommentIds = [...(request.processedCommentIds || []), ...res.processed];

  if (res.decision?.action === 'approve') {
    const previousStatus = request.status;
    request.status = 'approved';
    request.decision = res.decision;
    request.history = [...(request.history || []), { status: 'approved', at: new Date(now).toISOString(), ...(retry ? { retry: true } : {}) }];
    if (!dryRun) await saveState(state); // the rebuild's guard reads "approved" from the task
    try {
      if (dryRun) {
        console.log(`[DRY_RUN] would dispatch ckeditor-rebuild.yml for ${request.id} (${request.targetVersion})`);
      } else if (env.DISPATCH_IN_SCRIPT === '1') {
        const args = ['workflow', 'run', 'ckeditor-rebuild.yml', '-f', `request_id=${request.id}`, '-f', `target_version=${request.targetVersion}`];
        if (env.GITHUB_REF_NAME) args.push('--ref', env.GITHUB_REF_NAME);
        execFileSync(env.GH_BIN || 'gh', args, { stdio: 'inherit' });
      } else if (env.GITHUB_OUTPUT) {
        // state is saved on the ClickUp task first, then the workflow runs `gh workflow run` (so the rebuild sees "approved")
        (await import('node:fs')).appendFileSync(env.GITHUB_OUTPUT, `dispatch=true\nrequest_id=${request.id}\ntarget_version=${request.targetVersion}\nprevious_status=${previousStatus}\ncomment_id=${res.decision.commentId}\n`);
      }
    } catch (e) {
      // gh never started the rebuild. Put the same approve comment back in play.
      revertUnstarted(request, { previousStatus, commentId: res.decision.commentId, at: new Date(now).toISOString() });
      if (!dryRun) await saveState(state);
      throw e;
    }
    await say(`${retry ? 'Retry approved' : 'Approved'} by ${res.decision.username} (${request.id}). Build started for CKEditor ${request.targetVersion}: ${runUrl.replace(/\/runs\/\d+$/, '/workflows/ckeditor-rebuild.yml')}. The PR link will be posted here.`, request.requestCommentId || undefined);
  } else if (res.decision?.action === 'skip') {
    request.status = 'skipped';
    request.decision = res.decision;
    request.history = [...(request.history || []), { status: 'skipped', at: new Date(now).toISOString() }];
    await say(`Skipped by ${res.decision.username}: ${request.id} (CKEditor ${request.targetVersion}) will not be requested again.`, request.requestCommentId || undefined);
  } else {
    const t = timing(request, now, Number(env.REMIND_AFTER_DAYS || 7));
    if (t === 'expire') {
      request.status = 'expired';
      request.history = [...(request.history || []), { status: 'expired', at: new Date(now).toISOString() }];
      await say(`${request.id} expired without approval (CKEditor ${request.targetVersion}). Re-run the monitor manually to ask again.`, request.requestCommentId || undefined);
    } else if (t === 'remind') {
      request.remindersSent = (request.remindersSent || 0) + 1;
      await say(`Reminder: ${request.id} (CKEditor ${request.targetVersion}) is waiting. Comment "approve" or "skip" on this task. Expires ${request.expiresAt.slice(0, 10)}.`, request.requestCommentId || undefined);
    } else console.log('No decision yet.');
  }
  if (dryRun) { console.log('DRY_RUN: state not written.'); return; }
  await saveState(state);
  if (env.GITHUB_OUTPUT) (await import('node:fs')).appendFileSync(env.GITHUB_OUTPUT, `status=${request.status}\n`);
}

/** CLI: `node ckeditor-approval.mjs --revert-dispatch` after `gh workflow run` failed. */
export async function revertDispatchCli() {
  const env = process.env;
  const state = await loadState();
  const request = findRequest(state, env.REQUEST_ID);
  if (!revertUnstarted(request, { previousStatus: env.PREVIOUS_STATUS, commentId: env.COMMENT_ID })) {
    console.log(`Request ${env.REQUEST_ID} is not an unstarted approval (${request?.status ?? 'missing'}).`);
    return;
  }
  await saveState(state);
  const taskId = request.clickupTaskId || env.CLICKUP_TASK_ID;
  if (taskId) {
    try {
      await notify(`Build for CKEditor ${request.targetVersion} (${request.id}) did not start. The same approval will be retried on the next poll.`, { taskId });
    } catch (e) {
      console.error(`Approval reverted, but the ClickUp note failed: ${e.message || e}`);
    }
  }
  console.log(`${request.id} -> ${request.status} (dispatch failed)`);
}

if (isMain(import.meta.url)) {
  const run = process.argv.includes('--revert-dispatch') ? revertDispatchCli() : main();
  run.catch(e => { console.error(e); process.exit(1); });
}
