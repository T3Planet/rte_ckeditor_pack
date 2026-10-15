#!/usr/bin/env node
/**
 * Render .github/PULL_REQUEST_TEMPLATE/ckeditor-update.md for a request (state read from its ClickUp task).
 *   node Build/Scripts/ckeditor-pr-body.mjs <requestId>   (CLICKUP_API_TOKEN, CLICKUP_LIST_ID, RUN_URL env; the ClickUp link is the request's own task)
 */
import fs from 'node:fs';
import path from 'node:path';
import { loadState, findRequest } from './ckeditor-state.mjs';
import { isMain } from './ckeditor-lib.mjs';

export function renderBody(template, r, env = process.env) {
  const t = r.tracks || {};
  const vars = {
    REQUEST_ID: r.id, TARGET: r.targetVersion, FROM: r.fromVersion ?? 'n/a',
    APPROVER: r.decision?.username ?? 'n/a', APPROVED_AT: r.decision?.date?.slice(0, 10) ?? 'n/a',
    CLICKUP_TASK: r.clickupTaskId ? `https://app.clickup.com/t/${r.clickupTaskId}` : 'n/a',
    V13_BRANCH: t['13.4']?.branch ?? 'n/a', V13_RELEASE: `${t['13.4']?.release ?? 'n/a'} (${t['13.4']?.releaseTag ?? '-'})`,
    V14_BRANCH: t['14.3']?.branch ?? 'n/a', V14_RELEASE: `${t['14.3']?.release ?? 'n/a'} (${t['14.3']?.releaseTag ?? '-'})`,
    MAJOR: r.major ? 'yes - draft PR, manual plugin review required' : 'no', RUN_URL: env.RUN_URL ?? 'n/a',
  };
  return template.replace(/\{\{(\w+)\}\}/g, (m, k) => (k in vars ? String(vars[k]) : m));
}

if (isMain(import.meta.url)) {
  const r = findRequest(await loadState(), process.argv[2]);
  if (!r) { console.error(`Request ${process.argv[2]} not found`); process.exit(1); }
  const tpl = fs.readFileSync(path.join(process.env.EXT_DIR || '.', '.github/PULL_REQUEST_TEMPLATE/ckeditor-update.md'), 'utf8');
  process.stdout.write(renderBody(tpl, r));
}
