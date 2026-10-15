#!/usr/bin/env node
/**
 * State of the CKEditor automation, kept in ClickUp (no git branch, no file).
 *
 * Source of truth: the per-version tasks (tag `ckeditor-update`) in CLICKUP_LIST_ID, open AND closed.
 * Each task description ends with one machine-readable line inside a fenced block:
 *
 *   ```ckeditor-automation
 *   ckeditor-automation-state: {"id":"CKE-48.5.2-20261015","targetVersion":"48.5.2","status":"awaiting",...}
 *   ```
 *
 * The JSON is the request record (id, targetVersion, status, prUrl, build, decision, processedCommentIds,
 * history, ...). The newest task additionally carries `meta` (lastCheckedAt, core tracks, divergence
 * warning key, extension version) for the monitor. The block is rewritten via PUT /task/{id}
 * (markdown_content); the human-readable text above it is kept.
 * Tasks without a block (created before this change) are read as legacy requests from their title
 * (`Update CKEditor to <v> (TYPO3 13.4/14.3)`): status `awaiting` while the task is open.
 *
 * Library: loadState(), saveState(), openRequest(), findRequest(), setStatus(), parseBlock(), withBlock()
 * CLI (used by the workflows):
 *   node ckeditor-state.mjs get <requestId>                 -> prints request JSON
 *   node ckeditor-state.mjs status <requestId> <status> [key=value ...]
 *   node ckeditor-state.mjs require-status <requestId> <status>[,<status>]   exit 1 if not matching
 * Env: CLICKUP_API_TOKEN, CLICKUP_LIST_ID (default 901609568667), CLICKUP_API_BASE (testing)
 */
import { ClickUp, DEFAULT_LIST_ID, isMain, requestId, taskUrl } from './ckeditor-lib.mjs';

