#!/usr/bin/env node
/**
 * Update the "CKEditor Version" column of the current extension major's row in README.md
 * (## Compatibility table). The current major is taken from ext_emconf.php ('version' => 'X.y.z').
 *   node Build/Scripts/update-readme-compat.mjs <ckeditorVersion> [README.md] [ext_emconf.php]
 */
import fs from 'node:fs';
import { isMain } from './ckeditor-lib.mjs';

export function extensionMajor(emconf) {
  const m = emconf.match(/'version'\s*=>\s*'(\d+)\./);
  return m ? m[1] : null;
}

export function updateCompat(readme, extMajor, version) {
  const lines = readme.split('\n');
  const start = lines.findIndex(l => /^##\s+Compatibility/.test(l));
  if (start < 0) throw new Error('No "## Compatibility" section in README');
  let header = -1;
  for (let i = start + 1; i < lines.length && !/^##\s/.test(lines[i]); i++) if (lines[i].trim().startsWith('|')) { header = i; break; }
  if (header < 0) throw new Error('No compatibility table');
  const cols = lines[header].split('|').map(c => c.trim());
  const ckCol = cols.findIndex(c => /ckeditor version/i.test(c));
  if (ckCol < 0) throw new Error('No "CKEditor Version" column');
  for (let i = header + 2; i < lines.length && lines[i].trim().startsWith('|'); i++) {
    const cells = lines[i].split('|');
    if (cells[1]?.trim() === `v${extMajor}.x`) {
      const width = cells[ckCol].length;
      const old = cells[ckCol].trim();
      cells[ckCol] = (' ' + version).padEnd(width, ' ');
      lines[i] = cells.join('|');
      return { readme: lines.join('\n'), old, changed: old !== version };
    }
  }
  throw new Error(`No row for v${extMajor}.x in the compatibility table`);
}

if (isMain(import.meta.url)) {
  const [version, readmePath = 'README.md', emconfPath = 'ext_emconf.php'] = process.argv.slice(2);
  if (!/^\d+\.\d+\.\d+$/.test(version || '')) { console.error('Usage: update-readme-compat.mjs <x.y.z>'); process.exit(2); }
  const maj = extensionMajor(fs.readFileSync(emconfPath, 'utf8'));
  const res = updateCompat(fs.readFileSync(readmePath, 'utf8'), maj, version);
  fs.writeFileSync(readmePath, res.readme);
  console.log(res.changed ? `README: v${maj}.x CKEditor ${res.old} -> ${version}` : 'README already up to date');
}
