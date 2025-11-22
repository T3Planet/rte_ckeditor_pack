import * as Core from "@ckeditor/ckeditor5-core";

/**
 * RealtimeAdapter - Handles real-time collaboration setup and comment editor configuration
 */
class RealtimeAdapter extends Core.Plugin {
    static DEFAULT_MARKER = '@';
    static COMMENT_PLUGINS = ['Bold', 'Italic', 'Underline', 'Mention'];
    static COMMENT_TOOLBAR = {
        items: ['bold', 'italic', 'underline', '|', 'mention'],
        shouldNotGroupWhenFull: false
    };

    constructor(editor) {
        super();
        this.editor = editor;
        this.channelElement = this.editor.sourceElement || null;
        this._loaderEl = null;

        const config = this.editor.config._config || (this.editor.config._config = {});
        const channelId = config.collaboration?.channelId || this._ensureChannelId(this.channelElement);
        
        if (!channelId) return;

        this.channelId = channelId;
        this.channelSelector = `#${channelId}`;

        // Setup collaboration config
        config.collaboration = { ...(config.collaboration || {}), channelId };
        if (!config.cloudServices) config.cloudServices = {};
        if (!config.cloudServices.documentId) config.cloudServices.documentId = channelId;

        this._showLoader({
            channelId,
            title: this._translate('realtime.adapter.loader.title', 'Connecting to collaboration…'),
            desc: this._translate('realtime.adapter.loader.description', 'Preparing editor and syncing realtime session.')
        });

        this.setPresenceListContainer();
        this._configureCommentsPlugins();
    }

    static get pluginName() {
        return 'RealtimeAdapter';
    }

    /**
     * Configure comment editor with toolbar and plugins
     */
    _configureCommentsPlugins() {
        const commentsConfig = this.editor.config._config.comments?.editorConfig;
        if (!commentsConfig) return;

        // Ensure toolbar exists
        if (!commentsConfig.toolbar) {
            commentsConfig.toolbar = { ...RealtimeAdapter.COMMENT_TOOLBAR };
        }

        // Initialize extraPlugins array
        if (!Array.isArray(commentsConfig.extraPlugins)) {
            commentsConfig.extraPlugins = [];
        }

        // Add plugins function
        const addPlugins = () => {
            if (!this.editor?.plugins?._availablePlugins) return false;

            try {
                const availablePlugins = Array.from(this.editor.plugins._availablePlugins.values());
                const targetPlugins = RealtimeAdapter.COMMENT_PLUGINS;
                const extraCommentsPlugins = availablePlugins.filter(
                    plugin => plugin?.pluginName && targetPlugins.includes(plugin.pluginName)
                );

                if (extraCommentsPlugins.length === 0) return false;

                const existingNames = new Set(
                    commentsConfig.extraPlugins.map(p => p?.pluginName || p?.constructor?.pluginName).filter(Boolean)
                );

                extraCommentsPlugins.forEach(plugin => {
                    if (!existingNames.has(plugin.pluginName)) {
                        commentsConfig.extraPlugins.push(plugin);
                    }
                });

                return true;
            } catch (error) {
                return false;
            }
        };

        addPlugins();
        this.editor.once('ready', addPlugins);
    }

    init() {
        const editor = this.editor;
        const channelElement = this.channelElement || document.querySelector(this.channelSelector);
        if (!this.channelElement && channelElement) {
            this.channelElement = channelElement;
        }

        // Handle incompatible plugins
        const hasRTC = editor.plugins.has('RealTimeCollaborativeEditing');
        const hasSourceEditing = editor.plugins.has('SourceEditing');
        if (hasRTC && hasSourceEditing) {
            console.info('The Source editing plugin is not compatible with real-time collaboration, so it has been disabled. If you need it, please contact us to discuss your use case - https://ckeditor.com/contact/');
            editor.plugins.get('SourceEditing').forceDisabled('SourceEditing');
        }

        // Revision History containers
        if (editor.plugins.has('RevisionHistory') && channelElement) {
            const container = channelElement.closest('.form-wizards-item-element');
            if (container) {
                const { channelId } = this;
                container.insertAdjacentHTML('afterend', `
                    <div id="${channelId}revision_viewer_container" class="revision_viewer_container">
                        <div id="${channelId}revision_viewer_editor" class="revision_viewer_editor"></div>
                        <div id="${channelId}revision_viewer_sidebar" class="revision_viewer_sidebar"></div>
                    </div>
                `);
            }
        }

        // RTC connection events
        if (hasRTC) {
            const update = (msg) => this._updateLoaderDesc(msg);
            const t = (key, fallback) => this._translate(key, fallback);

            editor.on('cs-connection-initializing', () => update(t('realtime.adapter.loader.status.initializing', 'Establishing connection…')));
            editor.on('cs-connection-connected', () => update(t('realtime.adapter.loader.status.connected', 'Connected. Loading document…')));
            editor.on('cs-connection-reconnecting', () => update(t('realtime.adapter.loader.status.reconnecting', 'Connection lost. Reconnecting…')));
            editor.on('cs-connection-error', () => update(t('realtime.adapter.loader.status.error', 'Connection error. Retrying…')));
        }
    }

