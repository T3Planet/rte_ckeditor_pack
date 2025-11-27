import { Plugin } from '@ckeditor/ckeditor5-core';

/**
 * AI Sidebar Container Plugin
 */
class AISidebar extends Plugin {
    static get pluginName() {
        return 'AISidebar';
    }

    static get requires() {
        return [];
    }

    constructor(editor) {
        super(editor);
        this.editor = editor;
        this._loaderEl = null;
        this._revisionObserver = null;
        this._isRevisionViewer = this._detectRevisionViewerEditor();
        this._aiContainerEl = null;

        if (this._isRevisionViewer) {
            return;
        }

        this._setupContainer();

        if (this._isAIEnabled()) {
            const channelId = this.editor.sourceElement?.id;
            if (channelId) {
                this._showLoader({
                    channelId,
                    title: this._translate('ai.sidebar.loader.title', 'Loading AI features…'),
                    desc: this._translate('ai.sidebar.loader.description', 'Initializing AI assistant…')
                });
            }
        }
    }

    init() {
        if (this._isRevisionViewer) {
            return;
        }
        this._setupContainer();
    }

    afterInit() {
        if (this._isRevisionViewer) {
            return;
        }

        this.editor.on('ready', () => this._hideLoader());
        this.editor.on('error', () => this._hideLoader());
        this.editor.on('destroy', () => {
            this._hideLoader();
            this._disconnectRevisionObserver();
        });

        this._setupRevisionHistorySync();
    }

    _detectRevisionViewerEditor() {
        const sourceEl = this.editor.sourceElement;
        if (!sourceEl) {
            return false;
        }

        if (sourceEl.classList?.contains('revision_viewer_editor')) {
            return true;
        }

        if (sourceEl.closest('.revision_viewer_container')) {
            return true;
        }

        return false;
    }

    _getAiConfig() {
        const fullConfig = this.editor.config._config || (this.editor.config._config = {});
        if (!fullConfig.ai) {
            fullConfig.ai = {};
        }
        if (!fullConfig.ai.container) {
            fullConfig.ai.container = {};
        }
        return fullConfig.ai;
    }

    _isAIEnabled() {
        const config = this.editor.config._config || {};
        return !!(config.ai && (config.ai.container || config.ai.chat));
    }

    _setupContainer() {
        const sourceEl = this.editor.sourceElement;
        const channelId = sourceEl?.id;
        if (!channelId) {
            return;
        }

        const aiConfig = this._getAiConfig();
        const containerType = aiConfig.container.type;

        if (containerType === 'sidebar') {
            const containerId = `${channelId}-ai-sidebar-container`;
            let containerElement = document.getElementById(containerId);

            if (!containerElement) {
                const formItem = document.querySelector(`#${channelId}`)?.closest('.form-control-wrap');

                if (formItem) {
                    const formWizardsWrap = formItem.querySelector('.form-wizards-wrap');
                    const revisionViewerContainer = document.getElementById(`${channelId}revision_viewer_container`);

                    if (formWizardsWrap) {
                        if (revisionViewerContainer && revisionViewerContainer.parentElement === formWizardsWrap) {
                            revisionViewerContainer.insertAdjacentHTML('beforebegin', `
                                <div id="${containerId}" class="ck-ai-sidebar-container"></div>
                            `);
                            containerElement = document.getElementById(containerId);
                        } else {
                            formWizardsWrap.insertAdjacentHTML('afterbegin', `
                                <div id="${containerId}" class="ck-ai-sidebar-container"></div>
                            `);
                            containerElement = document.getElementById(containerId);
                        }
                    } else {
                        containerElement = document.createElement('div');
                        containerElement.id = containerId;
                        containerElement.className = 'ck-ai-sidebar-container';
                        formItem.insertBefore(containerElement, formItem.firstChild);
                    }

                    formItem.classList.add('rte-ckeditor-ai-sidebar');
                } else {
                    containerElement = document.createElement('div');
                    containerElement.id = containerId;
                    containerElement.className = 'ck-ai-sidebar-container';
                    document.body.appendChild(containerElement);
                }
            }

            if (containerElement) {
                this._aiContainerEl = containerElement;
                aiConfig.container.element = containerElement;
                aiConfig.container.type = 'sidebar';

                try {
                    const runtimeAiConfig = this.editor.config.get('ai') || {};
                    if (!runtimeAiConfig.container) {
                        runtimeAiConfig.container = {};
                    }
                    runtimeAiConfig.container.element = containerElement;
                    runtimeAiConfig.container.type = 'sidebar';
                } catch (e) {
                    // Config might be read-only during initialization
                }
            } else {
                console.warn('AISidebar: Could not create sidebar container, falling back to overlay');
                aiConfig.container.type = 'overlay';
                delete aiConfig.container.element;
            }
        } else if (!containerType) {
            aiConfig.container.type = 'overlay';
        }
    }

