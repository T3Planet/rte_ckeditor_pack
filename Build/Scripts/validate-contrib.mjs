#!/usr/bin/env node
/**
 * Validate a built shared CKEditor directory (default Resources/Public/Contrib).
 *   node Build/Scripts/validate-contrib.mjs <expectedVersion> [contribDir]
 * Checks (PLAN.md 6.3 step 4):
 *  1. @ckeditor/ckeditor5-utils.js reports <expectedVersion>
 *  2. every '@ckeditor/*' entry in Configuration/JavaScriptModules.php points to an existing file
 *  3. exactly the files listed in Build/ckeditor/packages.json (+ translations/) exist, nothing missing
 *  4. `node --check` passes for every output file
 *  5. every bare import of every output resolves to an import-map entry (JavaScriptModules.php or
 *     a shipped file) - unmapped-but-shipped specifiers are reported as warnings
 *  6. translations count >= MIN_TRANSLATIONS (73)
 *  7. CKEditor 48+: Contrib/ckeditor5.css exists and was built for <expectedVersion>
 * Exit 1 on any error; prints a JSON summary as the last line.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { versionFromJs } from './ckeditor-lib.mjs';

// rollup's AST parser (installed in Build/ckeditor) is used to read real import/export statements
const req = createRequire(path.resolve(process.env.EXT_DIR || '.', 'Build/ckeditor/package.json'));
const { parseAst } = await import(pathToFileURL(req.resolve('rollup/parseAst')).href);
function bareImports(src) {
  const out = [];
  const visit = (n) => {
    if (!n || typeof n !== 'object') return;
    if (Array.isArray(n)) return n.forEach(visit);
    if ((n.type === 'ImportDeclaration' || n.type === 'ExportAllDeclaration' || n.type === 'ExportNamedDeclaration' || n.type === 'ImportExpression') && n.source?.type === 'Literal') out.push(n.source.value);
    for (const k in n) if (k !== 'source' && n[k] && typeof n[k] === 'object') visit(n[k]);
  };
  visit(parseAst(src).body);
  return out.filter(s => typeof s === 'string' && !s.startsWith('.') && !s.startsWith('/'));
}

const EXT_DIR = process.env.EXT_DIR || '.';
const [expected, dirArg] = process.argv.slice(2);
const dir = path.resolve(dirArg || path.join(EXT_DIR, 'Resources/Public/Contrib'));
const MIN_TRANSLATIONS = Number(process.env.MIN_TRANSLATIONS || 73);
const errors = [], warnings = [];

const pk = JSON.parse(fs.readFileSync(path.join(EXT_DIR, 'Build/ckeditor/packages.json'), 'utf8'));
const expectedPkgs = [...pk.core, ...pk.extras];

// 1. version
const utils = path.join(dir, '@ckeditor/ckeditor5-utils.js');
const actual = fs.existsSync(utils) ? versionFromJs(fs.readFileSync(utils, 'utf8')) : null;
if (!expected) errors.push('no expected version given');
else if (actual !== expected) errors.push(`ckeditor5-utils.js reports ${actual}, expected ${expected}`);

// 2. import map
const php = fs.readFileSync(path.join(EXT_DIR, 'Configuration/JavaScriptModules.php'), 'utf8');
const map = new Map();
for (const m of php.matchAll(/'(@ckeditor\/[^']+)'\s*=>\s*'EXT:rte_ckeditor_pack\/Resources\/Public\/'\s*\.\s*\$contrib\s*\.\s*'([^']+)'/g)) map.set(m[1], m[2]);
// entries added in PHP code for the shared build only, e.g. foreach ([...] as $package) { $imports['imports']['@ckeditor/ckeditor5-' . $package] ??= '.../Contrib/@ckeditor/ckeditor5-' . $package . '.js'; }
for (const m of php.matchAll(/foreach\s*\(\[([^\]]+)\]\s*as\s*\$package\)\s*\{\s*\$imports\['imports'\]\['@ckeditor\/ckeditor5-'\s*\.\s*\$package\]\s*\?\?=\s*'EXT:rte_ckeditor_pack\/Resources\/Public\/Contrib\/@ckeditor\/ckeditor5-'/g)) {
  for (const p of m[1].matchAll(/'([a-z0-9-]+)'/g)) map.set(`@ckeditor/ckeditor5-${p[1]}`, `/@ckeditor/ckeditor5-${p[1]}.js`);
}
for (const [spec, rel] of map) if (!fs.existsSync(path.join(dir, rel))) errors.push(`JavaScriptModules.php: ${spec} -> ${rel} does not exist`);
if (map.size === 0) errors.push('no @ckeditor entries found in JavaScriptModules.php');

// 3. file list
const files = fs.existsSync(path.join(dir, '@ckeditor')) ? fs.readdirSync(path.join(dir, '@ckeditor')) : [];
const shipped = new Set(files.map(f => '@ckeditor/' + f.replace(/\.js$/, '')));
for (const p of expectedPkgs) if (!shipped.has(p)) errors.push(`missing output for ${p}`);
for (const s of shipped) if (!expectedPkgs.includes(s)) errors.push(`unexpected file @ckeditor/${s.slice(10)}.js (not in packages.json)`);
for (const s of shipped) if (!map.has(s)) warnings.push(`${s} is shipped but not mapped in JavaScriptModules.php (TYPO3 core map applies)`);

// 4 + 5. syntax and imports
const translations = fs.existsSync(path.join(dir, 'translations')) ? fs.readdirSync(path.join(dir, 'translations')).filter(f => f.endsWith('.js')) : [];
const all = [...files.map(f => path.join(dir, '@ckeditor', f)), ...translations.map(f => path.join(dir, 'translations', f))];
const allowedExternal = new Set(['lodash-es']); // provided by TYPO3 core import map
const unresolved = new Map();
for (const f of all) {
  try {
    // .js files with ESM syntax: copy-free check via --input-type would need stdin; --check works on ESM when
    // the nearest package.json has "type":"module" -> use a temporary .mjs link
    const tmp = f + '.check.mjs';
    fs.copyFileSync(f, tmp);
    try { execFileSync(process.execPath, ['--check', tmp], { stdio: 'pipe' }); } finally { fs.unlinkSync(tmp); }
  } catch (e) { errors.push(`node --check failed: ${path.relative(dir, f)}: ${String(e.stderr || e.message).split('\n').slice(0, 4).join(' ')}`); }
  const src = fs.readFileSync(f, 'utf8');
  let specs = [];
  try { specs = bareImports(src); } catch (e) { errors.push(`parse failed: ${path.relative(dir, f)}: ${e.message}`); }
  for (const spec of specs) {
    if (map.has(spec) || allowedExternal.has(spec)) continue;
    if (shipped.has(spec)) { warnings.push(`${path.basename(f)} imports ${spec}: shipped but not in JavaScriptModules.php`); continue; }
    unresolved.set(spec, [...(unresolved.get(spec) || []), path.basename(f)]);
  }
}
for (const [spec, by] of unresolved) errors.push(`import "${spec}" (from ${by.slice(0, 3).join(', ')}${by.length > 3 ? ', ...' : ''}) does not resolve to an import-map entry`);

// 7. CKEditor 48+: CSS is shipped separately (Contrib/ckeditor5.css, built by Build/ckeditor/build-css.mjs)
if (pk.supportedMajor >= 48) {
  const css = path.join(dir, 'ckeditor5.css');
  if (!fs.existsSync(css) || fs.statSync(css).size < 50000) errors.push('ckeditor5.css missing or too small');
  else if (expected && !fs.readFileSync(css, 'utf8').slice(0, 300).includes(`CKEditor 5 ${expected} `)) errors.push(`ckeditor5.css was not built for ${expected}`);
}

// 6. translations
if (translations.length < MIN_TRANSLATIONS) errors.push(`only ${translations.length} translations (expected >= ${MIN_TRANSLATIONS})`);

const uniqWarnings = [...new Set(warnings)];
for (const w of uniqWarnings) console.log(`WARN  ${w}`);
for (const e of errors) console.log(`ERROR ${e}`);
console.log(JSON.stringify({ ok: errors.length === 0, version: actual, files: files.length, translations: translations.length, errors: errors.length, warnings: uniqWarnings.length }));
process.exit(errors.length ? 1 : 0);
