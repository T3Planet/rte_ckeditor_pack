#!/usr/bin/env node
/**
 * Release steps of the CKEditor rebuild (run by ckeditor-rebuild.yml before the PR is opened).
 *
 *   node Build/Scripts/ckeditor-release.mjs bump <target> [--base <ref>] [--date YYYY-MM-DD]
 *       raise ext_emconf.php version (patch for a same-major CKEditor update, minor for a new
 *       CKEditor major) and prepend a CHANGELOG.md entry. Idempotent: does nothing if ext_emconf
 *       already differs from <ref>.
 *   node Build/Scripts/ckeditor-release.mjs assert-ready <target> [--base <ref>]
 *       release-ready gate, exit 1 with a list of problems:
 *        - only expected files changed against <ref> (Contrib/, JavaScript/, Classes/, Configuration/,
 *          Build/ckeditor package files, README.md, ext_emconf.php, CHANGELOG.md)
 *        - Resources/Public/Contrib-12/ untouched
 *        - Contrib/@ckeditor/ckeditor5-utils.js and Build/ckeditor/packages.json report <target>
 *        - migration patch state consistent (ckeditorNN-adapt.patch applied iff target major >= NN)
 *        - ext_emconf.php version == expected bump of <ref>, CHANGELOG.md top entry for it mentions <target>
 * <ref> defaults to origin/HEAD's branch (env BASE_REF, else "origin/main"). EXT_DIR selects the extension root.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { versionFromJs, major, isValidVersion, isMain } from './ckeditor-lib.mjs';

export const ALLOWED_CHANGES = [
  /^Resources\/Public\/Contrib\//,
  /^Resources\/Public\/JavaScript\//,
  /^Classes\//,
  /^Configuration\//,
  /^Build\/ckeditor\/(package\.json|package-lock\.json|packages\.json)$/,
  /^README\.md$/,
  /^ext_emconf\.php$/,
  /^CHANGELOG\.md$/,
];
const UTILS = 'Resources/Public/Contrib/@ckeditor/ckeditor5-utils.js';

/** 'minor' for a new CKEditor major, otherwise 'patch'. */
export const bumpKind = (fromCk, toCk) => (fromCk && major(fromCk) !== major(toCk) ? 'minor' : 'patch');

export function bumpVersion(v, kind) {
  const [a, b, c] = String(v).split('.').map(Number);
  if ([a, b, c].some(Number.isNaN)) throw new Error(`Cannot bump version "${v}"`);
  return kind === 'minor' ? `${a}.${b + 1}.0` : `${a}.${b}.${c + 1}`;
}

const EMCONF_RE = /('version'\s*=>\s*')(\d+\.\d+\.\d+)(')/;
export const emconfVersion = (src) => src.match(EMCONF_RE)?.[2] ?? null;
export function setEmconfVersion(src, v) {
  if (!EMCONF_RE.test(src)) throw new Error("ext_emconf.php has no 'version' => 'x.y.z'");
  return src.replace(EMCONF_RE, `$1${v}$3`);
}

export function changelogEntry(extVersion, fromCk, toCk, date) {
  const kind = bumpKind(fromCk, toCk) === 'minor' ? 'major' : 'minor/patch';
  return `## ${extVersion} - ${date}\n\n- [TASK] Update the shared CKEditor 5 build (Resources/Public/Contrib, TYPO3 13.4/14.3) from ${fromCk ?? 'n/a'} to ${toCk} (CKEditor ${kind} update).\n`;
}

