import { execFileSync } from 'node:child_process';

const run = (cwd, args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();

export const headSha = (cwd) => run(cwd, ['rev-parse', 'HEAD']);
export const workingTreeChanges = (cwd) => run(cwd, ['status', '--porcelain']);

/**
 * actions/checkout stores the push token as http.<url>.extraheader in .git/config.
 * Remove it while the agent runs (it must not push) and give it back afterwards.
 */
export function withoutPushCredentials(cwd) {
  let saved = [];
  try {
    saved = run(cwd, ['config', '--local', '--get-regexp', '^http\\..*\\.extraheader$']).split('\n').filter(Boolean)
      .map((line) => { const i = line.indexOf(' '); return [line.slice(0, i), line.slice(i + 1)]; });
  } catch { /* none configured */ }
  for (const [key] of saved) run(cwd, ['config', '--local', '--unset-all', key]);
  return () => { for (const [key, value] of saved) run(cwd, ['config', '--local', '--add', key, value]); };
}
