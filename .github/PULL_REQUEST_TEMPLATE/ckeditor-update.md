## CKEditor 5 update: {{FROM}} -> {{TARGET}} (shared Contrib build, TYPO3 13.4 + 14.3)

- Request: `{{REQUEST_ID}}`, approved by {{APPROVER}} on {{APPROVED_AT}} (ClickUp task {{CLICKUP_TASK}})
- Versions: extension `{{FROM}}` -> `{{TARGET}}`; core 13.4 branch `{{V13_BRANCH}}` / release `{{V13_RELEASE}}`; core 14.3 branch `{{V14_BRANCH}}` / release `{{V14_RELEASE}}`
- CKEditor release notes: https://github.com/ckeditor/ckeditor5/releases/tag/v{{TARGET}} (check intermediate versions too)
- Major update: **{{MAJOR}}**
- Files changed: `Resources/Public/Contrib/**`, `Build/ckeditor/*` pins, README compatibility table
- Build run: {{RUN_URL}}

`Resources/Public/Contrib-12/` (TYPO3 v12) is intentionally untouched.

### Checklist
- [ ] Backend editor loads on TYPO3 13.4 and 14.3
- [ ] Custom plugins work (AI, collaboration, comments/track changes, revision history, line-height, typo3-image, spell-check, document outline)
- [ ] `typo3-link` / `ckeditor5.js` / `css-prefixer.js` forks match core changes
- [ ] `Configuration/JavaScriptModules.php` entries match the new package set
- [ ] CSS check (core `editor.css` vs bundled CSS)
- [ ] Unit and functional tests green
- [ ] Release notes and `ext_emconf.php` version bump decided
