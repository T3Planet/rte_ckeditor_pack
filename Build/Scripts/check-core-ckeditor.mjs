#!/usr/bin/env node
/**
 * Monthly check: has TYPO3 core (13.4 / 14.3) changed its bundled CKEditor 5 version?
 * If the shared build (Resources/Public/Contrib/, used by TYPO3 13 AND 14) should move to a newer
 * version, CREATE A NEW ClickUp TASK for that version in the CKEditor list (CLICKUP_LIST_ID), assigned
 * to CLICKUP_ASSIGNEE_IDS (default: CLICKUP_APPROVER_IDS). An approver comments `approve` / `skip` on it.
 * One task per version (never duplicated): existing tasks (tag ckeditor-update, open and closed) in the list
 * ARE the state; each carries a machine-readable block (see ckeditor-state.mjs).
 * Contrib-12/ (TYPO3 v12) is frozen and deliberately NOT monitored.
 *
 * Per TYPO3 line two versions are read:
 *   - branch : TYPO3/typo3 <branch> head = what the NEXT patch release will ship (early warning)
 *   - release: latest Packagist release of typo3/cms-rte-ckeditor (TYPO3-CMS/rte_ckeditor split at that tag)
 *
 * Env:
 *   EXT_DIR            extension root (default ".")
 *   REQUEST_TRIGGER    branch (default, early) | release : which core version triggers an approval request
 *   DRY_RUN=1          never call ClickUp, never write state (no task, no block update)
 *   CLICKUP_API_TOKEN, CLICKUP_LIST_ID (default 901609568667), CLICKUP_ASSIGNEE_IDS / CLICKUP_APPROVER_IDS,
 *   CLICKUP_APPROVER_NAMES (display only), CLICKUP_TASK_ID (optional: general task for warnings/info only)
 *   REQUEST_EXPIRY_DAYS default 30
 *   GITHUB_TOKEN       optional, links the core commit that changed the version
 *   CORE_VERSIONS_FILE testing only: JSON {"13.4":{"branch":"x","release":"y","releaseTag":"v.."},...} instead of network
 *   CLICKUP_API_BASE   testing only
 */
import fs from 'node:fs';
import path from 'node:path';
import { versionFromJs, major, cmp, ckNotes, sharedTarget, requestId, notify, isMain, DAY, ClickUp, DEFAULT_LIST_ID, assigneeIds, taskUrl } from './ckeditor-lib.mjs';
import { loadState, saveState, OPEN_STATUSES, withBlock, TASK_TAG } from './ckeditor-state.mjs';

export const TRACKS = ['13.4', '14.3'];
const RAW_CORE = 'https://raw.githubusercontent.com/TYPO3/typo3';
const RAW_SPLIT = 'https://raw.githubusercontent.com/TYPO3-CMS/rte_ckeditor';
const UTILS_CORE = 'typo3/sysext/rte_ckeditor/Resources/Public/Contrib/@ckeditor/ckeditor5-utils.js';
const UTILS_SPLIT = 'Resources/Public/Contrib/@ckeditor/ckeditor5-utils.js';
const UTILS_EXT = 'Resources/Public/Contrib/@ckeditor/ckeditor5-utils.js';
const PACKAGIST = 'https://repo.packagist.org/p2/typo3/cms-rte-ckeditor.json';
const COMMIT_URL = { '13.4': 'https://github.com/TYPO3/typo3/commits/13.4', '14.3': 'https://github.com/TYPO3/typo3/commits/14.3' };

// ---------- data sources ----------
async function get(url, { json = false } = {}) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    const res = await fetch(url, { headers: { 'User-Agent': 'rte-ckeditor-pack-monitor' } });
    if (res.ok) return json ? res.json() : res.text();
    if (res.status === 404) return null;
    if (attempt === 3) throw new Error(`GET ${url} -> HTTP ${res.status}`);
    await new Promise(r => setTimeout(r, 2000 * attempt));
  }
}

async function branchVersion(branch) {
  let source = `${RAW_CORE}/${branch}/${UTILS_CORE}`;
  let v = versionFromJs(await get(source));
  if (!v) {
    source = `${RAW_CORE}/${branch}/Build/package-lock.json`;
    const p = (await get(source, { json: true }))?.packages || {};
    v = p['node_modules/@ckeditor/ckeditor5-utils']?.version || p['node_modules/ckeditor5']?.version || null;
  }
  return { version: v, source };
}

