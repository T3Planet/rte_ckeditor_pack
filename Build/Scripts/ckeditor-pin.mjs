#!/usr/bin/env node
/**
 * Pin the shared CKEditor 5 build (Build/ckeditor) to an exact version.
 *
 *   node Build/Scripts/ckeditor-pin.mjs 48.5.2 [--resolve-separate]
 *
 * - writes ckeditorVersion into Build/ckeditor/packages.json
 * - regenerates "dependencies" in Build/ckeditor/package.json with EXACT versions for every
 *   package of packages.json (core + extras) and the separately versioned ones
 * - --resolve-separate: ask npm which @ckeditor/ckeditor-cloud-services-collaboration version
 *   @ckeditor/ckeditor5-real-time-collaboration@<ver> requires (needs network)
 * - adds the real `ckeditor5` umbrella package (since CKEditor 48 it ships the CSS, as in TYPO3 core)
 * - exits with code 3 when <ver> has another major than packages.json "supportedMajor":
 *   the rollup config must be ported first (pass --allow-major to pin anyway)
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const BUILD_DIR = process.env.CKEDITOR_BUILD_DIR || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../ckeditor');
const version = process.argv[2];
if (!/^\d+\.\d+\.\d+$/.test(version || '')) {
  console.error('Usage: ckeditor-pin.mjs <x.y.z> [--resolve-separate]');
  process.exit(2);
}
const read = (f) => JSON.parse(fs.readFileSync(path.join(BUILD_DIR, f), 'utf8'));
const write = (f, d) => fs.writeFileSync(path.join(BUILD_DIR, f), JSON.stringify(d, null, 2) + '\n');

const pk = read('packages.json');
if (pk.supportedMajor && Number(version.split('.')[0]) !== pk.supportedMajor && !process.argv.includes('--allow-major')) {
  console.error(`CKEditor ${version} is a different major than the build config supports (${pk.supportedMajor}). Port Build/ckeditor/rollup.config.mjs (see TYPO3 core's matching branch), then set supportedMajor.`);
  process.exit(3);
}
pk.ckeditorVersion = version;

if (process.argv.includes('--resolve-separate')) {
  const out = execFileSync('npm', ['view', `@ckeditor/ckeditor5-real-time-collaboration@${version}`, 'dependencies', '--json'], { encoding: 'utf8' });
  const deps = JSON.parse(out || '{}');
  const collab = deps['@ckeditor/ckeditor-cloud-services-collaboration'];
  if (collab) pk.separatelyVersioned['@ckeditor/ckeditor-cloud-services-collaboration'] = collab.replace(/^[\^~]/, '');
}
write('packages.json', pk);

const pj = read('package.json');
const deps = { ckeditor5: version };
for (const name of [...pk.core, ...pk.extras]) deps[name] = pk.separatelyVersioned[name] ?? version;
for (const [name, v] of Object.entries(pk.separatelyVersioned)) deps[name] = v;
pj.dependencies = Object.fromEntries(Object.entries(deps).sort(([a], [b]) => a.localeCompare(b)));
write('package.json', pj);
console.log(`Pinned Build/ckeditor to CKEditor ${version} (${Object.keys(deps).length - 1} packages)`);
