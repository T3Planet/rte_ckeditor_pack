import { Plugin } from '@ckeditor/ckeditor5-core';

/**
 * Shared registry of AISidebar instances (multi-CE VE / FormEngine).
 * @type {Set<AISidebar>}
 */
const aiSidebarInstances = new Set();

/**
 * AI Sidebar / Overlay Container Plugin
 *
 * - Sidebar: RTE + toolbar | AI panel (FormEngine + VE)
 * - Overlay: AI panel absolutely scoped to the RTE host (not full page)
 * Visual Editor hosts (ve-editable-rich-text) exist on TYPO3 v13+ only; v12 uses FormEngine.
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
        this._editorDomId = null;
        this._isRevisionViewer = this._detectRevisionViewerEditor();
        this._aiContainerEl = null;
        this._overlayMode = false;
        this._boundSyncOverlay = null;
        this._overlayListenersBound = false;
        this._overlayPositionReady = false;
        this._overlayBootstrapped = false;
        this._wantsVisibleByDefault = true;
        this._didInitialOverlayReveal = false;
        this._overlayDomObserver = null;
        this._activatingToggle = false;
        this._mountResizeObserver = null;
        this._aiMinHeight = 280;

        if (this._isRevisionViewer) {
            return;
        }

        aiSidebarInstances.add(this);

        this._ensureCollaborationChannelId();
        this._setupContainer({ allowOverlayFallback: false });

        if (this._isAIEnabled()) {
            const domId = this._resolveEditorId();
            if (domId) {
                this._showLoader({
                    channelId: domId,
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
        this._setupContainer({ allowOverlayFallback: true });
    }

    afterInit() {
        if (this._isRevisionViewer) {
            return;
        }

        this.editor.on('ready', () => {
            this._hideLoader();
            if (this._overlayMode) {
                this._initOverlayBehavior();
            } else {
                this._finalizeSidebarLayout();
            }
        });
        this.editor.on('error', () => this._hideLoader());
        this.editor.on('destroy', () => {
            this._hideLoader();
            this._disconnectRevisionObserver();
            this._teardownOverlayBehavior();
            aiSidebarInstances.delete(this);
        });

        this._setupRevisionHistorySync();
    }

    /**
     * After CKEditor UI mounts: split layout (editor+toolbar | AI), no overlap.
     */
    _finalizeSidebarLayout() {
        const mount = this._resolveMountContainer();
        if (!mount || !this._aiContainerEl) {
            return;
        }

        mount.classList.add('rte-ckeditor-ai-sidebar');
        this._applySideToMount(mount);
        this._ensureHostMinHeight(mount);
        this._ensureStackSeparation();

        // VE: wrap toolbar + content in one column beside the AI panel.
        if (this._findVeEditableHost()) {
            this._ensureEditorColumn(mount, this._aiContainerEl);

            const editorColumn = mount.querySelector(':scope > .rte-ckeditor-ai-editor-column');
            if (!editorColumn) {
                return;
            }

            Array.from(mount.children).forEach((child) => {
                if (child === this._aiContainerEl || child === editorColumn) {
                    return;
                }
                if (child.classList?.contains('ck-ai-sidebar-container')) {
                    return;
                }
                editorColumn.appendChild(child);
            });

            // Keep the initializing overlay on the RTE column, not beside the sidebar.
            if (this._loaderEl?.isConnected && this._loaderEl.parentElement !== editorColumn) {
                if (getComputedStyle(editorColumn).position === 'static') {
                    editorColumn.style.position = 'relative';
                }
                editorColumn.appendChild(this._loaderEl);
            }

            this._placeSidebarBySide(mount, this._aiContainerEl, editorColumn);
            // Force row layout in case VE host styles fight the stylesheet.
            mount.style.setProperty('display', 'flex', 'important');
            mount.style.setProperty('flex-direction', 'row', 'important');
            mount.style.setProperty('flex-wrap', 'nowrap', 'important');
            mount.style.setProperty('align-items', 'stretch', 'important');
        }
    }

    /**
     * @param {HTMLElement|null} mount
     */
    _applySideToMount(mount) {
        if (!mount) {
            return;
        }
        const side = this._getConfiguredSide();
        mount.classList.toggle('rte-ckeditor-ai-side-left', side === 'left');
        mount.classList.toggle('rte-ckeditor-ai-side-right', side !== 'left');
        const wrap = mount.querySelector?.('.form-wizards-wrap');
        if (wrap) {
            wrap.classList.toggle('rte-ckeditor-ai-side-left', side === 'left');
            wrap.classList.toggle('rte-ckeditor-ai-side-right', side !== 'left');
        }
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

    /**
     * CKEditor AI requires collaboration.channelId for chat history grouping.
     */
    _ensureCollaborationChannelId() {
        const fullConfig = this.editor.config._config || (this.editor.config._config = {});
        if (!fullConfig.collaboration) {
            fullConfig.collaboration = {};
        }

        if (fullConfig.collaboration.channelId) {
            return fullConfig.collaboration.channelId;
        }

        const channelId = this._resolveEditorId() || this._generateFallbackChannelId();
        fullConfig.collaboration.channelId = channelId;

        if (!fullConfig.cloudServices) {
            fullConfig.cloudServices = {};
        }
        if (!fullConfig.cloudServices.documentId) {
            fullConfig.cloudServices.documentId = channelId;
        }

        return channelId;
    }

    _resolveEditorId() {
        if (this._editorDomId) {
            return this._editorDomId;
        }

        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.id) {
            this._editorDomId = sourceEl.id;
            return this._editorDomId;
        }

        const veHost = this._findVeEditableHost();
        if (veHost?.table && veHost.uid !== undefined && veHost.field) {
            this._editorDomId = `ve-${veHost.table}-${veHost.uid}-${veHost.field}`;
            return this._editorDomId;
        }

        this._editorDomId = this._generateFallbackChannelId();
        return this._editorDomId;
    }

    _findVeEditableHost() {
        const anchors = [this.editor.ui?.element, this.editor.sourceElement].filter(Boolean);
        for (const anchor of anchors) {
            const host = anchor.closest?.('ve-editable-rich-text');
            if (host) {
                return host;
            }
        }
        return null;
    }

    _resolveMountContainer() {
        const veHost = this._findVeEditableHost();
        if (veHost) {
            return veHost;
        }

        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.id) {
            const formItem = document.getElementById(sourceEl.id)?.closest('.form-control-wrap');
            if (formItem) {
                return formItem;
            }
        }

        const anchors = [this.editor.ui?.element, this.editor.sourceElement].filter(Boolean);
        const mountSelectors = ['.form-control-wrap', '.form-wizards-item-element'];

        for (const anchor of anchors) {
            for (const selector of mountSelectors) {
                const mount = anchor.closest(selector);
                if (mount) {
                    return mount;
                }
            }
        }

        return anchors[0]?.parentElement || null;
    }

    _generateFallbackChannelId() {
        return `ck-ai-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    }

    _setupContainer({ allowOverlayFallback = false } = {}) {
        const aiConfig = this._getAiConfig();
        let containerType = aiConfig.container.type;

        // Defaults match AIFeature select order (Sidebar / Right).
        if (!containerType) {
            containerType = 'sidebar';
            aiConfig.container.type = 'sidebar';
        }
        if (!aiConfig.container.side) {
            aiConfig.container.side = 'right';
        }

        if (containerType === 'overlay') {
            this._setupOverlayConfig();
            return;
        }

        if (containerType !== 'sidebar') {
            return;
        }

        // Already wired for this editor instance.
        if (this._aiContainerEl?.isConnected && aiConfig.container.element === this._aiContainerEl) {
            this._applySideToMount(this._resolveMountContainer());
            return;
        }

        const editorId = this._resolveEditorId();
        const containerId = `${editorId}-ai-sidebar-container`;
        let containerElement = document.getElementById(containerId);

        if (!containerElement) {
            containerElement = this._createSidebarContainer(containerId, editorId);
        } else {
            const mount = this._resolveMountContainer();
            mount?.classList.add('rte-ckeditor-ai-sidebar');
            this._applySideToMount(mount);
        }

        if (containerElement) {
            this._aiContainerEl = containerElement;
            this._overlayMode = false;
            this._applyAiContainerConfig(containerElement, 'sidebar');
            this._applySideToMount(this._resolveMountContainer());
            return;
        }

        if (!allowOverlayFallback) {
            return;
        }

        console.warn('AISidebar: Could not create sidebar container, falling back to overlay');
        aiConfig.container.type = 'overlay';
        delete aiConfig.container.element;
        this._setupOverlayConfig();
    }

    /**
     * Overlay: respect configured side (left|right).
     * Boots hidden to avoid full-page flash, then activates Toggle AI after bounds sync.
     */
    _setupOverlayConfig() {
        this._overlayMode = true;
        this._overlayPositionReady = false;
        const aiConfig = this._getAiConfig();
        const side = this._getConfiguredSide();

        aiConfig.container.type = 'overlay';
        aiConfig.container.side = side;

        // Capture default-visible intent only once (before we force boot-hidden).
        if (!this._overlayBootstrapped) {
            const configured = aiConfig.container.visibleByDefault;
            this._wantsVisibleByDefault = configured !== false
                && configured !== 0
                && configured !== '0';
            this._overlayBootstrapped = true;
        }

        // Always boot hidden so CKEditor does not paint a full-page overlay first.
        aiConfig.container.visibleByDefault = false;

        delete aiConfig.container.element;

        const mount = this._resolveMountContainer();
        if (mount) {
            mount.classList.add('rte-ckeditor-ai-overlay');
            if (getComputedStyle(mount).position === 'static') {
                mount.style.position = 'relative';
            }
            mount.style.overflow = 'visible';
        }

        try {
            const current = this.editor.config.get('ai') || {};
            this.editor.config.set('ai', {
                ...current,
                container: {
                    ...(current.container || {}),
                    type: 'overlay',
                    side,
                    visibleByDefault: false,
                },
            });
        } catch (e) {
            // Config might be read-only during initialization
        }
    }

    /**
     * @returns {'left'|'right'}
     */
    _getConfiguredSide() {
        const side = this._getAiConfig().container?.side;
        return side === 'left' ? 'left' : 'right';
    }

    _initOverlayBehavior() {
        if (this._overlayListenersBound) {
            this._syncOverlayBounds({ revealIfNeeded: true });
            return;
        }

        if (!this.editor.plugins.has('AITabs')) {
            return;
        }

        const aiTabs = this.editor.plugins.get('AITabs');
        const side = this._getConfiguredSide();

        if (typeof aiTabs.switchSide === 'function' && aiTabs.side !== side) {
            aiTabs.switchSide(side);
        }

        const overlayEl = this._getOverlayElement();
        if (overlayEl) {
            this._prepareOverlayElement(overlayEl, side);
        }

        this._boundSyncOverlay = () => this._syncOverlayBounds();
        window.addEventListener('scroll', this._boundSyncOverlay, true);
        window.addEventListener('resize', this._boundSyncOverlay);
        this._observeMountSize();

        const toggleCmd = this.editor.commands.get('toggleAi');
        if (toggleCmd) {
            this.listenTo(toggleCmd, 'change:value', (evt, name, value) => {
                if (value) {
                    this._markOverlayHostActive();
                    requestAnimationFrame(() => {
                        this._syncOverlayBounds();
                        this._revealOverlay();
                    });
                } else if (!this._activatingToggle) {
                    this._deactivateOverlayHighlight();
                    this._hideOverlayPending();
                }
            });

            this.listenTo(toggleCmd, 'execute', () => {
                requestAnimationFrame(() => {
                    if (toggleCmd.value) {
                        this._markOverlayHostActive();
                        this._syncOverlayBounds();
                        this._revealOverlay();
                    }
                });
            });
        }

        if (aiTabs.view && typeof aiTabs.view.on === 'function') {
            this.listenTo(aiTabs.view, 'change:isVisible', (evt, name, value) => {
                if (value) {
                    this._markOverlayHostActive();
                    requestAnimationFrame(() => {
                        this._syncOverlayBounds();
                        this._revealOverlay();
                    });
                } else if (!this._activatingToggle) {
                    this._deactivateOverlayHighlight();
                }
            });
        }

        // Overlay DOM may appear slightly after ready — watch briefly then reveal.
        this._watchOverlayElement();

        this._overlayListenersBound = true;

        // Double rAF: wait until layout/paint settled, then position and reveal.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this._syncOverlayBounds({ revealIfNeeded: true });
            });
        });
    }

    _watchOverlayElement() {
        if (this._overlayDomObserver || this._getOverlayElement()) {
            return;
        }

        this._overlayDomObserver = new MutationObserver(() => {
            const overlayEl = this._getOverlayElement();
            if (!overlayEl) {
                return;
            }

            this._prepareOverlayElement(overlayEl);
            this._syncOverlayBounds({ revealIfNeeded: true });
            this._overlayDomObserver?.disconnect();
            this._overlayDomObserver = null;
        });

        this._overlayDomObserver.observe(document.body, { childList: true, subtree: true });

        // Safety: stop watching after a short window.
        setTimeout(() => {
            this._overlayDomObserver?.disconnect();
            this._overlayDomObserver = null;
        }, 5000);
    }

    _prepareOverlayElement(overlayEl, side = null) {
        const resolvedSide = side || this._getConfiguredSide();
        overlayEl.classList.add('rte-ckeditor-ai-overlay-panel');
        if (!this._overlayPositionReady) {
            overlayEl.classList.add('rte-ckeditor-ai-overlay-panel--pending');
            overlayEl.classList.remove('rte-ckeditor-ai-overlay-panel--ready');
        }
        overlayEl.classList.toggle('ck-tabs_right', resolvedSide === 'right');
        overlayEl.classList.toggle('ck-tabs_left', resolvedSide === 'left');
        overlayEl.dataset.ckAiEditorId = this._resolveEditorId();
        overlayEl.dataset.ckAiSide = resolvedSide;
    }

    _hideOverlayPending() {
        const overlayEl = this._getOverlayElement();
        if (!overlayEl) {
            return;
        }
        overlayEl.classList.add('rte-ckeditor-ai-overlay-panel--pending');
        overlayEl.classList.remove('rte-ckeditor-ai-overlay-panel--ready');
    }

    _revealOverlay() {
        const overlayEl = this._getOverlayElement();
        if (!overlayEl) {
            return;
        }
        this._overlayPositionReady = true;
        overlayEl.classList.remove('rte-ckeditor-ai-overlay-panel--pending');
        overlayEl.classList.add('rte-ckeditor-ai-overlay-panel--ready');
    }

    _teardownOverlayBehavior() {
        if (this._boundSyncOverlay) {
            window.removeEventListener('scroll', this._boundSyncOverlay, true);
            window.removeEventListener('resize', this._boundSyncOverlay);
            this._boundSyncOverlay = null;
        }
        this._overlayDomObserver?.disconnect();
        this._overlayDomObserver = null;
        this._mountResizeObserver?.disconnect();
        this._mountResizeObserver = null;
        this._deactivateOverlayHighlight();
        this._overlayListenersBound = false;
        this._overlayPositionReady = false;
    }

    _getOverlayElement() {
        if (!this.editor.plugins.has('AITabs')) {
            return null;
        }

        const aiTabs = this.editor.plugins.get('AITabs');
        const el = aiTabs.view?.element || null;
        if (el?.classList?.contains('ck-ai-tabs')) {
            return el;
        }

        const editorId = this._resolveEditorId();
        return document.querySelector(`.ck-ai-tabs__overlay[data-ck-ai-editor-id="${this._cssEscape(editorId)}"]`)
            || null;
    }

    /**
     * CSS.escape with a simple fallback for older backends (still supported on TYPO3 v12).
     */
    _cssEscape(value) {
        if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
            return CSS.escape(String(value));
        }
        return String(value).replace(/([^a-zA-Z0-9_-])/g, '\\$1');
    }

    /**
     * Keep every RTE overlay in sync when layout changes (other CEs init / grow).
     */
    _observeMountSize() {
        const mount = this._resolveMountContainer();
        if (!mount || this._mountResizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }

        this._ensureHostMinHeight(mount);

        this._mountResizeObserver = new ResizeObserver(() => {
            this._syncOverlayBounds();
            for (const other of aiSidebarInstances) {
                if (other !== this && other._overlayMode) {
                    other._syncOverlayBounds();
                }
            }
        });
        this._mountResizeObserver.observe(mount);
    }

    /**
     * Grow the RTE host so a short field still has room for the AI panel.
     * Layout (document flow + stack separation) keeps the next CE below this one.
     */
    _ensureHostMinHeight(mount) {
        if (!mount) {
            return;
        }
        const minHeight = this._aiMinHeight;
        mount.classList.add('rte-ckeditor-ai-min-height');
        const current = parseFloat(mount.style.minHeight) || 0;
        if (current < minHeight) {
            mount.style.minHeight = `${minHeight}px`;
        }
    }

    /**
     * If two AI hosts still share the same vertical space, push the earlier one
     * down with margin so the next CE starts after it (no paint overlap).
     */
    _ensureStackSeparation() {
        const mounts = [];
        for (const instance of aiSidebarInstances) {
            const mount = instance._resolveMountContainer();
            if (mount && !mounts.includes(mount)) {
                mounts.push(mount);
            }
        }
        if (mounts.length < 2) {
            return;
        }

        mounts.sort((a, b) => {
            const aTop = a.getBoundingClientRect().top + window.scrollY;
            const bTop = b.getBoundingClientRect().top + window.scrollY;
            return aTop - bTop;
        });

        for (let i = 0; i < mounts.length - 1; i++) {
            const current = mounts[i];
            const next = mounts[i + 1];
            this._ensureHostMinHeight(current);
            this._ensureHostMinHeight(next);

            const curRect = current.getBoundingClientRect();
            const nextRect = next.getBoundingClientRect();
            const overlap = curRect.bottom - nextRect.top;
            if (overlap > 0) {
                const existing = parseFloat(current.style.marginBottom) || 0;
                current.style.marginBottom = `${Math.max(existing, Math.ceil(overlap) + 12)}px`;
            }
        }
    }

    /**
     * Keep the overlay inside this RTE host (absolute), so it cannot cover the next CE.
     * Hosts get a shared min-height and stack with separation when needed.
     */
    _syncOverlayBounds({ revealIfNeeded = false } = {}) {
        const mount = this._resolveMountContainer();
        const overlayEl = this._getOverlayElement();
        if (!mount || !overlayEl) {
            return false;
        }

        const side = this._getConfiguredSide();
        this._prepareOverlayElement(overlayEl, side);
        this._ensureHostMinHeight(mount);
        this._ensureStackSeparation();

        if (this.editor.plugins.has('AITabs')) {
            const aiTabs = this.editor.plugins.get('AITabs');
            if (typeof aiTabs.switchSide === 'function' && aiTabs.side !== side) {
                aiTabs.switchSide(side);
            }
        }

        // Contain inside this host — fixed-to-viewport was painting over the next CE.
        if (overlayEl.parentElement !== mount) {
            mount.appendChild(overlayEl);
        }

        const hostWidth = mount.clientWidth || mount.getBoundingClientRect().width;
        if (!hostWidth && !mount.clientHeight) {
            return false;
        }

        const panelWidth = Math.min(400, Math.max(280, Math.round(Math.min(hostWidth * 0.45, window.innerWidth * 0.4))));

        overlayEl.style.setProperty('position', 'absolute', 'important');
        overlayEl.style.setProperty('top', '0', 'important');
        overlayEl.style.setProperty('bottom', '0', 'important');
        overlayEl.style.setProperty('height', 'auto', 'important');
        overlayEl.style.setProperty('max-height', '100%', 'important');
        overlayEl.style.setProperty('min-height', '0', 'important');
        overlayEl.style.setProperty('width', `${panelWidth}px`, 'important');
        overlayEl.style.setProperty('max-width', 'min(400px, 42vw)', 'important');
        overlayEl.style.setProperty('z-index', '40', 'important');
        overlayEl.style.setProperty('overflow-y', 'auto', 'important');
        overlayEl.style.setProperty('box-sizing', 'border-box', 'important');

        overlayEl.style.setProperty('--ck-tabs-overlay-top-position', '0');
        overlayEl.style.setProperty('--ck-tabs-overlay-bottom-position', '0');
        overlayEl.style.setProperty('--ck-tabs-overlay-height', '100%');
        overlayEl.style.setProperty('--ck-ai-tabs-overlay-width', `${panelWidth}px`);

        if (side === 'left') {
            overlayEl.style.setProperty('left', '0', 'important');
            overlayEl.style.setProperty('right', 'auto', 'important');
            overlayEl.style.setProperty('--ck-tabs-overlay-left-position', '0');
            overlayEl.style.setProperty('--ck-tabs-overlay-right-position', 'auto');
        } else {
            overlayEl.style.setProperty('right', '0', 'important');
            overlayEl.style.setProperty('left', 'auto', 'important');
            overlayEl.style.setProperty('--ck-tabs-overlay-right-position', '0');
            overlayEl.style.setProperty('--ck-tabs-overlay-left-position', 'auto');
        }

        if (revealIfNeeded) {
            this._finishOverlayReveal();
        }

        return true;
    }

    /**
     * After bounds are applied, show the panel and turn Toggle AI on when that is the default.
     * Each RTE activates independently — do not close other editors (VE multi-field pages).
     */
    _finishOverlayReveal() {
        if (!this._wantsVisibleByDefault) {
            // Position is known; stay hidden until the user clicks Toggle AI.
            this._overlayPositionReady = true;
            return;
        }

        if (this._didInitialOverlayReveal) {
            this._revealOverlay();
            return;
        }

        this._didInitialOverlayReveal = true;
        this._activatingToggle = true;

        try {
            const toggleCmd = this.editor.commands.get('toggleAi');
            if (toggleCmd && !toggleCmd.value) {
                toggleCmd.execute();
            }

            // Fallback if execute did not flip value (command timing).
            if (toggleCmd && !toggleCmd.value && typeof toggleCmd.set === 'function') {
                toggleCmd.set('value', true);
            }
        } catch (e) {
            // Ignore if command cannot run yet
        }

        this._activatingToggle = false;
        this._markOverlayHostActive();
        this._revealOverlay();
    }

    /**
     * Highlight this RTE host while its AI overlay is open.
     * Does not deactivate other editors — each CE keeps its own Toggle AI state.
     */
    _markOverlayHostActive() {
        this._resolveMountContainer()?.classList.add('rte-ckeditor-ai-overlay-active');
        this._syncOverlayBounds();
        this._revealOverlay();
    }

    _deactivateOverlayHighlight() {
        this._resolveMountContainer()?.classList.remove('rte-ckeditor-ai-overlay-active');
    }

    _createSidebarContainer(containerId, editorId) {
        const mount = this._resolveMountContainer();
        const containerElement = document.createElement('div');
        containerElement.id = containerId;
        containerElement.className = 'ck-ai-sidebar-container';

        const formWizardsWrap = mount?.querySelector?.('.form-wizards-wrap') || null;
        if (formWizardsWrap) {
            const revisionViewerContainer = document.getElementById(`${editorId}revision_viewer_container`);
            if (revisionViewerContainer && revisionViewerContainer.parentElement === formWizardsWrap) {
                revisionViewerContainer.insertAdjacentElement('beforebegin', containerElement);
            } else {
                formWizardsWrap.insertAdjacentElement('afterbegin', containerElement);
            }
            mount.classList.add('rte-ckeditor-ai-sidebar');
            this._applySideToMount(mount);
            return containerElement;
        }

        if (mount) {
            mount.classList.add('rte-ckeditor-ai-sidebar');
            this._applySideToMount(mount);
            this._ensureHostMinHeight(mount);
            this._ensureEditorColumn(mount, containerElement);
            return containerElement;
        }

        return null;
    }

    _ensureEditorColumn(mount, sidebarElement) {
        let editorColumn = mount.querySelector(':scope > .rte-ckeditor-ai-editor-column');

        if (!editorColumn) {
            editorColumn = document.createElement('div');
            editorColumn.className = 'rte-ckeditor-ai-editor-column';

            const nodesToMove = Array.from(mount.childNodes).filter((node) => {
                return node !== sidebarElement
                    && !(node.nodeType === 1 && node.classList?.contains('ck-ai-sidebar-container'));
            });

            nodesToMove.forEach((node) => editorColumn.appendChild(node));
            mount.appendChild(editorColumn);
        }

        // Place children by configured side (DOM order + flex-row): left → AI|RTE, right → RTE|AI.
        this._placeSidebarBySide(mount, sidebarElement, editorColumn);
    }

    /**
     * Put AI panel on the configured side of the editor column (VE + reliable without row-reverse).
     */
    _placeSidebarBySide(mount, sidebarElement, editorColumn) {
        if (!mount || !sidebarElement || !editorColumn) {
            return;
        }

        const side = this._getConfiguredSide();

        if (side === 'left') {
            // [AI, RTE]
            if (sidebarElement.parentElement !== mount || sidebarElement.nextElementSibling !== editorColumn) {
                mount.insertBefore(sidebarElement, editorColumn);
            }
        } else {
            // [RTE, AI]
            if (sidebarElement.parentElement !== mount || editorColumn.nextElementSibling !== sidebarElement) {
                mount.insertBefore(sidebarElement, editorColumn.nextSibling);
            }
        }
    }

    _applyAiContainerConfig(containerElement, type = 'sidebar') {
        const aiConfig = this._getAiConfig();
        aiConfig.container.element = containerElement;
        aiConfig.container.type = type;

        if (!aiConfig.container.side) {
            aiConfig.container.side = 'right';
        }

        try {
            const current = this.editor.config.get('ai') || {};
            this.editor.config.set('ai', {
                ...current,
                container: {
                    ...(current.container || {}),
                    type,
                    side: aiConfig.container.side,
                    element: containerElement,
                },
            });
        } catch (e) {
            // Config might be read-only during initialization
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

        let editorContainer = sourceEl.closest('.form-wizards-item-element')
            || this._findVeEditableHost();

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
        const parent = this._resolveMountContainer();
        if (!parent) {
            return null;
        }

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

        // Cover the RTE only (not a third flex column beside the AI sidebar).
        const editorColumn = mount.querySelector?.(':scope > .rte-ckeditor-ai-editor-column');
        const formEditor = mount.querySelector?.('.form-wizards-item-element');
        const host = editorColumn || formEditor || mount;
        if (getComputedStyle(host).position === 'static') {
            host.style.position = 'relative';
        }
        host.appendChild(el);
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
