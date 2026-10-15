#!/usr/bin/env node
/**
 * Post a plain-text message from a FILE or stdin to the ClickUp task of request REQUEST_ID
 * (its per-version task, found in CLICKUP_LIST_ID), falling back to CLICKUP_TASK_ID.
 * Text is never passed through shell arguments, so untrusted content can't be interpreted.
 *   node ckeditor-clickup.mjs <file|-> [--reply-to <commentId>]
 * DRY_RUN=1 prints instead of posting.
 */
import fs from 'node:fs';
import { notify } from './ckeditor-lib.mjs';
import { loadState, findRequest } from './ckeditor-state.mjs';

const [src, flag, replyTo] = process.argv.slice(2);
const text = fs.readFileSync(src === '-' || !src ? 0 : src, 'utf8').trim();
const dryRun = ['1', 'true'].includes(process.env.DRY_RUN);
let taskId = process.env.CLICKUP_TASK_ID;
if (process.env.REQUEST_ID) { try { taskId = findRequest(await loadState(), process.env.REQUEST_ID)?.clickupTaskId || taskId; } catch {} }
const res = await notify(text, { dryRun, taskId, replyTo: flag === '--reply-to' ? replyTo : undefined });
if (res) console.log(`ClickUp comment ${res.id ?? ''} posted.`);