let packagistCache;
async function latestRelease(track) {
  packagistCache ??= (await get(PACKAGIST, { json: true }))?.packages?.['typo3/cms-rte-ckeditor'] || [];
  const rel = packagistCache.find(r => r.version.replace(/^v/, '').startsWith(track + '.'));
  if (!rel) return null;
  const source = `${RAW_SPLIT}/${rel.version}/${UTILS_SPLIT}`;
  return { tag: rel.version, time: rel.time, version: versionFromJs(await get(source)), source };
}

async function findCoreCommit(branch) {
  const headers = { 'User-Agent': 'rte-ckeditor-pack-monitor', ...(process.env.GITHUB_TOKEN ? { Authorization: `Bearer ${process.env.GITHUB_TOKEN}` } : {}) };
  try {
    const res = await fetch(`https://api.github.com/repos/TYPO3/typo3/commits?sha=${encodeURIComponent(branch)}&path=${encodeURIComponent(UTILS_CORE)}&per_page=1`, { headers });
    if (!res.ok) return null;
    const c = (await res.json())?.[0];
    return c ? { url: c.html_url, title: c.commit.message.split('\n')[0], date: c.commit.committer.date } : null;
  } catch { return null; }
}

export function extensionVersion(extDir = process.env.EXT_DIR || '.') {
  const f = path.join(extDir, UTILS_EXT);
  return fs.existsSync(f) ? versionFromJs(fs.readFileSync(f, 'utf8')) : null;
}

export async function readCoreTracks() {
  if (process.env.CORE_VERSIONS_FILE) {
    const fx = JSON.parse(fs.readFileSync(process.env.CORE_VERSIONS_FILE, 'utf8'));
    for (const t of TRACKS) if (!fx[t]?.branch) throw new Error(`fixture lacks ${t}.branch`);
    return fx;
  }
  const tracks = {};
  for (const track of TRACKS) {
    const [b, r] = await Promise.all([branchVersion(track), latestRelease(track)]);
    if (!b.version) throw new Error(`Could not determine CKEditor version on TYPO3 ${track} (${b.source})`);
    tracks[track] = { branch: b.version, branchSource: b.source };
    if (r) Object.assign(tracks[track], { release: r.version, releaseTag: r.tag, releaseTime: r.time, releaseSource: r.source });
  }
  return tracks;
}

// ---------- pure decision logic (unit tested) ----------
export function diffTracks(oldTracks = {}, tracks) {
  const changes = [];
  for (const track of TRACKS) {
    const t = tracks[track] || {}, old = oldTracks[track] || {};
    for (const kind of ['branch', 'release']) {
      if (!t[kind] || !old[kind] || old[kind] === t[kind]) continue;
      changes.push({ track, kind, from: old[kind], to: t[kind], major: major(old[kind]) !== major(t[kind]), ...(kind === 'release' ? { tag: t.releaseTag } : {}) });
    }
  }
  return changes;
}

/**
 * Decide what to do this month.
 * returns { action: 'request'|'warn-divergent'|'none', target, supersede: [requestIds], reason }
 */
export function decide(state, tracks, ext, trigger = 'branch') {
  const s = sharedTarget(tracks['13.4']?.[trigger], tracks['14.3']?.[trigger]);
  if (s.majorDivergent) {
    const key = `${trigger}:${tracks['13.4'][trigger]}|${tracks['14.3'][trigger]}`;
    return { action: state.warnings?.divergent === key ? 'none' : 'warn-divergent', key, shared: s, reason: 'majors differ' };
  }
  const target = s.target;
  if (!target) return { action: 'none', reason: 'no target' };
  if (ext && cmp(target, ext) <= 0) return { action: 'none', target, shared: s, reason: `extension ${ext} already >= ${target}` };
  const same = state.requests.find(r => r.targetVersion === target && r.status !== 'superseded');
  if (same) return { action: 'none', target, shared: s, reason: `request ${same.id} for ${target} already exists (${same.status})` };
  const open = state.requests.filter(r => OPEN_STATUSES.includes(r.status) || r.status === 'pr_open');
  if (open.some(r => r.status === 'building')) return { action: 'none', target, shared: s, reason: 'a build is running' };
  return { action: 'request', target, shared: s, supersede: open.map(r => r.id), major: ext ? major(ext) !== major(target) : true };
}

