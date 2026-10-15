# CKEditor core-version automation (TYPO3 13.4 + 14.3)

`Resources/Public/Contrib/` is ONE CKEditor 5 build shared by TYPO3 13 and 14.
`Resources/Public/Contrib-12/` (TYPO3 12) is frozen and not touched by any of this.

## Flow

1. **Monthly monitor** (`.github/workflows/ckeditor-core-monitor.yml`, 1st of month 09:00 IST)
   reads the CKEditor version of TYPO3 core 13.4 / 14.3 (branch head + latest Packagist release).
   If the shared target is newer than the bundled version it **creates a new ClickUp task** in the
   CKEditor list (`CLICKUP_LIST_ID`, default `901609568667`) named
   `Update CKEditor to <version> (TYPO3 13.4/14.3)`, assigned to `CLICKUP_ASSIGNEE_IDS`
   (default: `CLICKUP_APPROVER_IDS`). One task per version, never duplicated (existing tagged tasks, open or closed, are checked first).
   If 13.4 and 14.3 use different CKEditor majors it only warns (on `CLICKUP_TASK_ID` if set, else in the run log).
2. **Daily poller** (`ckeditor-approval-poller.yml`, 09:30 IST) reads the comments on THAT task.
   A one-line `approve` or `skip` (also `approve CKE-<version>-<yyyymmdd>`) from a user listed in
   `CLICKUP_APPROVER_IDS`, newer than the task, counts. Reminder after 7 days, expiry after 30 days.
   Approve starts the rebuild; skip is acknowledged on the task and the version is not asked again.
3. **Rebuild** (`ckeditor-rebuild.yml`, three jobs; nothing is pushed unless every step passes):
   - **rebuild:** with the `CURSOR_API_KEY` secret a Cursor agent (Cursor SDK,
     `.agents/agents/src/ckeditor-upgrade.mjs`) performs the upgrade following
     `.agents/skills/ckeditor-upgrade/SKILL.md` (pin, 48 migration patch, npm build, adapt
     `JavaScriptModules.php`/plugins, README) and only edits the working tree. Without the secret the
     plain deterministic path runs (pin + `npm run build`). Then the hard gates: migration patches
     (`Build/ckeditor/migrations/`, e.g. `ckeditor48-adapt.patch` for CKEditor >= 48), `validate-contrib.mjs`,
     `php -l` (Configuration, ext_*.php, Classes), `check-javascript-modules.php` (includes the file the way
     TYPO3's ImportMap does and fails if it overwrites core's `$package`), version bump
     (`ckeditor-release.mjs bump`: `ext_emconf.php` patch for a same-major update, minor for a new
     major, plus a `CHANGELOG.md` entry) and `ckeditor-release.mjs assert-ready` (only expected files
     changed, Contrib version == target, `Contrib-12/` untouched, patch state, ext_emconf/CHANGELOG).
   - **smoke:** TYPO3 13.4 and 14.3 matrix, `Build/Scripts/typo3-smoke.sh` (composer project with the
     extension as path repository, sqlite `typo3 setup`, `extension:setup`, `cache:flush`, backend login page
     and backend import map must load without error).
   - **publish:** commit, PR (draft + `needs-manual-review` for a major update), PR link as a comment on
     the same ClickUp task.
   On any failure no PR is opened, the failed step is commented on the task and the request becomes
   `failed`. **Retry:** fix the cause and comment `approve` on the same task again; the poller picks up
   failed requests too.

### State (in ClickUp, no branch)

The automation creates **no state branch and no state file**. The per-version ClickUp tasks (tag
`ckeditor-update`, list `CLICKUP_LIST_ID`, open and closed) are the source of truth. Each task
description ends with a machine-readable block that the workflows read and rewrite through the ClickUp API:

````text
```ckeditor-automation
ckeditor-automation-state: {"id":"CKE-48.5.2-20261015","targetVersion":"48.5.2","status":"pr_open","prUrl":"https://github.com/.../pull/5",...}
```
````

- `status`: `awaiting` → `approved` → `building` → `pr_open` (or `failed`, `skipped`, `expired`, `superseded`)
- also: request id, approver decision, build run URL / failed step, processed comment ids, history
- the newest task also carries `meta` (last check, core versions, divergence warning key)

Do not edit the block by hand unless you mean to change the automation state (e.g. set `"status":"failed"`
to allow a retry). Deleting the block makes the task a "legacy" request again (read from its title).
To reset the automation for a version, delete or untag its task.

The only branch the automation creates is the PR branch `automation/ckeditor-<version>`. Turn on
Settings → General → **Automatically delete head branches** so it is removed when the PR is merged or closed.

Migration note: earlier versions kept `state.json` on an orphan `automation/state` branch. It is no longer
read or written and can be deleted. Tasks created before the change have no block and are picked up from
their title (`Update CKEditor to <version> ...`); the first run that changes them adds the block.

## Repository setup