    _setupRevisionHistorySync() {
        if (!this._aiContainerEl) {
            return;
        }

        const sourceEl = this.editor.sourceElement;
        if (!sourceEl) {
            return;
        }

        let editorContainer = sourceEl.closest('.form-wizards-item-element');

        if (!editorContainer) {
            const rhConfig = this.editor.config._config?.revisionHistory;
            if (rhConfig?.editorContainer) {
                editorContainer = rhConfig.editorContainer;
            }
        }

        if (!editorContainer) {
            return;
        }

        const aiEl = this._aiContainerEl;
        const originalDisplay = aiEl.style.display || '';

        const applyVisibility = () => {
            const computedStyle = window.getComputedStyle(editorContainer);
            const isHidden =
                editorContainer.style.display === 'none' ||
                editorContainer.hidden ||
                editorContainer.getAttribute('aria-hidden') === 'true' ||
                computedStyle.display === 'none';

            if (isHidden) {
                if (aiEl.style.display !== 'none') {
                    aiEl.dataset.ckAiSidebarPrevDisplay = aiEl.style.display || originalDisplay;
                    aiEl.style.display = 'none';
                }
            } else {
                const prev = aiEl.dataset.ckAiSidebarPrevDisplay || originalDisplay;
                aiEl.style.display = prev;
            }
        };

        applyVisibility();

        this._revisionObserver = new MutationObserver(applyVisibility);
        this._revisionObserver.observe(editorContainer, {
            attributes: true,
            attributeFilter: ['style', 'hidden', 'aria-hidden', 'class']
        });
    }

    _disconnectRevisionObserver() {
        if (this._revisionObserver) {
            this._revisionObserver.disconnect();
            this._revisionObserver = null;
        }
    }

    _getMountContainer() {
        const channelElement = this.editor.sourceElement;
        const fromForm = channelElement?.closest('.form-control-wrap') || null;
        const parent = fromForm || channelElement?.parentElement || this.editor.sourceElement?.parentElement;

        if (!parent) return null;

        const style = window.getComputedStyle(parent);
        if (style.position === 'static') {
            parent.style.position = 'relative';
        }
        return parent;
    }

    _showLoader({ channelId, title, desc }) {
        const mount = this._getMountContainer();
        if (!mount || this._loaderEl?.isConnected) return;

        const el = document.createElement('div');
        el.className = 'ck-rt-loader';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.id = `${channelId}-ai-loader`;

        el.innerHTML = `
            <div class="ck-rt-loader__box" aria-label="AI is loading">
                <div class="ck-rt-loader__row">
                    <div class="ck-rt-loader__spinner" aria-hidden="true"></div>
                    <div class="ck-rt-loader__title">${title || 'Loading AI Chat…'}</div>
                </div>
                <div class="ck-rt-loader__desc">${desc || ''}</div>
            </div>
        `;

        mount.appendChild(el);
        this._loaderEl = el;
    }

    _updateLoaderDesc(text) {
        const descEl = this._loaderEl?.querySelector('.ck-rt-loader__desc');
        if (descEl) descEl.textContent = text || '';
    }

    _hideLoader() {
        if (this._loaderEl?.parentNode) {
            this._loaderEl.parentNode.removeChild(this._loaderEl);
        }
        this._loaderEl = null;
    }

    _translate(key, fallback = '') {
        const scope = typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : {});
        const translations = scope?.TYPO3?.lang;
        const value = translations?.[key];
        return typeof value === 'string' && value.trim() !== '' ? value : fallback;
    }
}

export { AISidebar };
export default AISidebar;