export function approvalText({ id, target, isMajor, tracks, ext, trigger, changes, approverNames, expiresAt, commits = {} }) {
  const L = [`[CKEditor update request ${id}]`, 'TYPO3 core CKEditor 5 versions (shared build for TYPO3 13.4 + 14.3):'];
  for (const track of TRACKS) {
    const t = tracks[track] || {};
    const ch = changes.find(c => c.track === track && c.kind === 'branch');
    L.push(`- TYPO3 ${track} branch: ${ch ? `${ch.from} -> ${ch.to}` : t.branch}${commits[track] ? ` (${commits[track]})` : ''}`);
    L.push(`- TYPO3 ${track} latest release ${t.releaseTag ?? 'n/a'}: ${t.release ?? 'n/a'}`);
  }
  L.push(`Shared build target (${trigger}): ${target}.${isMajor ? ' MAJOR UPDATE: draft PR + manual plugin review.' : ''}`);
  L.push(`Extension currently bundles: ${ext ?? 'n/a'}`);
  L.push(`Release notes: ${ckNotes(target)}`);
  L.push('Approve or skip by commenting on THIS task with exactly one line:', '  approve', '  skip', `(\`approve ${id}\` / \`skip ${id}\` also work.)`);
  L.push('On approve the rebuild workflow builds the shared Contrib/ at this version, opens a PR and posts the PR link here.');
  L.push(`Accepted from: ${approverNames || 'configured approvers (CLICKUP_APPROVER_IDS)'}. Expires: ${expiresAt.slice(0, 10)}.`);
  return L.join('\n');
}

export const taskTitle = (target) => `Update CKEditor to ${target} (TYPO3 13.4/14.3)`;

export function divergentText(tracks, trigger) {
  const a = tracks['13.4'][trigger], b = tracks['14.3'][trigger];
  const s = sharedTarget(a, b);
  return `[CKEditor version warning] TYPO3 13.4 uses ${a}, 14.3 uses ${b} (different majors, ${trigger}). A single shared build cannot target both; automatic rebuild disabled. Team decision needed (keep ${s.lower} or split the build).`;
}

function reportText(state, tracks, ext, changes) {
  const L = [`TYPO3 core CKEditor 5 version check (${new Date().toISOString().slice(0, 10)})`];
  if (changes.length) for (const c of changes) L.push(`- TYPO3 ${c.track} ${c.kind}${c.tag ? ` ${c.tag}` : ''}: ${c.from} -> ${c.to}${c.major ? ' [MAJOR]' : ''}`);
  else L.push('No change since last check.');
  for (const t of TRACKS) L.push(`- TYPO3 ${t}: release ${tracks[t].releaseTag ?? 'n/a'} ships ${tracks[t].release ?? 'n/a'}; branch has ${tracks[t].branch}`);
  L.push(`- Extension Contrib/ bundles ${ext ?? 'n/a'}`);
  return L.join('\n');
}

// ---------- ClickUp helpers ----------
/** Comment on the request's own task (legacy requests: reply to their comment on CLICKUP_TASK_ID). */
async function notifyRequest(r, text, dryRun) {
  if (r.clickupTaskId) return notify(text, { dryRun, taskId: r.clickupTaskId });
  if (r.requestCommentId) return notify(text, { dryRun, replyTo: r.requestCommentId });
  console.log(text);
}
/** Warnings/info: posted on CLICKUP_TASK_ID if configured, otherwise only logged. */
async function info(text, dryRun, { once = false } = {}) {
  if (process.env.CLICKUP_TASK_ID) {
    // `once`: stateless de-duplication, skip if the same text was already posted on that task
    if (once && !dryRun && (await new ClickUp().getTaskComments(process.env.CLICKUP_TASK_ID)).some(c => (c.comment_text || '').trim() === text.trim())) {
      console.log('Same warning already posted, not repeated.'); return;
    }
    return notify(text, { dryRun });
  }
  console.log(`(no CLICKUP_TASK_ID, not posted)\n${text}`);
}