| Kind | Name | Value |
|---|---|---|
| Secret | `CLICKUP_API_TOKEN` | ClickUp personal token (`pk_...`), ideally of a bot user. Needs read + edit (task description) + comment on the CKEditor list |
| Variable | `CLICKUP_APPROVER_IDS` | numeric ClickUp user ids, comma separated (e.g. `94966947`) |
| Variable (optional) | `CLICKUP_LIST_ID` | list for the new tasks (default `901609568667`, "CKEditor") |
| Variable (optional) | `CLICKUP_ASSIGNEE_IDS` | assignees of the new task (default: the approvers) |
| Variable (optional) | `CLICKUP_APPROVER_NAMES` | names shown in the task text |
| Secret (optional) | `CURSOR_API_KEY` | Cursor API key (Cursor dashboard → Integrations / API keys); rebuild by a Cursor agent. Without it the plain npm build runs |
| Variable (optional) | `CURSOR_AGENT_MODEL` | Cursor model id for the agent (default `composer-2.5`) |
| Variable (optional) | `CLICKUP_TASK_ID` | general task for warnings / info only (no longer needed for approvals) |
| Variable (optional) | `CKEDITOR_REQUEST_TRIGGER` | `branch` (default, early warning) or `release` |
| Variable + secret (optional) | `AUTOMATION_APP_ID`, `AUTOMATION_APP_PRIVATE_KEY` | GitHub App, so CI runs on automation PRs without manual approval |

Settings → Actions → General:
- Workflow permissions: **Read and write** (only used to push the PR branch)
- **Allow GitHub Actions to create and approve pull requests**: on

Scheduled workflows only run from the default branch, so the files must be merged to `main`.
Find a ClickUp user id: `curl -H "Authorization: $CLICKUP_API_TOKEN" https://api.clickup.com/api/v2/team`.

## Local usage

```bash
# unit + end-to-end tests (fake ClickUp server, no network; incl. release gate, retry, Cursor runner, PHP include check)
node --test Tests/Automation/

# monitor dry run against live TYPO3 data (reads the ClickUp tasks; no task created, nothing written)
DRY_RUN=1 CLICKUP_API_TOKEN=pk_... node Build/Scripts/check-core-ckeditor.mjs

# build into a temporary directory and compare with the shipped files
cd Build/ckeditor && npm ci && npm run build:tmp && cd ../..
node Build/Scripts/validate-contrib.mjs 47.6.2 Build/ckeditor/.build-out

# pin another version (what the rebuild workflow does)
node Build/Scripts/ckeditor-pin.mjs 47.6.3 --resolve-separate

# pre-PR gates
php Build/Scripts/check-javascript-modules.php --typo3=13.4.0
node Build/Scripts/ckeditor-release.mjs assert-ready 48.5.2 --base HEAD
Build/Scripts/typo3-smoke.sh 13.4 . /tmp/typo3-13

# Cursor runner without spending API calls (prints the prompt)
cd .agents/agents && npm ci && cd ../..
node .agents/agents/src/ckeditor-upgrade.mjs --target 48.6.0 --dry-run
```

## Build tooling (CKEditor 48)

- `packages.json`: single source of truth: `core` (the 45 packages of TYPO3 core 13.4/14.3
  `Build/rollup/ckeditor.js` at CKEditor 48), `extras` (37 additional packages the extension ships),
  separately versioned packages (inspector 5.0.3, cloud-services-collaboration 54.2.10 for 48.5.2),
  `supportedMajor` (48).
- `package.json` / `package-lock.json`: exact pins incl. the real `ckeditor5` umbrella package
  (since 48 it ships the CSS, so core no longer stubs it). Regenerated by `Build/Scripts/ckeditor-pin.mjs`,
  which refuses a different major (exit 3) until the config is ported.
- `rollup.config.mjs`: adapted from TYPO3 core's CKEditor 48 config (commit 30522e4): each package is
  built from its `dist/index.js`, `@ckeditor/*/dist/index.js` imports become externals, the link patch
  targets `ckeditor5-link/dist/index.js`, no `theme-lark`. The "HEADS UP" import guard applies to core
  packages; extras bundle their third-party dependencies (browser builds).
- `translations.mjs`: core's 48 version (merges `dist/translations/<lang>.js` of all shipped packages).
- `build-css.mjs`: CKEditor 48 no longer injects CSS from JS. Writes `Contrib/ckeditor5.css`
  = `ckeditor5/dist/ckeditor5.css` + `dist/index.css` of every shipped premium package + core's TYPO3
  additions. Loaded by `CustomAssetTrait` (TYPO3 13/14) instead of core's `editor.css`; the extension's
  `RichTextElement::render()` does not call core's render, so core's `editor.css` is not loaded twice.
- TYPO3 v12 (`Contrib-12/`, CKEditor 47) is unchanged and still uses `theme-lark` / CSS-in-JS.
- **Next major (49+)**: port core's matching config, then bump `supportedMajor`.

History: the 47.6.2 build was reproduced with the 47-era config (126 of 156 files byte-identical)
before the migration to 48.5.2.
