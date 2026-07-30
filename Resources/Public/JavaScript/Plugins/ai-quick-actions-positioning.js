import { Plugin } from '@ckeditor/ckeditor5-core';

/**
 * Anchors the AI Quick Actions dropdown panel to its toolbar trigger inside
 * TYPO3 backend hosts (scrollable scaffold, AI sidebar/overlay stacking).
 *
 * CKEditor keeps the main Quick Actions panel in the toolbar DOM with
 * CSS-relative positioning. In FormEngine / Visual Editor that often places
 * the panel over the editable instead of beside the button. Nested group
 * balloons already use .ck-body-wrapper; this plugin only fixes the primary
 * dropdown panel via position:fixed + getBoundingClientRect.
 */
class AiQuickActionsPositioning extends Plugin {
    static get pluginName() {
        return 'AiQuickActionsPositioning';
    }

    constructor(editor) {
        super(editor);
        this._panel = null;
        this._button = null;
        this._mutationObserver = null;
        this._rafId = 0;
        // Arrow listeners — avoid constructor .bind() on prototype methods
        // (unsafe during CKEditor Plugin construction).
        this._boundSync = () => this._syncPanelPosition();
        this._boundScheduleSync = () => this._scheduleSync();
    }

    afterInit() {
        this.editor.once('ready', () => {
            this._setup();
        });
    }

    destroy() {
        this._teardown();
        return super.destroy();
    }

    _setup() {
        const root = this.editor.ui?.view?.element;
        if (!root) {
            return;
        }

        this._mutationObserver = new MutationObserver(this._boundScheduleSync);
        this._mutationObserver.observe(root, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style'],
            childList: true,
        });

        // Capture scroll in TYPO3 scaffold / iframe as well as window resize.
        window.addEventListener('scroll', this._boundSync, true);
        window.addEventListener('resize', this._boundSync);

        this._scheduleSync();
    }

    _teardown() {
        if (this._mutationObserver) {
            this._mutationObserver.disconnect();
            this._mutationObserver = null;
        }
        window.removeEventListener('scroll', this._boundSync, true);
        window.removeEventListener('resize', this._boundSync);
        if (this._rafId) {
            cancelAnimationFrame(this._rafId);
            this._rafId = 0;
        }
        this._clearFixedStyles();
        this._panel = null;
        this._button = null;
    }

    _scheduleSync() {
        if (this._rafId) {
            return;
        }
        this._rafId = requestAnimationFrame(() => {
            this._rafId = 0;
            this._syncPanelPosition();
        });
    }

    _resolveElements() {
        const root = this.editor.ui?.view?.element;
        if (!root) {
            return { dropdown: null, panel: null, button: null };
        }

        const dropdown = root.querySelector('.ck-ai-quick-actions-dropdown');
        if (!dropdown) {
            return { dropdown: null, panel: null, button: null };
        }

        const panel = dropdown.querySelector(':scope > .ck-dropdown__panel');
        const button = dropdown.querySelector('.ck-dropdown__button, .ck-ai-quick-actions-button')
            || dropdown.querySelector('button');

        return { dropdown, panel, button };
    }

    _syncPanelPosition() {
        const { panel, button } = this._resolveElements();
        if (!panel || !button) {
            this._clearFixedStyles();
            return;
        }

        this._panel = panel;
        this._button = button;

        const isOpen = panel.classList.contains('ck-dropdown__panel-visible')
            && !panel.classList.contains('ck-hidden');

        if (!isOpen) {
            this._clearFixedStyles();
            return;
        }

        // Temporarily clear directional CSS so measurement matches final layout.
        panel.classList.add('rte-ckeditor-ai-qa-panel--anchored');
        panel.style.setProperty('position', 'fixed', 'important');
        panel.style.setProperty('transform', 'none', 'important');
        panel.style.setProperty('right', 'auto', 'important');
        panel.style.setProperty('bottom', 'auto', 'important');
        panel.style.setProperty('z-index', '10050', 'important');

        const buttonRect = button.getBoundingClientRect();
        const panelWidth = Math.max(panel.offsetWidth || 0, panel.scrollWidth || 0, 280);
        const panelHeight = Math.max(panel.offsetHeight || 0, panel.scrollHeight || 0, 120);
        const margin = 8;

        let top = buttonRect.bottom;
        let left = buttonRect.left;

        // Prefer opening below; flip above when the panel would leave the viewport.
        if (top + panelHeight > window.innerHeight - margin) {
            const above = buttonRect.top - panelHeight;
            if (above >= margin) {
                top = above;
            } else {
                top = Math.max(margin, window.innerHeight - panelHeight - margin);
            }
        }

        // Prefer left-aligned with the trigger; flip when near the right edge
        // (common with AI sidebar reducing the editor column width).
        if (left + panelWidth > window.innerWidth - margin) {
            left = Math.max(margin, buttonRect.right - panelWidth);
        }
        if (left < margin) {
            left = margin;
        }

        panel.style.setProperty('top', `${Math.round(top)}px`, 'important');
        panel.style.setProperty('left', `${Math.round(left)}px`, 'important');
    }

    _clearFixedStyles() {
        const panel = this._panel
            || this.editor.ui?.view?.element?.querySelector(
                '.ck-ai-quick-actions-dropdown > .ck-dropdown__panel',
            );

        if (!panel) {
            return;
        }

        panel.classList.remove('rte-ckeditor-ai-qa-panel--anchored');
        panel.style.removeProperty('position');
        panel.style.removeProperty('top');
        panel.style.removeProperty('left');
        panel.style.removeProperty('right');
        panel.style.removeProperty('bottom');
        panel.style.removeProperty('transform');
        panel.style.removeProperty('z-index');
    }
}

export default AiQuickActionsPositioning;
export { AiQuickActionsPositioning };
