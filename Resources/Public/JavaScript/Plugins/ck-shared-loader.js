/**
 * Shared RTE host loader for AI + Realtime Collaboration.
 *
 * One overlay per root mount; removed when every owner releases.
 * VE: bound to <ve-editable-rich-text> (CE boundary).
 * FormEngine: editor column / wizards wrap.
 */

/** @typedef {{ title: string, desc: string }} LoaderCopy */
/** @typedef {{
 *   el: HTMLElement,
 *   host: HTMLElement,
 *   owners: Map<string, LoaderCopy>,
 *   statusOwner: string|null,
 *   statusDesc: string|null,
 * }} LoaderState
 */

export const LoaderOwner = Object.freeze({
    AI: 'ai',
    REALTIME: 'realtime',
});

/** @type {WeakMap<HTMLElement, LoaderState>} */
const statesByRoot = new WeakMap();

/**
 * @param {HTMLElement|null|undefined} el
 * @returns {HTMLElement|null}
 */
function closestVe(el) {
    if (!el) {
        return null;
    }
    if (el.matches?.('ve-editable-rich-text')) {
        return el;
    }
    return el.closest?.('ve-editable-rich-text') || null;
}

/**
 * @param {HTMLElement|null|undefined} mount
 * @returns {HTMLElement|null}
 */
function resolveLoaderHost(mount) {
    if (!mount) {
        return null;
    }

    const veRoot = closestVe(mount);
    if (veRoot) {
        // Full CE boundary so the frosted loader covers editor + AI (sidebar/overlay).
        return veRoot;
    }

    const hasAiSidebar = mount.classList.contains('rte-ckeditor-ai-sidebar')
        || !!mount.querySelector('.ck-ai-sidebar-container');

    // FormEngine: cover the wizards wrap (editor + AI) while loading.
    if (hasAiSidebar) {
        return mount.querySelector(':scope > .form-wizards-wrap')
            || mount.querySelector('.form-wizards-wrap')
            || mount;
    }

    return mount.querySelector(':scope > .form-wizards-wrap')
        || mount.querySelector('.form-wizards-wrap')
        || mount;
}

/**
 * @param {string} key
 * @param {string} [fallback]
 * @returns {string}
 */
function translate(key, fallback = '') {
    const value = globalThis?.TYPO3?.lang?.[key];
    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

/**
 * @param {string} [channelId]
 * @returns {HTMLElement}
 */
function createLoaderElement(channelId = 'ck') {
    const el = document.createElement('div');
    el.className = 'ck-rt-loader';
    el.id = `${channelId}-shared-loader`;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = `
        <div class="ck-rt-loader__box" aria-label="Editor is loading">
            <div class="ck-rt-loader__row">
                <div class="ck-rt-loader__spinner" aria-hidden="true"></div>
                <div class="ck-rt-loader__title"></div>
            </div>
            <div class="ck-rt-loader__desc"></div>
        </div>
    `;
    return el;
}

/**
 * @param {HTMLElement} host
 * @param {HTMLElement} el
 */
function ensureOnHost(host, el) {
    host.classList.add('rte-ckeditor-loader-host');
    if (getComputedStyle(host).position === 'static') {
        host.style.position = 'relative';
    }
    if (el.parentElement !== host || host.lastElementChild !== el) {
        host.appendChild(el);
    }
}

/**
 * @param {LoaderState} state
 */
function teardownState(state) {
    state.el?.remove();
    state.host?.classList.remove('rte-ckeditor-loader-host');
}

/**
 * @param {LoaderState} state
 */
function render(state) {
    const titleEl = state.el.querySelector('.ck-rt-loader__title');
    const descEl = state.el.querySelector('.ck-rt-loader__desc');
    if (!titleEl || !descEl) {
        return;
    }

    const copies = [...state.owners.values()];
    if (copies.length === 0) {
        return;
    }

    if (copies.length > 1) {
        titleEl.textContent = translate('shared.loader.title', 'Preparing editor…');
        descEl.textContent = state.statusDesc
            || translate(
                'shared.loader.description',
                'Connecting AI assistant and realtime collaboration…'
            );
        return;
    }

    const only = copies[0];
    titleEl.textContent = only.title;
    descEl.textContent = state.statusDesc || only.desc;
}

/**
 * @param {HTMLElement|null|undefined} rootMount
 * @returns {LoaderState|null}
 */
function getState(rootMount) {
    if (!rootMount) {
        return null;
    }
    const state = statesByRoot.get(rootMount);
    if (!state?.el?.isConnected) {
        if (state) {
            teardownState(state);
            statesByRoot.delete(rootMount);
        }
        return null;
    }
    return state;
}

/**
 * @param {HTMLElement|null|undefined} rootMount
 * @param {{ owner: string, channelId?: string, title?: string, desc?: string }} options
 * @returns {HTMLElement|null}
 */
export function showSharedLoader(rootMount, { owner, channelId = 'ck', title = '', desc = '' }) {
    if (!rootMount || !owner) {
        return null;
    }

    const host = resolveLoaderHost(rootMount);
    if (!host) {
        return null;
    }

    let state = getState(rootMount);
    if (!state) {
        const el = createLoaderElement(channelId);
        state = {
            el,
            host,
            owners: new Map(),
            statusOwner: null,
            statusDesc: null,
        };
        statesByRoot.set(rootMount, state);
        ensureOnHost(host, el);
    } else if (state.host !== host) {
        state.host.classList.remove('rte-ckeditor-loader-host');
        state.host = host;
        ensureOnHost(host, state.el);
    }

    state.owners.set(owner, {
        title: title || 'Loading…',
        desc: desc || '',
    });
    render(state);
    return state.el;
}

/**
 * @param {HTMLElement|null|undefined} rootMount
 * @param {string} owner
 * @param {string} text
 */
export function updateSharedLoaderDesc(rootMount, owner, text) {
    const state = getState(rootMount);
    if (!state?.owners.has(owner)) {
        return;
    }

    state.statusOwner = owner;
    state.statusDesc = text || '';
    const entry = state.owners.get(owner);
    if (entry) {
        entry.desc = text || entry.desc;
    }
    render(state);
}

/**
 * @param {HTMLElement|null|undefined} rootMount
 * @param {string} owner
 * @returns {HTMLElement|null}
 */
export function hideSharedLoader(rootMount, owner) {
    const state = getState(rootMount);
    if (!state || !owner) {
        return null;
    }

    state.owners.delete(owner);
    if (state.statusOwner === owner) {
        state.statusOwner = null;
        state.statusDesc = null;
    }

    if (state.owners.size === 0) {
        teardownState(state);
        statesByRoot.delete(rootMount);
        return null;
    }

    render(state);
    return state.el;
}

/**
 * @param {HTMLElement|null|undefined} rootMount
 * @returns {HTMLElement|null}
 */
export function reparentSharedLoader(rootMount) {
    const state = getState(rootMount);
    if (!state) {
        return null;
    }

    const host = resolveLoaderHost(rootMount);
    if (!host) {
        return state.el;
    }

    if (state.host !== host) {
        state.host.classList.remove('rte-ckeditor-loader-host');
        state.host = host;
    }
    ensureOnHost(host, state.el);
    return state.el;
}
