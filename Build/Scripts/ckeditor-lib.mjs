/**
 * Shared helpers for the CKEditor core-version automation (no dependencies, Node >= 20).
 * Used by check-core-ckeditor.mjs, ckeditor-approval.mjs, ckeditor-state.mjs, ckeditor-clickup.mjs.
 * State is kept in ClickUp (see ckeditor-state.mjs); there is no state branch or file.
 */
export const VERSION_RE = /(?:const|let|var)\s+[\w$]+\s*=\s*"(\d{2,}\.\d+\.\d+)"/;
export const versionFromJs = (src) => (src && src.match(VERSION_RE)?.[1]) || null;
export const parseVersion = (v) => String(v).split('.').map(Number);
export const major = (v) => parseVersion(v)[0];
export function cmp(a, b) {
  const x = parseVersion(a), y = parseVersion(b);
  for (let i = 0; i < 3; i++) if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) - (y[i] || 0);
  return 0;
}
export const isValidVersion = (v) => /^\d+\.\d+\.\d+$/.test(String(v || ''));
export const ckNotes = (v) => `https://github.com/ckeditor/ckeditor5/releases/tag/v${v}`;
export const DAY = 24 * 60 * 60 * 1000;

/**
 * What should the ONE shared build (TYPO3 13.4 + 14.3) target?
 *  - same version            -> that version
 *  - same major, diff. minor -> the higher one
 *  - different majors        -> none (warning only)
 */
export function sharedTarget(v13, v14) {
  if (!v13 || !v14) return { target: v13 || v14 || null, divergent: false, majorDivergent: false };
  if (v13 === v14) return { target: v13, divergent: false, majorDivergent: false };
  if (major(v13) === major(v14)) return { target: cmp(v13, v14) >= 0 ? v13 : v14, divergent: true, majorDivergent: false };
  return { target: null, divergent: true, majorDivergent: true, lower: cmp(v13, v14) < 0 ? v13 : v14, higher: cmp(v13, v14) < 0 ? v14 : v13 };
}

/** CKE-<version>-<yyyymmdd> (UTC date) */
export function requestId(version, now = new Date()) {
  return `CKE-${version}-${now.toISOString().slice(0, 10).replace(/-/g, '')}`;
}

// ---------------- ClickUp API v2 ----------------
export class ClickUp {
  constructor({ token = process.env.CLICKUP_API_TOKEN, base = process.env.CLICKUP_API_BASE || 'https://api.clickup.com/api/v2', fetchImpl = fetch } = {}) {
    this.token = token; this.base = base.replace(/\/$/, ''); this.fetch = fetchImpl;
  }
  async request(method, path, body) {
    if (!this.token) throw new Error('CLICKUP_API_TOKEN not set');
    for (let attempt = 1; ; attempt++) {
      const res = await this.fetch(this.base + path, {
        method,
        headers: { Authorization: this.token, 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
      });
      if (res.ok) return res.status === 204 ? {} : res.json();
      if ((res.status === 429 || res.status >= 500) && attempt < 4) {
        const reset = Number(res.headers.get('x-ratelimit-reset'));
        const wait = res.status === 429 && reset ? Math.max(1000, reset * 1000 - Date.now()) : 1000 * 2 ** attempt;
        await new Promise(r => setTimeout(r, Math.min(wait, 60000)));
        continue;
      }
      throw new Error(`ClickUp ${method} ${path} -> HTTP ${res.status}: ${await res.text()}`);
    }
  }
  /** POST /task/{id}/comment -> { id, hist_id, date } */
  postTaskComment(taskId, text, { notifyAll = true, assignee } = {}) {
    return this.request('POST', `/task/${encodeURIComponent(taskId)}/comment`, { comment_text: text, notify_all: notifyAll, ...(assignee ? { assignee: Number(assignee) } : {}) });
  }
  /** POST /list/{id}/task -> { id, url, ... } */
  createTask(listId, { name, description, assignees = [], tags = [] }) {
    return this.request('POST', `/list/${encodeURIComponent(listId)}/task`, {
      name, markdown_description: description, assignees: assignees.map(Number), tags, notify_all: true,
    });
  }
  /** PUT /task/{id} (e.g. { status } or { markdown_content }) */
  updateTask(taskId, body) {
    return this.request('PUT', `/task/${encodeURIComponent(taskId)}`, body);
  }
  /** POST /comment/{id}/reply */
  replyToComment(commentId, text, { notifyAll = false } = {}) {
    return this.request('POST', `/comment/${encodeURIComponent(commentId)}/reply`, { comment_text: text, notify_all: notifyAll });
  }
  /**
   * GET /task/{id}/comment returns the newest 25 comments; older pages via start (date ms) + start_id.
   * Pages back until comments are older than `sinceMs`.
   */
  async getTaskComments(taskId, sinceMs = 0, maxPages = 20) {
    const all = [];
    let qs = '';
    for (let page = 0; page < maxPages; page++) {
      const { comments = [] } = await this.request('GET', `/task/${encodeURIComponent(taskId)}/comment${qs}`);
      all.push(...comments);
      if (comments.length < 25) break;
      const oldest = comments[comments.length - 1];
      if (Number(oldest.date) <= sinceMs) break;
      qs = `?start=${encodeURIComponent(oldest.date)}&start_id=${encodeURIComponent(oldest.id)}`;
    }
    const seen = new Set();
    return all.filter(c => !seen.has(String(c.id)) && seen.add(String(c.id)));
  }
  /** GET /comment/{id}/reply -> threaded replies */
  async getReplies(commentId) {
    const { comments = [] } = await this.request('GET', `/comment/${encodeURIComponent(commentId)}/reply`);
    return comments;
  }
}

/** Post to the configured task; returns the created comment (or null in dry-run). */
export async function notify(text, { dryRun = false, client = new ClickUp(), taskId = process.env.CLICKUP_TASK_ID, replyTo } = {}) {
  if (dryRun) { console.log(`\n[DRY_RUN] ClickUp ${replyTo ? `reply to ${replyTo}` : `comment on ${taskId}`}:\n${text}\n`); return null; }
  if (replyTo) return client.replyToComment(replyTo, text);
  if (!taskId) throw new Error('CLICKUP_TASK_ID not set');
  return client.postTaskComment(taskId, text, { assignee: process.env.CLICKUP_ASSIGNEE });
}

export const DEFAULT_LIST_ID = '901609568667'; // ClickUp list "CKEditor"
export const parseIds = (s) => String(s || '').split(/[\s,;]+/).filter(Boolean).map(String);
/** Who gets the per-version task: CLICKUP_ASSIGNEE_IDS, else the approvers. */
export const assigneeIds = (env = process.env) => parseIds(env.CLICKUP_ASSIGNEE_IDS || env.CLICKUP_APPROVER_IDS);
export const taskUrl = (id) => `https://app.clickup.com/t/${id}`;

export const isMain = (metaUrl) => process.argv[1] && new URL(metaUrl).pathname === new URL(`file://${process.argv[1]}`).pathname;
