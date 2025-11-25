import { Plugin } from '@ckeditor/ckeditor5-core';

/**
 * AI Sidebar Container Plugin
 * Sets up the sidebar container element for AI features when container type is 'sidebar'
 * Must run before AI plugins initialize to set the container element
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
        
        // Set container element IMMEDIATELY in constructor
        // This must happen before AI plugins read the config
        this._setupContainer();
        
        // Show loader if AI is enabled
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
        // Also set up in init() as a fallback in case config wasn't ready in constructor
        this._setupContainer();
    }

    afterInit() {
        // Hide loader when editor is ready
        this.editor.on('ready', () => {
            this._hideLoader();
        });
        
        // Also hide on error or destroy
        this.editor.on('error', () => {
            this._hideLoader();
        });
        
        this.editor.on('destroy', () => {
            this._hideLoader();
        });
    }

    _setupContainer() {
        const channelId = this.editor.sourceElement?.id;
        if (!channelId) {
            return;
        }

        // Get config from _config (this is where CKEditor stores runtime config)
        const config = this.editor.config._config || (this.editor.config._config = {});
        
        // Ensure AI config structure exists
        if (!config.ai) {
            config.ai = {};
        }
        if (!config.ai.container) {
            config.ai.container = {};
        }
        
        // Check what container type is configured
        // The config should come from the saved user configuration merged into editor config
        let containerType = config.ai.container.type;
        
        // If container type is 'sidebar', we need to create the container element
        if (containerType === 'sidebar') {
            const containerId = channelId + '-ai-sidebar-container';
            
            // Find or create the container element
            let containerElement = document.getElementById(containerId);
            
            if (!containerElement) {
                const formItem = document.querySelector('#' + channelId)?.closest('.form-control-wrap');
                if (formItem) {
                    containerElement = document.createElement("div");
                    containerElement.id = containerId;
                    containerElement.className = "ck-ai-sidebar-container";
                    
                    // Insert before the first child of formItem
                    formItem.insertBefore(containerElement, formItem.firstChild);
                    formItem.classList.add('rte-ckeditor-ai-sidebar');
                } else {
                    // Try alternative: append to body as fallback
                    containerElement = document.createElement("div");
                    containerElement.id = containerId;
                    containerElement.className = "ck-ai-sidebar-container";
                    document.body.appendChild(containerElement);
                }
            }
            
            // CRITICAL: Set the container element in config - AI plugins check this immediately
            if (containerElement) {
                // Set on _config (used by CKEditor internally)
                config.ai.container.element = containerElement;
                config.ai.container.type = 'sidebar';
                
                // Ensure it's also accessible via editor.config.get('ai')
                // This is the canonical way CKEditor accesses config
                try {
                    // Try to set it directly on the config object
                    const aiConfig = this.editor.config.get('ai') || {};
                    if (!aiConfig.container) {
                        aiConfig.container = {};
                    }
                    aiConfig.container.element = containerElement;
                    aiConfig.container.type = 'sidebar';
                } catch (e) {
                    // Config might be read-only during initialization, that's okay
                    // The _config should be enough
                }
            } else {
                // If container creation failed, fallback to overlay to prevent errors
                console.warn('AISidebar: Could not create sidebar container, falling back to overlay');
                config.ai.container.type = 'overlay';
                delete config.ai.container.element;
            }
        } else if (!containerType) {
            // If no container type is set, default to overlay
            config.ai.container.type = 'overlay';
        }
    }

    /**
     * Check if AI is enabled in the configuration
     */
    _isAIEnabled() {
        const config = this.editor.config._config || {};
        return config.ai && (config.ai.container || config.ai.chat);
    }

    /* ----------------------- Loader Helpers ----------------------- */

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
        return (typeof value === 'string' && value.trim() !== '') ? value : fallback;
    }
}

export { AISidebar };
export default AISidebar;

