/**
 * Shared CKEditor 5 build for EXT:rte_ckeditor_pack (TYPO3 13.4 + 14.3) -> Resources/Public/Contrib/
 *
 * Adapted from TYPO3 core 13.4/14.3 Build/rollup/ckeditor.js for CKEditor 48
 * (commit 30522e4 "[TASK] Upgrade to CKEditor 5 v48"). Differences to core:
 *  - package list from packages.json (core list + extras the extension ships)
 *  - output directory configurable via CONTRIB_OUT_DIR (default ../../Resources/Public/Contrib)
 *  - extras bundle their third-party dependencies (browser builds); the "HEADS UP" guard applies to core packages
 *  - CSS is not part of the JS modules any more (CKEditor 48): build-css.mjs writes Contrib/ckeditor5.css
 */
import { nodeResolve } from '@rollup/plugin-node-resolve';
import MagicString from 'magic-string';
import commonjs from '@rollup/plugin-commonjs';
import postcss from 'postcss';
import cssnano from 'cssnano';
import svg from 'rollup-plugin-svg';
import { minify } from 'rollup-plugin-esbuild';
import { resolve } from 'path';
import { readFileSync } from 'fs';
import { translations } from './translations.mjs';

const outDir = resolve(process.env.CONTRIB_OUT_DIR || '../../Resources/Public/Contrib');
const { core, extras, externalAliases = {}, bundledDeepImports = [] } = JSON.parse(readFileSync('./packages.json', 'utf8'));
const only = process.env.CKEDITOR_ONLY ? process.env.CKEDITOR_ONLY.split(',') : null;
const packages = [...core, ...extras].filter(p => !only || only.includes(p));

const postCssProcessor = postcss([
  cssnano({
    preset: 'default',
  }),
]);

const allowedBareImports = [
  'es-toolkit/compat',
  'es-toolkit/compat/isEqual',
  'vanilla-colorful/lib/entrypoints/hex',
  'color-convert',
  'color-name',
  'color-parse',
];

export default [
  ...packages.map(packageName => {
    return {
      input: [packageName],
      output: {
        compact: true,
        file: `${outDir}/${packageName}.js`,
        format: 'es',
        plugins: [minify({ target: 'es2023' })],
      },
      external: ['lodash-es'],
      plugins: [
        {
          name: 'resolve imports',
          resolveId: (source, from) => {
            if (from === undefined) {
              return null; // entry point
            }
            if (bundledDeepImports.some(prefix => source.startsWith(prefix))) {
              return null; // bundled into the importing package (as in the shipped Contrib/)
            }
            if (externalAliases[source]) {
              return { id: externalAliases[source], external: true };
            }
            if (source.startsWith('@ckeditor/') && source.endsWith('/dist/index.js')) {
              return { id: source.replace('/dist/index.js', ''), external: true };
            }
            if (source.startsWith('@ckeditor/') && !source.startsWith(packageName) && !source.endsWith('.svg') && !source.endsWith('.css')) {
              if (source.split('/').length > 2) {
                throw new Error(`Non package-entry point was imported: ${source} (from ${from})`);
              }
              return { id: source.replace(/.js$/, ''), external: true };
            }
            if (source.startsWith('@ckeditor/') && source.endsWith('.js') && source.split('/').length === 2) {
              throw new Error(`JS File with suffix: ${source} import from ${from}`);
            }
            if (
              !source.startsWith('@ckeditor/') &&
              !source.startsWith('.') &&
              !source.startsWith('/') &&
              !allowedBareImports.includes(source) &&
              core.includes(packageName)
            ) {
              throw new Error(`HEADS UP: New CKEditor 5 import "${source}" (import from ${from}). Please decide whether to bundle or package separately and adapt Build/ckeditor/rollup.config.mjs accordingly.`);
            }
            return null;
          }
        },
        {
          name: 'patchLinkEditing',
          transform(code, id) {
            if (!id.endsWith('@ckeditor/ckeditor5-link/dist/index.js')) {
              return null;
            }
            const ms = new MagicString(code);
            // Workaround (same as TYPO3 core): a link without `href` is created when the cursor is
            // placed at the end of a link containing a class attribute (htmlA is coupled with linkHref).
            const source = 'return schema.getDefinition("$text").allowAttributes.filter((attribute) => attribute.startsWith("link"));';
            const target = 'return schema.getDefinition("$text").allowAttributes.filter((attribute) => attribute.startsWith("link")||attribute==="htmlA");';
            if (!code.includes(source)) {
              throw new Error(`Expected to find "${source}" in "${id}". Please adapt the rollup plugin "patchLinkEditing".`);
            }
            ms.replace(source, target);
            return { code: ms.toString(), map: ms.generateMap({ id, includeContent: true, hires: true }) };
          }
        },
        {
          name: 'css inject',
          async transform(code, id) {
            if (!id.endsWith('.css')) {
              return;
            }
            const { css } = await postCssProcessor.process(code, { from: id });
            const importPath = resolve('./shim/style-inject.js');
            return {
              code: `
                import styleInject from '${importPath}';
                styleInject(${JSON.stringify(css)});
              `,
              map: { mappings: '' }
            };
          }
        },
        nodeResolve({ extensions: ['.js'], browser: !core.includes(packageName) }),
        commonjs(),
        svg(),
      ]
    };
  }),
  ...(process.env.CKEDITOR_SKIP_TRANSLATIONS ? [] : translations(packages, outDir)),
];