export function prependChangelog(existing, entry) {
  const header = '# Changelog\n\n';
  const body = (existing || '').startsWith('# Changelog') ? existing.replace(/^# Changelog\s*\n+/, '') : (existing || '');
  return header + entry + (body ? '\n' + body : '');
}

/** Top "## x.y.z" entry of a CHANGELOG. */
export function topChangelog(src) {
  const text = String(src || '');
  const start = text.search(/^## \d+\.\d+\.\d+/m);
  if (start < 0) return null;
  const rest = text.slice(start);
  const next = rest.slice(3).search(/^## /m);
  const entry = next < 0 ? rest : rest.slice(0, next + 3);
  return { version: entry.match(/^## (\d+\.\d+\.\d+)/)[1], text: entry };
}

/** Pure gate; every input is plain data so it can be unit-tested. */
export function releaseProblems({ target, changedFiles, contribVersion, packagesVersion, patches = [], baseExtVersion, extVersion, changelog, fromCk }) {
  const p = [];
  for (const f of changedFiles) {
    if (f.startsWith('Resources/Public/Contrib-12/')) p.push(`Contrib-12 must stay untouched: ${f}`);
    else if (!ALLOWED_CHANGES.some(re => re.test(f))) p.push(`unexpected changed file: ${f}`);
  }
  if (contribVersion !== target) p.push(`Contrib build reports CKEditor ${contribVersion ?? 'unknown'}, expected ${target}`);
  if (packagesVersion !== target) p.push(`Build/ckeditor/packages.json ckeditorVersion is ${packagesVersion ?? 'unknown'}, expected ${target}`);
  for (const { major: m, name, applied } of patches) {
    const want = major(target) >= m;
    if (applied !== want) p.push(`${name} should ${want ? '' : 'not '}be applied for CKEditor ${target}`);
  }
  const expected = baseExtVersion ? bumpVersion(baseExtVersion, bumpKind(fromCk, target)) : null;
  if (expected && extVersion !== expected) p.push(`ext_emconf.php version is ${extVersion}, expected ${expected} (${bumpKind(fromCk, target)} bump of ${baseExtVersion})`);
  const top = topChangelog(changelog);
  if (!top) p.push('CHANGELOG.md has no "## x.y.z" entry');
  else {
    if (top.version !== extVersion) p.push(`CHANGELOG.md top entry is ${top.version}, ext_emconf.php is ${extVersion}`);
    if (!top.text.includes(target)) p.push(`CHANGELOG.md top entry does not mention CKEditor ${target}`);
  }
  return p;
}

// ---- git/file helpers (CLI) ----
const EXT_DIR = () => path.resolve(process.env.EXT_DIR || '.');
const git = (args, opts = {}) => execFileSync('git', args, { cwd: EXT_DIR(), encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], ...opts });
const show = (ref, f) => { try { return git(['show', `${ref}:${f}`]); } catch { return null; } };
const read = (f) => { try { return fs.readFileSync(path.join(EXT_DIR(), f), 'utf8'); } catch { return null; } };
const applied = (patch) => { try { git(['apply', '--reverse', '--check', patch]); return true; } catch { return false; } };

export function changedFiles(base) {
  const tracked = git(['diff', '--name-only', base]).split('\n');
  const untracked = git(['ls-files', '--others', '--exclude-standard']).split('\n');
  return [...new Set([...tracked, ...untracked].filter(Boolean))].sort();
}

function cliArgs(argv) {
  const [cmd, target, ...rest] = argv;
  const opt = (n) => { const i = rest.indexOf(`--${n}`); return i >= 0 ? rest[i + 1] : undefined; };
  return { cmd, target, base: opt('base') || process.env.BASE_REF || 'origin/main', date: opt('date') || new Date().toISOString().slice(0, 10) };
}

export function main(argv = process.argv.slice(2)) {
  const { cmd, target, base, date } = cliArgs(argv);
  if (!['bump', 'assert-ready'].includes(cmd) || !isValidVersion(target)) {
    console.error('Usage: ckeditor-release.mjs bump|assert-ready <x.y.z> [--base <ref>] [--date YYYY-MM-DD]');
    return 2;
  }
  const fromCk = versionFromJs(show(base, UTILS));
  const baseExtVersion = emconfVersion(show(base, 'ext_emconf.php') || '');
  if (cmd === 'bump') {
    const src = read('ext_emconf.php');
    const cur = emconfVersion(src || '');
    if (cur !== baseExtVersion) { console.log(`ext_emconf.php already bumped (${baseExtVersion} -> ${cur}), nothing to do.`); return 0; }
    const next = bumpVersion(cur, bumpKind(fromCk, target));
    fs.writeFileSync(path.join(EXT_DIR(), 'ext_emconf.php'), setEmconfVersion(src, next));
    fs.writeFileSync(path.join(EXT_DIR(), 'CHANGELOG.md'), prependChangelog(read('CHANGELOG.md'), changelogEntry(next, fromCk, target, date)));
    console.log(`ext_emconf.php ${cur} -> ${next} (${bumpKind(fromCk, target)}), CHANGELOG.md entry added.`);
    return 0;
  }
  const patchDir = 'Build/ckeditor/migrations';
  const patches = (fs.existsSync(path.join(EXT_DIR(), patchDir)) ? fs.readdirSync(path.join(EXT_DIR(), patchDir)) : [])
    .map(n => ({ n, m: n.match(/^ckeditor(\d+)-.*\.patch$/) })).filter(x => x.m)
    .map(({ n, m }) => ({ name: n, major: Number(m[1]), applied: applied(`${patchDir}/${n}`) }));
  const problems = releaseProblems({
    target,
    changedFiles: changedFiles(base),
    contribVersion: versionFromJs(read(UTILS)),
    packagesVersion: JSON.parse(read('Build/ckeditor/packages.json') || '{}').ckeditorVersion ?? null,
    patches, baseExtVersion, extVersion: emconfVersion(read('ext_emconf.php') || ''), changelog: read('CHANGELOG.md'), fromCk,
  });
  if (problems.length) { console.error(`Release-ready check FAILED for CKEditor ${target}:\n - ${problems.join('\n - ')}`); return 1; }
  console.log(`Release-ready check OK for CKEditor ${target} (base ${base}).`);
  return 0;
}

if (isMain(import.meta.url)) process.exit(main());
