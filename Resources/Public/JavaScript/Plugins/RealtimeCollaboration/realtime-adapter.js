import * as Core from "@ckeditor/ckeditor5-core";
import { getDataFromElement } from '@ckeditor/ckeditor5-utils';
import {
    LoaderOwner,
    showSharedLoader,
    hideSharedLoader,
    updateSharedLoaderDesc,
} from '@t3planet/RteCkeditorPack/ck-shared-loader.js';
import {
    resolveRteMount,
    placePresenceList,
} from '@t3planet/RteCkeditorPack/ck-presence-placement.js';

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

        const config = this.editor.config._config || (this.editor.config._config = {});
        let channelId = config.collaboration?.channelId || this._ensureChannelId(this.channelElement);

        if (!channelId && config.presenceList) {
            channelId = this._generateFallbackId();
        }

        if (!channelId) {
            return;
        }

        this.channelId = channelId;
        this.channelSelector = `#${channelId}`;

        // Setup collaboration config
        config.collaboration = { ...(config.collaboration || {}), channelId };
        if (!config.cloudServices) {
            config.cloudServices = {};
        }
        if (!config.cloudServices.documentId) {
            config.cloudServices.documentId = channelId;
        }

        this._ensureInitialDataForRtc(config);

        this._loaderCopy = {
            channelId,
            title: this._translate('realtime.adapter.loader.title', 'Connecting to collaboration…'),
            desc: this._translate('realtime.adapter.loader.description', 'Preparing editor and syncing realtime session.'),
        };
        this._showLoader(this._loaderCopy);

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

        // VE/FormEngine mount may not be ready in the constructor — ensure loader is visible.
        if (this._loaderCopy) {
            this._showLoader(this._loaderCopy);
        }

        // Handle incompatible plugins
        const hasRTC = editor.plugins.has('RealTimeCollaborativeEditing');
        const hasSourceEditing = editor.plugins.has('SourceEditing');
        if (hasRTC && hasSourceEditing) {
            console.info('The Source editing plugin is not compatible with real-time collaboration, so it has been disabled. If you need it, please contact us to discuss your use case - https://ckeditor.com/contact/');
            editor.plugins.get('SourceEditing').forceDisabled('SourceEditing');
        }

        // Revision History containers — must be siblings of editorContainer.
        // Nesting the viewer inside editorContainer hides it when RH opens.
        this._applyRevisionHistoryContainers();

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

        // Revision History viewer wiring (sibling of editorContainer, never nested)
        this._applyRevisionHistoryContainers();

        this._configureCommentMentionFeeds();

        if (editor.plugins.has('RealTimeCollaborativeEditing')) {
            this._applyRtcPostInitGuards(editor);
        }
        this._enableCollaborationCommandsInRestrictedEditing(editor);
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

        if (!cfg.container || !(cfg.container instanceof HTMLElement)) {
            const presenceListContainerId = `${this.channelId}presence-list-container`;
            const existing = document.getElementById(presenceListContainerId);

            if (existing) {
                cfg.container = existing;
            } else {
                const mount = this._resolveMountContainer();
                if (mount) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'ck-presence-list-container';
                    wrapper.id = presenceListContainerId;
                    mount.appendChild(wrapper);
                    cfg.container = wrapper;
                }
            }
        }

        this._ensurePresenceListPlacement(cfg.container);

        if (!cfg.collapseAt) {
            cfg.collapseAt = 4;
        }

        this.editor.once('ready', () => {
            this._ensurePresenceListPlacement(cfg.container);
        });
    }

    _ensurePresenceListPlacement(container) {
        placePresenceList(container);
    }

    /**
     * Real-time collaboration forbids editor.setData() after the editor is ready.
     *
     * Do NOT call editor.data.set() while CloudServices is connecting/hydrating —
     * that corrupts the CRDT session. Seed empty FormEngine docs via
     * config.initialData in _ensureInitialDataForRtc (constructor-time) instead.
     */
    _applyRtcPostInitGuards(editor) {
        editor.once('ready', () => {
            editor.setData = () => Promise.resolve();
            this._syncVisualEditorInitialData(editor);
        });
    }

    /**
     * Restricted editing disables most commands outside exception regions.
     * Collaboration UX (comments / track changes) must stay available with RTC.
     *
     * @see https://ckeditor.com/docs/ckeditor5/latest/features/restricted-editing.html
     */
    _enableCollaborationCommandsInRestrictedEditing(editor) {
        if (!editor.plugins.has('RestrictedEditingModeEditing')) {
            return;
        }

        const restrictedEditing = editor.plugins.get('RestrictedEditingModeEditing');
        if (typeof restrictedEditing.enableCommand !== 'function') {
            return;
        }

        // Commands that must work for commenting / suggestions while content is locked.
        const collaborationCommands = [
            'addCommentThread',
            'removeCommentThread',
            'updateCommentThread',
            'addComment',
            'updateComment',
            'removeComment',
            'trackChanges',
            'acceptSuggestion',
            'discardSuggestion',
            'acceptAllSuggestions',
            'discardAllSuggestions',
        ];

        collaborationCommands.forEach((commandName) => {
            if (editor.commands.get(commandName)) {
                restrictedEditing.enableCommand(commandName);
            }
        });
    }

    _syncVisualEditorInitialData(editor) {
        const host = editor.sourceElement?.closest('ve-editable-rich-text');
        if (!host?.table) {
            return;
        }

        // Visual Editor is TYPO3 v13+ only; dynamic import keeps v12 backend unaffected.
        import('@typo3/visual-editor/Frontend/stores/data-handler-store.js')
            .then(({ dataHandlerStore }) => {
                if (editor.state === 'destroyed') {
                    return;
                }
                const value = editor.getData({ skipListItemIds: true });
                dataHandlerStore.setInitialData(host.table, host.uid, host.field, value);
            })
            .catch(() => {});
    }

    /**
     * Element CKEditor hides when Revision History opens.
     * Prefer the editor item itself (not .form-control-wrap) so the viewer
     * can sit as a sibling and remain visible.
     */
    _resolveRevisionEditorContainer() {
        const veHost = this.editor.sourceElement?.closest?.('ve-editable-rich-text')
            || this.editor.ui?.element?.closest?.('ve-editable-rich-text')
            || this.channelElement?.closest?.('ve-editable-rich-text');
        if (veHost) {
            return veHost;
        }

        const anchors = [
            this.editor.ui?.element,
            this.editor.sourceElement,
            this.channelElement,
        ].filter(Boolean);

        for (const anchor of anchors) {
            const item = anchor.closest?.('.form-wizards-item-element');
            if (item) {
                return item;
            }
        }

        return this._resolveMountContainer();
    }

    /**
     * Create + wire revisionHistory.* containers.
     * Viewer must never be a descendant of editorContainer.
     */
    _applyRevisionHistoryContainers() {
        const editor = this.editor;
        if (!editor.plugins.has('RevisionHistory')) {
            return;
        }

        const editorContainer = this._resolveRevisionEditorContainer();
        if (!editorContainer) {
            return;
        }

        const { channelId } = this;
        const viewerContainerId = `${channelId}revision_viewer_container`;
        if (!document.getElementById(viewerContainerId)) {
            editorContainer.insertAdjacentHTML('afterend', `
                <div id="${viewerContainerId}" class="revision_viewer_container">
                    <div class="revision_viewer_editor-container">
                        <div id="${channelId}revision_viewer_editor" class="revision_viewer_editor"></div>
                        <div id="${channelId}revision_viewer_sidebar" class="revision_viewer_sidebar sidebar-container"></div>
                    </div>
                </div>
            `);
        }

        const containers = {
            editorContainer,
            viewerContainer: document.getElementById(viewerContainerId),
            viewerEditorElement: document.getElementById(`${channelId}revision_viewer_editor`),
            viewerSidebarContainer: document.getElementById(`${channelId}revision_viewer_sidebar`),
        };

        Object.entries(containers).forEach(([key, value]) => {
            if (value) {
                editor.config.set(`revisionHistory.${key}`, value);
            }
        });
    }

    /**
     * Resolve a stable parent for collaboration UI (presence list, loader, etc.).
     * Supports backend FormEngine (v12–v14) and Visual Editor (ve-editable-rich-text, v13+).
     */
    _resolveMountContainer() {
        return resolveRteMount(
            this.channelElement,
            this.channelSelector ? document.querySelector(this.channelSelector) : null,
            this.editor.sourceElement,
            this.editor.ui?.element,
        );
    }

    _resolveLoaderMount() {
        return this._resolveMountContainer()
            || this.editor?.sourceElement?.closest?.('ve-editable-rich-text')
            || this.editor?.ui?.element?.closest?.('ve-editable-rich-text')
            || this.channelElement?.closest?.('ve-editable-rich-text')
            || null;
    }

    _showLoader({ channelId, title, desc }) {
        showSharedLoader(this._resolveLoaderMount(), {
            owner: LoaderOwner.REALTIME,
            channelId,
            title: title || 'Connecting to collaboration…',
            desc: desc || '',
        });
    }

    _updateLoaderDesc(text) {
        updateSharedLoaderDesc(this._resolveLoaderMount(), LoaderOwner.REALTIME, text || '');
    }

    _hideLoader() {
        hideSharedLoader(this._resolveLoaderMount(), LoaderOwner.REALTIME);
    }

    _translate(key, fallback = '') {
        const value = globalThis?.TYPO3?.lang?.[key];
        return typeof value === 'string' && value.trim() !== '' ? value : fallback;
    }

    /* ----------------------- Channel ID Helpers ----------------------- */

    /**
     * RTC initial document seed.
     *
     * Visual Editor embeds theme markup into the editable host. Feeding that as
     * config.initialData while reconnecting to an existing Cloud document causes
     * mapping-model-position-view-parent-not-found during RTC init.
     * For VE, ClassicEditor reads the element; Cloud remains source of truth when
     * the document already exists. FormEngine keeps the previous seed behaviour.
     */
    _ensureInitialDataForRtc(config) {
        if (!this._hasRtcModules(config)) {
            return;
        }

        const isVisualEditor = !!(
            this.channelElement?.closest?.('ve-editable-rich-text')
            || this.editor?.sourceElement?.closest?.('ve-editable-rich-text')
        );

        if (isVisualEditor) {
            // Do not seed conflicting initialData over themed FE HTML.
            delete config.initialData;
            return;
        }

        const existing = typeof config.initialData === 'string' ? config.initialData.trim() : '';
        if (existing) {
            return;
        }

        const html = this._resolveRtcSourceHtml(this.channelElement);
        if (!html) {
            return;
        }

        config.initialData = html;
        if (typeof this.editor.config.set === 'function') {
            this.editor.config.set('initialData', html);
        }
    }

    _hasRtcModules(config) {
        return (config.importModules || []).some((entry) => {
            const moduleName = typeof entry === 'string' ? entry : entry?.module;
            return typeof moduleName === 'string' && moduleName.includes('real-time-collaboration');
        });
    }

    /**
     * Read HTML the same way ClassicEditor does (textarea value, div innerHTML, VE value).
     */
    _resolveRtcSourceHtml(source) {
        if (!source) {
            return '';
        }

        const fromElement = getDataFromElement(source)?.trim() || '';
        if (fromElement) {
            return fromElement;
        }

        const veHost = source.closest?.('ve-editable-rich-text');
        return veHost?.value?.trim() || '';
    }

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
