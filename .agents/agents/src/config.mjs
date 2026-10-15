import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// .agents/agents/src -> repository root
const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '../../..');

export const config = {
  repoRoot: process.env.EXT_DIR ? join(process.cwd(), process.env.EXT_DIR) : repoRoot,
  cursorApiKey: process.env.CURSOR_API_KEY ?? '',
  cursorAgentModel: process.env.CURSOR_AGENT_MODEL ?? 'composer-2.5',
  skillPath: '.agents/skills/ckeditor-upgrade/SKILL.md',
};
