/**
 * Presence list placement + RTE host resolution for FormEngine (v12–v14)
 * and Visual Editor (v13+ only; selectors no-op on v12).
 *
 * - FormEngine: presence above the CKEditor toolbox
 * - Visual Editor: presence below the editor
 */

/**
 * Stable RTE mount used by AI, Realtime, loader, and presence.
 * Order is fixed so plugins share the same host key across TYPO3 versions.
 *
 * @param {...(Element|null|undefined)} anchors
 * @returns {HTMLElement|null}
 */
export function resolveRteMount(...anchors) {
    const list = anchors.filter(Boolean);
    const selectors = [
        've-editable-rich-text',
        '.form-control-wrap',
        '.form-wizards-item-element',
    ];

    for (const anchor of list) {
        for (const selector of selectors) {
            const mount = anchor.closest?.(selector);
            if (mount) {
                return mount;
            }
        }
    }

    return list[0]?.parentElement || null;
}

/**
 * @param {ParentNode|null|undefined} root
 * @returns {HTMLElement|null}
 */
function findCkEditorRoot(root) {
    if (!root?.querySelector) {
        return null;
    }
    return root.querySelector(':scope > .ck.ck-editor, :scope > .ck-editor');
}

/**
 * @param {HTMLElement} container
 * @param {HTMLElement|null} [preferredColumn]
 */
function placeBelowEditor(container, preferredColumn = null) {
    const veHost = container.closest('ve-editable-rich-text');
    if (!veHost) {
        return;
    }

    const column = preferredColumn
        || veHost.querySelector(':scope > .rte-ckeditor-ai-editor-column')
        || veHost.querySelector('.rte-ckeditor-ai-editor-column')
        || veHost;

    if (container.parentElement !== column || column.lastElementChild !== container) {
        column.appendChild(container);
    }
}

/**
 * @param {HTMLElement} container
 * @param {HTMLElement|null} [preferredColumn]
 */
function placeAboveToolbox(container, preferredColumn = null) {
    const host = container.closest('.form-control-wrap')
        || preferredColumn?.closest?.('.form-control-wrap')
        || container.parentElement;
    if (!host) {
        return;
    }

    // v12 uses both form-wizards-element + form-wizards-item-element; v13+ uses item-element only.
    const editorItem = preferredColumn
        || host.querySelector('.form-wizards-wrap > .form-wizards-item-element')
        || host.querySelector('.form-wizards-item-element')
        || host;

    const ckEditor = findCkEditorRoot(editorItem);
    if (ckEditor) {
        if (container.parentElement !== editorItem || container.nextElementSibling !== ckEditor) {
            editorItem.insertBefore(container, ckEditor);
        }
        return;
    }

    if (container.parentElement !== editorItem || editorItem.firstElementChild !== container) {
        editorItem.insertBefore(container, editorItem.firstElementChild);
    }
}

/**
 * Place a `.ck-presence-list-container` for the current host context.
 *
 * @param {HTMLElement|null|undefined} container
 * @param {HTMLElement|null} [preferredColumn] AI editor column / FormEngine item when known
 */
export function placePresenceList(container, preferredColumn = null) {
    if (!container?.isConnected) {
        return;
    }

    if (container.closest('ve-editable-rich-text')) {
        placeBelowEditor(container, preferredColumn);
        return;
    }

    placeAboveToolbox(container, preferredColumn);
}

/**
 * Find and place the presence list under a mount (FormEngine or VE).
 *
 * @param {HTMLElement|null|undefined} mount
 * @param {HTMLElement|null} [preferredColumn]
 * @returns {HTMLElement|null}
 */
export function placePresenceListInMount(mount, preferredColumn = null) {
    if (!mount) {
        return null;
    }

    const presence = mount.querySelector('.ck-presence-list-container');
    if (!presence) {
        return null;
    }

    placePresenceList(presence, preferredColumn);
    return presence;
}
