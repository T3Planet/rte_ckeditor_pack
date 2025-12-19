/**
 * Editoria11y Integration for TYPO3 CKEditor5
 * Provides real-time accessibility feedback for CKEditor5 instances in TYPO3 backend.
 */

import { Ed11y } from '@t3planet/RteCkeditorPack/editoria11y.min.js';

// Utility to debounce function calls
const debounce = (func, delay) => {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
};

class Editoria11yIntegration {
    constructor(config = {}) {
        this.editoria11y = null;
        this.ckeditorInstances = new Set();
        this.mutationObserver = null;
        this.isInitialized = false;

        this.config = this._getMergedConfig(config);
        this.debouncedScan = debounce(this._scanAndAlign.bind(this), 150);

        this.init();
    }

    _getMergedConfig(initialConfig) {
        const configElement = document.getElementById('editoria11y-config');
        const cssPath = configElement?.dataset.cssPath;

        return {
            checkRoots: ['[contenteditable="true"]'],
            preventCheckingIfAbsent: ['[contenteditable="true"]'],
            ignoreByKey : {
              'img': '[aria-hidden], [aria-hidden] img', // disable alt text tests on aria-hidden images by default
              'a': '[aria-hidden][tabindex]', // disable link text check on properly disabled links
            },
            ignoreElements: '.ck-editor__top *',
            hiddenHandlers: ["[hidden]", ".accordion-panel"],
            checkVisible: true,
            allowHide: false,
            allowOK: true,
            ...initialConfig,
            ...(cssPath ? { cssUrls: [cssPath] } : {}),
            ...(window.editoria11yConfig || {}),
        };
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupIntegration());
        } else {
            this.setupIntegration();
        }
    }

    async setupIntegration() {
        if (!this._isBackendEditForm()) return;

        try {
            if (typeof Ed11y === 'undefined') {
                throw new Error('Ed11y class not found.');
            }
            this.initializeEditoria11y();
            this.watchForCKEditorInstances();
        } catch (error) {
            console.error('Editoria11y Integration failed:', error);
        }
    }

    _isBackendEditForm() {
        return document.querySelector('.t3js-formengine-field-item, typo3-rte-ckeditor-ckeditor5');
    }

    initializeEditoria11y() {
        if (this.isInitialized) return;

        const contenteditableElements = document.querySelectorAll('[contenteditable="true"]');
        if (contenteditableElements.length === 0) {
            setTimeout(() => this.initializeEditoria11y(), 500);
            return;
        }

        try {
            this.editoria11y = new Ed11y({
                ...this.config,
                jumpTo: this._safeJumpTo.bind(this),
                onReady: () => {
                    this.isInitialized = true;
                    this._scanAndAlign();
                    this.scanExistingEditors();
                }
            });
        } catch (error) {
            console.error('Failed to initialize Editoria11y:', error);
        }
    }

    _safeJumpTo(element) {
        const scrollOptions = { behavior: 'smooth', block: 'center' };
        try {
            if (element && typeof element.scrollIntoView === 'function') {
                element.scrollIntoView(scrollOptions);
                return true;
            }
        } catch (error) {
            console.warn('Editoria11y: scrollIntoView failed, attempting fallback.', error);
        }

        const contenteditable = document.querySelector('[contenteditable="true"]');
        if (contenteditable) {
            try {
                contenteditable.scrollIntoView(scrollOptions);
                return true;
            } catch (error) {
                console.warn('Editoria11y: Fallback scrollIntoView failed.', error);
            }
        }
        return false;
    }

    watchForCKEditorInstances() {
        this.mutationObserver = new MutationObserver(mutations => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== Node.ELEMENT_NODE) continue;

                    const editors = [
                        ...(node.matches('typo3-rte-ckeditor-ckeditor5') ? [node] : []),
                        ...node.querySelectorAll('typo3-rte-ckeditor-ckeditor5')
                    ];
                    editors.forEach(editor => this.handleNewCKEditorInstance(editor));
                }
            }
        });

        this.mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    handleNewCKEditorInstance(element) {
        if (this.ckeditorInstances.has(element)) return;
        this.ckeditorInstances.add(element);

        let retries = 0;
        const maxRetries = 50; // Wait up to 5 seconds

        const checkCKEditorReady = () => {
            if (retries++ > maxRetries) {
                console.warn('CKEditor instance not found for element:', element);
                return;
            }

            const textarea = element.querySelector('textarea[slot="textarea"]');
            if (textarea?.ckeditorInstance) {
                this.setupCKEditorIntegration(textarea.ckeditorInstance, element);
            } else {
                setTimeout(checkCKEditorReady, 100);
            }
        };
        checkCKEditorReady();
    }

    setupCKEditorIntegration(ckeditorInstance, element) {
        ckeditorInstance.model.document.on('change:data', () => this.debouncedScan());
        ckeditorInstance.on('ready', () => this.debouncedScan());
        ckeditorInstance.on('destroy', () => {
            this.ckeditorInstances.delete(element);
            this.debouncedScan();
        });
    }

    scanExistingEditors() {
        document.querySelectorAll('typo3-rte-ckeditor-ckeditor5')
            .forEach(element => this.handleNewCKEditorInstance(element));
    }

    _scanAndAlign() {
        if (!this.editoria11y || !this.isInitialized) return;

        this.editoria11y.scan();
        if (this.editoria11y.alignButtons) {
            setTimeout(() => this.editoria11y.alignButtons(), 100);
        }
    }

    destroy() {
        this.mutationObserver?.disconnect();
        this.editoria11y?.destroy();
        this.ckeditorInstances.clear();
        this.isInitialized = false;
    }
}

// Initialize integration automatically
const editoria11yIntegration = new Editoria11yIntegration();

// Export for TYPO3 module system or direct use
export default function(...args) {
    const config = args.length > 0 ? args[0] : {};
    return new Editoria11yIntegration(config);
}

export { Editoria11yIntegration, editoria11yIntegration };