// ---------- main ----------
export async function main() {
  const env = process.env;
  const dryRun = ['1', 'true'].includes(env.DRY_RUN);
  const trigger = env.REQUEST_TRIGGER === 'release' ? 'release' : 'branch';
  const state = await loadState();
  const now = new Date(env.NOW || Date.now());
  const ext = extensionVersion();
  const tracks = await readCoreTracks();
  const changes = diffTracks(state.tracks, tracks);
  const d = decide(state, tracks, ext, trigger);

  const report = reportText(state, tracks, ext, changes);
  console.log(report);
  console.log(`\nDecision: ${d.action}${d.reason ? ` (${d.reason})` : ''}${d.target ? ` target=${d.target}` : ''}`);
  if (env.GITHUB_STEP_SUMMARY) fs.appendFileSync(env.GITHUB_STEP_SUMMARY, '```\n' + report + `\nDecision: ${d.action} ${d.reason ?? ''}\n` + '```\n');

  if (d.action === 'request') {
    for (const oldId of d.supersede) {
      const r = state.requests.find(x => x.id === oldId);
      await notifyRequest(r, `[${oldId}] superseded by a newer CKEditor version (${d.target}). Do not approve this task any more.`, dryRun);
      r.status = 'superseded';
      r.history = [...(r.history || []), { status: 'superseded', at: now.toISOString() }];
    }
    const id = requestId(d.target, now);
    const expiresAt = new Date(now.getTime() + Number(env.REQUEST_EXPIRY_DAYS || 30) * DAY).toISOString();
    const commits = {};
    if (!env.CORE_VERSIONS_FILE) for (const c of changes.filter(c => c.kind === 'branch')) commits[c.track] = (await findCoreCommit(c.track))?.url || COMMIT_URL[c.track];
    const text = approvalText({ id, target: d.target, isMajor: d.major, tracks, ext, trigger, changes, approverNames: env.CLICKUP_APPROVER_NAMES, expiresAt, commits });
    const name = taskTitle(d.target);
    const listId = env.CLICKUP_LIST_ID || DEFAULT_LIST_ID;
    const assignees = assigneeIds(env);
    const request = {
      id, targetVersion: d.target, trigger, major: d.major, status: 'awaiting',
      requestedAt: now.toISOString(), requestCommentId: null,
      expiresAt, decision: null, build: null, prUrl: null, remindersSent: 0, processedCommentIds: [],
      fromVersion: ext, tracks: JSON.parse(JSON.stringify(tracks)),
      history: [{ status: 'awaiting', at: now.toISOString() }],
    };
    let task = null;
    if (dryRun) console.log(`\n[DRY_RUN] ClickUp new task in list ${listId} "${name}" assigned to ${assignees.join(',') || '(nobody)'}:\n${text}\n`);
    else task = await new ClickUp().createTask(listId, { name, description: withBlock(text, request), assignees, tags: [TASK_TAG] });
    if (task?.id) console.log(`ClickUp task created: ${task.url || taskUrl(task.id)}`);
    if (task?.id) state.requests.push({ ...request, description: withBlock(text, request), clickupTaskId: String(task.id), clickupTaskUrl: task.url || taskUrl(task.id) });
    console.log(`Approval request ${id} ${dryRun ? '(dry run) ' : ''}created.`);
    if (env.GITHUB_OUTPUT) fs.appendFileSync(env.GITHUB_OUTPUT, `request_id=${id}\ntask_id=${task?.id ?? ''}\n`);
    if (env.GITHUB_STEP_SUMMARY && task?.id) fs.appendFileSync(env.GITHUB_STEP_SUMMARY, `ClickUp task: ${task.url || taskUrl(task.id)}\n`);
  } else if (d.action === 'warn-divergent') {
    await info(divergentText(tracks, trigger), dryRun, { once: true });
    state.warnings = { ...(state.warnings || {}), divergent: d.key };
  } else if (changes.length) {
    await info(report, dryRun); // informational, nothing to approve
  }

  if (env.GITHUB_OUTPUT) fs.appendFileSync(env.GITHUB_OUTPUT, `changed=${changes.length > 0}\naction=${d.action}\n`);
  state.lastCheckedAt = now.toISOString();
  state.extension = { Contrib: ext };
  state.tracks = tracks;
  state.sharedTarget = { branch: sharedTarget(tracks['13.4'].branch, tracks['14.3'].branch).target, release: sharedTarget(tracks['13.4'].release, tracks['14.3'].release).target };
  if (dryRun) { console.log('DRY_RUN: state not written.'); return; }
  const writes = await saveState(state);
  console.log(`State saved in ClickUp (${writes} task block(s) updated).`);
}

if (isMain(import.meta.url)) main().catch(e => { console.error(e); process.exit(1); });