    afterInit() {
        const editor = this.editor;
        const { channelElement, channelId } = this;

        // Editor event listeners
        editor.on('ready', () => this._hideLoader());
        editor.on('error', () => this._hideLoader());
        editor.on('destroy', () => this._hideLoader());

        // Revision History viewer wiring
        if (editor.plugins.has('RevisionHistory')) {
            const revisionConfig = editor.config._config.revisionHistory;
            revisionConfig.editorContainer = channelElement?.closest('.form-wizards-item-element') ?? null;
            revisionConfig.viewerContainer = document.getElementById(`${channelId}revision_viewer_container`);
            revisionConfig.viewerEditorElement = document.getElementById(`${channelId}revision_viewer_editor`);
            revisionConfig.viewerSidebarContainer = document.getElementById(`${channelId}revision_viewer_sidebar`);
        }

        this._configureCommentMentionFeeds();
    }

    /**
     * Configure mention feeds for comment editor
     */
    _configureCommentMentionFeeds() {
        const commentsConfig = this.editor.config._config.comments?.editorConfig;
        if (!commentsConfig) return;

        const mainMentionConfig = this.editor.config.get('mention');
        
        if (mainMentionConfig?.feeds?.length > 0) {
            const uniqueFeeds = [];
            const seenMarkers = new Set();

            mainMentionConfig.feeds.forEach(feed => {
                if (!feed || typeof feed !== 'object') return;

                const marker = feed.marker || RealtimeAdapter.DEFAULT_MARKER;
                if (!seenMarkers.has(marker)) {
                    seenMarkers.add(marker);
                    uniqueFeeds.push({
                        marker,
                        minimumCharacters: feed.minimumCharacters || 1,
                        feed: feed.feed
                    });
                }
            });

            if (uniqueFeeds.length > 0) {
                commentsConfig.mention = { feeds: uniqueFeeds };
            }
        } else if (typeof commentsConfig.mention === 'undefined') {
            commentsConfig.mention = {
                feeds: [{ marker: RealtimeAdapter.DEFAULT_MARKER, feed: [] }]
            };
        }
    }

    setPresenceListContainer() {
        const cfg = this.editor.config._config.presenceList;
        if (!cfg) return;

        if (!cfg.container) {
            const presenceListContainerId = `${this.channelId}presence-list-container`;
            const existing = document.getElementById(presenceListContainerId);

            if (existing) {
                cfg.container = existing;
            } else {
                const formItem = (this.channelElement || document.querySelector(this.channelSelector))
                    ?.closest('.form-control-wrap');
                
                if (formItem) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'ck-presence-list-container';
                    wrapper.id = presenceListContainerId;
                    formItem.insertBefore(wrapper, formItem.firstChild);
                    this.editor.config._config.presenceList = { container: wrapper };
                }
            }
        }

        if (!cfg.collapseAt) cfg.collapseAt = 1;
    }

    checkIfInitialDataChanged() {
        const initialData = this.editor.config._config.initialData;
        this.editor.on('ready', () => {
            if (initialData !== this.editor.getData()) {
                this.editor.sourceElement.setAttribute('data-editor-value-is-changed', 'true');
            }
        });
    }

    /* ----------------------- Loader Helpers ----------------------- */

    _getMountContainer() {
        const fromForm = this.channelElement?.closest('.form-control-wrap') || null;
        const parent = fromForm || this.channelElement?.parentElement || this.editor.sourceElement?.parentElement;
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
        el.id = `${channelId}-rt-loader`;

        el.innerHTML = `
            <div class="ck-rt-loader__box" aria-label="Editor is loading">
                <div class="ck-rt-loader__row">
                    <div class="ck-rt-loader__spinner" aria-hidden="true"></div>
                    <div class="ck-rt-loader__title">${title || 'Loading editor…'}</div>
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

    /* ----------------------- Channel ID Helpers ----------------------- */

    _ensureChannelId(element) {
        if (!element) return null;

        const existingId = element.id;
        const candidateId = (existingId && existingId !== 'undefined')
            ? existingId
            : element.getAttribute('data-channel-id') || element.getAttribute('data-ck-channel-id');

        const sanitizedId = this._sanitizeChannelId(candidateId);

        if (sanitizedId) {
            if (existingId !== sanitizedId) {
                if (existingId) element.dataset.ckOriginalId = existingId;
                element.id = sanitizedId;
            }
            return sanitizedId;
        }

        const fallbackId = this._generateFallbackId();
        if (fallbackId) {
            if (existingId) element.dataset.ckOriginalId = existingId;
            element.id = fallbackId;
            return fallbackId;
        }

        return null;
    }

    _generateFallbackId() {
        return `ck-channel-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    }

    _sanitizeChannelId(rawId) {
        if (!rawId || typeof rawId !== 'string') return null;

        let normalized = rawId
            .normalize('NFKD')
            .replace(/[^\w-]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/_+/g, '_')
            .replace(/^-+|-+$/g, '')
            .replace(/^_+|_+$/g, '')
            .toLowerCase();

        if (normalized.length > 60) normalized = normalized.slice(0, 60);
        if (normalized.length >= 8) return normalized;

        const hash = this._hashChannelId(rawId);
        return `ckdoc-${hash}`;
    }

    _hashChannelId(value) {
        let hash = 0;
        for (let i = 0; i < value.length; i++) {
            hash = (hash << 5) - hash + value.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash).toString(36).padStart(6, '0').slice(0, 10);
    }

    _syncElementIdWithChannel(element, channelId) {
        if (!element) return document.querySelector(`#${channelId}`);

        if (element.id !== channelId) {
            if (element.id) element.dataset.ckOriginalId = element.id;
            element.id = channelId;
        }

        return element;
    }
}

export default RealtimeAdapter;
