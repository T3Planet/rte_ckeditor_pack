#!/usr/bin/env node
/**
 * Runs a Cursor agent (Cursor SDK, same pattern as ns_revolution_slider's implement.ts) on the
 * checked-out repo to perform an APPROVED CKEditor upgrade following .agents/skills/ckeditor-upgrade/SKILL.md.
 * The agent only edits the working tree: ckeditor-rebuild.yml then runs the deterministic gates,
 * bumps the version, commits and opens the PR.
 *
 *   node .agents/agents/src/ckeditor-upgrade.mjs --target <x.y.z> [--from <x.y.z>] [--request <id>] [--dry-run] [--help]
 *
 * Env: CURSOR_API_KEY (required unless --dry-run), CURSOR_AGENT_MODEL (default composer-2.5), EXT_DIR.
 * Exit: 0 ok, 1 agent failed / no changes / agent committed, 2 usage error.
 */
import { config } from './config.mjs';
import { buildPrompt } from './prompt.mjs';
import { headSha, workingTreeChanges, withoutPushCredentials } from './git.mjs';

const USAGE = 'Usage: ckeditor-upgrade.mjs --target <x.y.z> [--from <x.y.z>] [--request <id>] [--dry-run] [--help]';
const VERSION = /^\d+\.\d+\.\d+$/;

export function parseArgs(argv) {
  const out = { dryRun: false, help: false };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--dry-run') out.dryRun = true;
    else if (a === '--help' || a === '-h') out.help = true;
    else if (['--target', '--from', '--request'].includes(a)) out[a.slice(2)] = argv[++i];
    else throw new Error(`Unknown argument: ${a}`);
  }
  if (!out.help) {
    if (!VERSION.test(out.target || '')) throw new Error('--target <x.y.z> is required');
    if (out.from && !VERSION.test(out.from)) throw new Error('--from must be x.y.z');
  }
  return out;
}

async function runAgent(cwd, prompt) {
  const { Agent } = await import('@cursor/sdk');
  const agent = await Agent.create({
    apiKey: config.cursorApiKey,
    model: { id: config.cursorAgentModel },
    local: { cwd, settingSources: [] },
  });
  try {
    const run = await agent.send(prompt);
    for await (const _ of run.stream()) {
      /* drain */
    }
    return await run.wait();
  } finally {
    await agent[Symbol.asyncDispose]?.();
  }
}

export async function main(argv = process.argv.slice(2)) {
  let args;
  try { args = parseArgs(argv); } catch (e) { console.error(`${e.message}\n${USAGE}`); return 2; }
  if (args.help) { console.log(USAGE); return 0; }

  const cwd = config.repoRoot;
  const prompt = buildPrompt({ target: args.target, from: args.from, requestId: args.request ?? 'manual', skillPath: config.skillPath });
  if (args.dryRun) {
    console.log(`[dry-run] repo: ${cwd}\n[dry-run] model: ${config.cursorAgentModel}\n[dry-run] CURSOR_API_KEY set: ${config.cursorApiKey ? 'yes' : 'no'}\n[dry-run] prompt:\n${prompt}`);
    return 0;
  }
  if (!config.cursorApiKey) { console.error('CURSOR_API_KEY is required (the workflow uses the plain npm build when it is missing).'); return 2; }

  const before = headSha(cwd);
  const restore = withoutPushCredentials(cwd);
  let result;
  try {
    console.log(`Cursor agent (${config.cursorAgentModel}) upgrading CKEditor to ${args.target} in ${cwd}`);
    result = await runAgent(cwd, prompt);
  } finally {
    restore();
  }
  if (result?.status === 'error') { console.error(`Cursor agent failed for CKEditor ${args.target}`); return 1; }
  if (headSha(cwd) !== before) { console.error('The agent created commits; it must only change the working tree.'); return 1; }
  if (!workingTreeChanges(cwd)) { console.error(`Cursor agent finished for ${args.target} but changed nothing.`); return 1; }
  console.log('Cursor agent finished; changes left in the working tree for the workflow gates.');
  return 0;
}

const isMain = process.argv[1] && import.meta.url === new URL(`file://${process.argv[1]}`).href;
if (isMain) main().then((code) => process.exit(code), (e) => { console.error(e); process.exit(1); });
