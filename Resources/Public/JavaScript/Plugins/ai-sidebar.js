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
        
        // Set container element IMMEDIATELY in constructor
        // This must happen before AI plugins read the config
        this._setupContainer();
    }

    init() {
        // Also set up in init() as a fallback in case config wasn't ready in constructor
        this._setupContainer();
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
}

export { AISidebar };
export default AISidebar;

