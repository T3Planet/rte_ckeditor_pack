---
name: ckeditor-upgrade
description: >-
  After a CKEditor update request is approved on its ClickUp task ("approve" comment), rebuild the
  shared CKEditor 5 build Resources/Public/Contrib/ (TYPO3 13.4 + 14.3) at the target version and
  adapt the extension. Run by .github/workflows/ckeditor-rebuild.yml through the Cursor SDK runner
  (.agents/agents/src/ckeditor-upgrade.mjs). Edits the working tree only.
---

# rte_ckeditor_pack — approved CKEditor upgrade

**Flow (unchanged):** monthly monitor → new ClickUp task in the CKEditor list → `approve` comment →
`ckeditor-rebuild.yml` → this runbook (Cursor agent) → deterministic gates → version bump → PR → PR link on the same task.

## Inputs

- `{target}` CKEditor version (x.y.z), `{from}` version currently in `Resources/Public/Contrib/`
- Request id `CKE-{target}-{yyyymmdd}`
- Repo root = EXT:rte_ckeditor_pack, checked out on the PR base branch

## Steps

1. Pin: `node Build/Scripts/ckeditor-pin.mjs {target} --resolve-separate`.
   Exit code 3 = new CKEditor major: port `Build/ckeditor/rollup.config.mjs` (compare TYPO3 core's
   `Build/rollup/ckeditor.js` for that major), set `supportedMajor` in `Build/ckeditor/packages.json`, pin again.
2. Migration patches: for every `Build/ckeditor/migrations/ckeditorNN-adapt.patch` with NN <= major({target}):
   skip if `git apply --reverse --check` succeeds, otherwise `git apply` it. Never apply one for a higher major.
3. Build: in `Build/ckeditor` run `npm install --no-audit --no-fund`; delete `Resources/Public/Contrib/@ckeditor`,
   `Resources/Public/Contrib/translations`, `Resources/Public/Contrib/ckeditor5.css`; run `npm run build`.
4. Validate: `node Build/Scripts/validate-contrib.mjs {target}`. Fix what it reports:
   - new/removed packages → `Build/ckeditor/packages.json` and `Configuration/JavaScriptModules.php`
   - API changes in custom plugins → `Resources/Public/JavaScript/**` (typo3-link fork, MathType shim, collaboration adapters, …)
5. `Configuration/JavaScriptModules.php` must keep its body inside `return (static function (): array { ... })();`.
   TYPO3 includes it in its own scope; a top-level variable such as `$package` breaks the backend
   ("Call to a member function getPackageKey() on string").
   Check: `php Build/Scripts/check-javascript-modules.php --typo3=13.4.0` and `--typo3=14.3.0`.
6. `php -l` every PHP file you touched.
7. README: `node Build/Scripts/update-readme-compat.mjs {target}`.

## Guardrails

- Never touch `Resources/Public/Contrib-12/` (TYPO3 v12, CKEditor 47).
- Never edit `ext_emconf.php`, `CHANGELOG.md`, `.github/`, `.agents/` or the ClickUp state block; never create branches.
  The workflow bumps the version (`Build/Scripts/ckeditor-release.mjs bump`) and runs
  `ckeditor-release.mjs assert-ready` plus the TYPO3 13.4/14.3 smoke install.
- Do NOT commit, push, create branches or open PRs. The runner fails if HEAD moves.
- If blocked, stop and report the exact blocker (it is posted to the ClickUp task).
