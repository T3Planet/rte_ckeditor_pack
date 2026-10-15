/** Prompt for the Cursor agent that performs an approved CKEditor upgrade (pure, unit-tested). */
export const MIGRATION_MAJOR = 48;

export function buildPrompt({ target, from, requestId, skillPath }) {
  const major = Number(String(target).split('.')[0]);
  const isMajor = from ? Number(String(from).split('.')[0]) !== major : false;
  return `You maintain EXT:rte_ckeditor_pack (this repo root, a TYPO3 13.4/14.3 extension).

Approved ClickUp request ${requestId}: update the shared CKEditor 5 build (Resources/Public/Contrib/) ${from ?? '(current)'} → ${target}${isMajor ? ' (NEW CKEDITOR MAJOR)' : ''}.

Follow the runbook ${skillPath} exactly. In short:

1. \`node Build/Scripts/ckeditor-pin.mjs ${target} --resolve-separate\`${isMajor ? ` (it exits 3 for a new major: port Build/ckeditor/rollup.config.mjs to CKEditor ${major} first, set "supportedMajor": ${major} in Build/ckeditor/packages.json, then pin again)` : ''}.
2. ${major >= MIGRATION_MAJOR ? `The CKEditor ${MIGRATION_MAJOR} migration patch must be applied: \`git apply --reverse --check Build/ckeditor/migrations/ckeditor${MIGRATION_MAJOR}-adapt.patch\` succeeds when it already is, otherwise \`git apply\` it.` : `Do not apply Build/ckeditor/migrations/ckeditor${MIGRATION_MAJOR}-adapt.patch (target is below ${MIGRATION_MAJOR}).`}
3. In Build/ckeditor: \`npm install --no-audit --no-fund\`, remove Resources/Public/Contrib/@ckeditor, Resources/Public/Contrib/translations and Resources/Public/Contrib/ckeditor5.css, then \`npm run build\`.
4. \`node Build/Scripts/validate-contrib.mjs ${target}\` must pass. If packages were added or removed, adapt Configuration/JavaScriptModules.php and the plugins under Resources/Public/JavaScript/ (its body must stay inside \`return (static function (): array { ... })();\` — never add top-level variables).
5. \`php Build/Scripts/check-javascript-modules.php --typo3=13.4.0\` and \`--typo3=14.3.0\` must pass.
6. \`node Build/Scripts/update-readme-compat.mjs ${target}\`.

Rules:
- Never touch Resources/Public/Contrib-12/ (TYPO3 v12 build), ext_emconf.php, CHANGELOG.md, .github/ or ClickUp state; the workflow bumps the version.
- Do NOT commit, push, create branches or open a pull request. Leave the changes in the working tree; the workflow validates, commits and opens the PR only if every check passes.
- Do not add Co-authored-by trailers or credentials anywhere.

If blocked, stop and report the exact blocker.`;
}