export const STATUSES = ['awaiting', 'approved', 'building', 'pr_open', 'merged', 'skipped', 'expired', 'superseded', 'failed'];
export const OPEN_STATUSES = ['awaiting', 'approved', 'building'];
export const TASK_TAG = 'ckeditor-update';
export const BLOCK_KEY = 'ckeditor-automation-state';
const BLOCK_RE = /\n*```ckeditor-automation[^\n]*\n[\s\S]*?\n```\s*$/;
const LINE_RE = new RegExp(`${BLOCK_KEY}:\\s*(\\{.*\\})\\s*$`, 'm');
const TITLE_RE = /^Update CKEditor to (\d+\.\d+\.\d+) \(TYPO3/;

export function emptyState() {
  return { schemaVersion: 2, lastCheckedAt: null, extension: { Contrib: null }, tracks: {}, sharedTarget: {}, requests: [], warnings: {} };
}

/** Parse the JSON line from a task description (markdown or plain text). */
export function parseBlock(text) {
  const m = String(text || '').match(LINE_RE);
  if (!m) return null;
  try { return JSON.parse(m[1]); } catch { return null; }
}

/** Description with the block replaced/appended (human text kept). */
export function withBlock(text, data) {
  const human = String(text || '').replace(BLOCK_RE, '').replace(LINE_RE, '').trimEnd();
  return `${human}\n\n\`\`\`ckeditor-automation\n${BLOCK_KEY}: ${JSON.stringify(data)}\n\`\`\`\n`;
}

const META_KEYS = ['lastCheckedAt', 'extension', 'tracks', 'sharedTarget', 'warnings'];
const loaded = new WeakMap(); // state -> Map(taskId -> { desc, json })

/** Turn one ClickUp task into a request record (or null if it is not an automation task). */
export function requestFromTask(task) {
  const desc = task.markdown_description ?? task.description ?? '';
  const data = parseBlock(desc) || parseBlock(task.description);
  const closed = task.status?.type === 'closed' || task.status?.type === 'done';
  if (data?.id) return { ...data, clickupTaskId: String(task.id), clickupTaskUrl: task.url || taskUrl(task.id) };
  const m = String(task.name || '').match(TITLE_RE);
  if (!m) return null;
  const created = new Date(Number(task.date_created) || Date.now());
  return {
    id: requestId(m[1], created), targetVersion: m[1], trigger: 'branch', major: null, legacy: true,
    status: closed ? 'merged' : 'awaiting', requestedAt: created.toISOString(),
    expiresAt: new Date(created.getTime() + 30 * 864e5).toISOString(), decision: null, build: null, remindersSent: 0,
    clickupTaskId: String(task.id), clickupTaskUrl: task.url || taskUrl(task.id), history: [],
  };
}

/** All tasks of the list with the automation tag (open and closed, all pages). */
export async function listTasks(client, listId) {
  const out = [];
  for (let page = 0; page < 20; page++) {
    const qs = `?include_closed=true&subtasks=true&include_markdown_description=true&tags[]=${encodeURIComponent(TASK_TAG)}&page=${page}`;
    const { tasks = [], last_page } = await client.request('GET', `/list/${encodeURIComponent(listId)}/task${qs}`);
    out.push(...tasks);
    if (last_page !== false || tasks.length < 100) break;
  }
  return out;
}

export async function loadState({ client = new ClickUp(), listId = process.env.CLICKUP_LIST_ID || DEFAULT_LIST_ID } = {}) {
  const state = emptyState();
  const snap = new Map();
  let metaAt = '';
  for (const task of await listTasks(client, listId)) {
    const r = requestFromTask(task);
    if (!r) continue;
    const { meta, ...req } = r;
    if (meta && String(meta.lastCheckedAt || '') >= metaAt) { metaAt = String(meta.lastCheckedAt || ''); Object.assign(state, pick(meta)); }
    state.requests.push(req);
    snap.set(req.clickupTaskId, { desc: task.markdown_description ?? task.description ?? '', json: r.legacy ? null : JSON.stringify(blockData(r)) });
  }
  state.requests.sort((a, b) => String(a.requestedAt).localeCompare(String(b.requestedAt)));
  loaded.set(state, snap);
  return state;
}

const pick = (o) => Object.fromEntries(META_KEYS.filter(k => o[k] !== undefined).map(k => [k, o[k]]));
function blockData(r) {
  const { clickupTaskId, clickupTaskUrl, legacy, description, ...rest } = r;
  return rest;
}

/** Write changed request blocks back; the newest request's task also carries the monitor meta. */
export async function saveState(state, { client = new ClickUp() } = {}) {
  const snap = loaded.get(state) || new Map();
  const withTask = state.requests.filter(r => r.clickupTaskId);
  const newest = withTask[withTask.length - 1];
  let writes = 0;
  for (const r of withTask) {
    if (Array.isArray(r.processedCommentIds)) r.processedCommentIds = [...new Set(r.processedCommentIds.map(String))].slice(-200);
    const data = { ...blockData(r), ...(r === newest ? { meta: pick(state) } : {}) };
    const json = JSON.stringify(data);
    const prev = snap.get(r.clickupTaskId);
    if (prev?.json === json) continue;
    const desc = withBlock(prev?.desc ?? r.description ?? '', data);
    await client.updateTask(r.clickupTaskId, { markdown_content: desc });
    snap.set(r.clickupTaskId, { desc, json });
    delete r.legacy; delete r.description;
    writes++;
  }
  loaded.set(state, snap);
  return writes;
}

export const findRequest = (state, id) => state.requests.find(r => r.id === id) || null;
export const openRequest = (state) => state.requests.find(r => OPEN_STATUSES.includes(r.status)) || null;

export function setStatus(state, id, status, extra = {}) {
  if (!STATUSES.includes(status)) throw new Error(`Unknown status ${status}`);
  const r = findRequest(state, id);
  if (!r) throw new Error(`Request ${id} not found`);
  r.status = status;
  r.history = [...(r.history || []), { status, at: new Date().toISOString() }];
  Object.assign(r, extra);
  return r;
}

async function cli() {
  const [cmd, id, ...rest] = process.argv.slice(2);
  const state = await loadState();
  switch (cmd) {
    case 'get': {
      const r = findRequest(state, id);
      if (!r) { console.error(`Request ${id} not found`); process.exit(1); }
      console.log(JSON.stringify(r));
      return;
    }
    case 'require-status': {
      const r = findRequest(state, id);
      const ok = r && rest[0].split(',').includes(r.status);
      if (!ok) { console.error(`Request ${id} has status ${r?.status ?? '(missing)'}, expected ${rest[0]}`); process.exit(1); }
      return;
    }
    case 'status': {
      const [status, ...kv] = rest;
      const extra = {};
      for (const p of kv) { const i = p.indexOf('='); extra[p.slice(0, i)] = p.slice(i + 1); }
      if (extra.build) extra.build = JSON.parse(extra.build);
      setStatus(state, id, status, extra);
      await saveState(state);
      console.log(`${id} -> ${status}`);
      return;
    }
    default:
      console.error('Usage: ckeditor-state.mjs get|status|require-status ...');
      process.exit(2);
  }
}

if (isMain(import.meta.url)) cli().catch(e => { console.error(e.message || e); process.exit(1); });
