/**
 * Frontend MathML renderer bootstrap.
 * Loads MathJax (mml-chtml) and typesets <math> formulas on the page.
 */
(function () {
  'use strict';

  const MATHJAX_SRC = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/mml-chtml.js';

  function hasMathMl(root) {
    return !!(root && root.querySelector && root.querySelector('math'));
  }

  function ensureMathJaxConfig() {
    if (window.MathJax && window.MathJax.typesetPromise) {
      return;
    }
    window.MathJax = Object.assign({}, window.MathJax || {}, {
      options: {
        enableEnrichment: false,
        renderActions: {
          addMenu: []
        }
      },
      startup: {
        typeset: false
      }
    });
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      const existing = document.querySelector('script[data-rte-ckeditor-pack-mathjax="1"]');
      if (existing) {
        if (window.MathJax && window.MathJax.typesetPromise) {
          resolve();
          return;
        }
        existing.addEventListener('load', function () { resolve(); });
        existing.addEventListener('error', reject);
        return;
      }
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.dataset.rteCkeditorPackMathjax = '1';
      script.onload = function () { resolve(); };
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function typeset(root) {
    if (!window.MathJax || typeof window.MathJax.typesetPromise !== 'function') {
      return Promise.resolve();
    }
    const nodes = root ? [root] : undefined;
    return window.MathJax.typesetPromise(nodes).catch(function (error) {
      console.warn('[rte_ckeditor_pack] MathJax typeset failed', error);
    });
  }

  function boot(root) {
    const scope = root || document;
    if (!hasMathMl(scope)) {
      return Promise.resolve();
    }
    ensureMathJaxConfig();
    return loadScript(MATHJAX_SRC).then(function () {
      return typeset(root || document.body);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(); });
  } else {
    boot();
  }

  // Expose for dynamically loaded content (AJAX / Visual Editor style swaps).
  window.RteCkeditorPackMath = {
    render: boot
  };
})();
