import * as Core from "@ckeditor/ckeditor5-core";
import CollaborationStorage from "@t3planet/RteCkeditorPack/collaboration-storage.js";
import CheckForm from "@t3planet/RteCkeditorPack/common.js";

window.revisionSaved = false;

class RevisionHistoryTrackerAdapter extends Core.Plugin {
    constructor(editor, editorId) {
        super();
        this.editor = editor;
        this._veHost = editor.sourceElement?.closest('ve-editable-rich-text') || null;
        this.editorId = this._resolveEditorId();
        this.storage = new CollaborationStorage(this.editor);
        this.cms = new CheckForm();
    }

    _resolveEditorId() {
        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.id) {
            return sourceEl.id;
        }

        const veHost = this._veHost || this._findVeEditableHost();
        if (veHost?.table && veHost.uid !== undefined && veHost.field) {
            return `ve-${veHost.table}-${veHost.uid}-${veHost.field}`;
        }

        return `ck-revision-${Math.random().toString(36).slice(2, 11)}`;
    }

    _findVeEditableHost() {
        if (this._veHost?.isConnected) {
            return this._veHost;
        }

        const anchor = this.editor.ui?.element || this.editor.sourceElement;
        const host = anchor?.closest?.('ve-editable-rich-text');
        if (host) {
            this._veHost = host;
            return host;
        }

        const match = typeof this.editorId === 'string' && this.editorId.match(/^ve-(.+)-(\d+)-(.+)$/);
        if (match) {
            const [, table, uid, field] = match;
            const found = document.querySelector(
                `ve-editable-rich-text[table="${CSS.escape(table)}"][uid="${uid}"][field="${CSS.escape(field)}"]`
            );
            if (found) {
                this._veHost = found;
                return found;
            }
        }

        return null;
    }

    _resolveEditorContainer() {
        const veHost = this._findVeEditableHost();
        if (veHost) {
            return veHost;
        }

        const mountSelectors = [
            '.form-wizards-item-element',
            've-editable-rich-text',
            '.form-control-wrap',
        ];

        const anchors = [
            this.editor.ui?.element,
            this.editor.sourceElement,
        ].filter(Boolean);

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

    _applyRevisionHistoryContainers() {
        if (!this.editor.plugins.has('RevisionHistory')) {
            return;
        }

        const editorContainer = this._resolveEditorContainer();
        if (!editorContainer) {
            return;
        }

        const viewerContainerId = `${this.editorId}revision_viewer_container`;
        if (!document.getElementById(viewerContainerId)) {
            editorContainer.insertAdjacentHTML('afterend', `
                <div id="${viewerContainerId}" class="revision_viewer_container">
                    <div class="revision_viewer_editor-container">
                        <div id="${this.editorId}revision_viewer_editor" class="revision_viewer_editor"></div>
                        <div id="${this.editorId}revision_viewer_sidebar" class="revision_viewer_sidebar sidebar-container"></div>
                    </div>
                </div>
            `);
        }

        const containers = {
            editorContainer,
            viewerContainer: document.getElementById(viewerContainerId),
            viewerEditorElement: document.getElementById(`${this.editorId}revision_viewer_editor`),
            viewerSidebarContainer: document.getElementById(`${this.editorId}revision_viewer_sidebar`),
        };

        Object.entries(containers).forEach(([key, value]) => {
            if (value) {
                this.editor.config.set(`revisionHistory.${key}`, value);
            }
        });
    }

    static get pluginName() {
        return 'RevisionHistoryTrackerAdapter'
    }

    static get requires() {
        return ['RevisionHistory', 'RevisionTracker']
    }

    init() {
        this.editor.once('ready', () => {
            this._applyRevisionHistoryContainers();
        });
    }

    afterInit() {
        this._applyRevisionHistoryContainers();

        if (this.storage.processRevisionDisable()) {
            return;
        }
        // Initialize revision history settings.
        if (typeof TYPO3.settings.ckeditor5Premium == "undefined") {
            return;
        }
        const revisionHistoryPlugin = this.editor.plugins.get('RevisionHistory');
        const revisionTrackerPlugin = this.editor.plugins.get('RevisionTracker');
        const revisionHistoryElement = document.querySelector('[data-ckeditor5-premium-element-id="' + this.editorId + '"]');
        const revisions = revisionHistoryElement?.value ? JSON.parse(revisionHistoryElement.value) : [];
        
        let create_new_draft = false;
        if (revisions) {
            for (const revision of revisions) {
                if (revision['createdAt']) {
                    revision['createdAt'] = new Date(revision['createdAt'] * 1000)
                }
                if (revision['attributes']['new_draft_req']) {
                    create_new_draft = true;
                    delete revision['attributes']['new_draft_req'];
                }
                revisionHistoryPlugin.addRevisionData(revision);
            }
        }
        if (create_new_draft) {
            setTimeout(() => {
                this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, true,);
            }, 10);
        }

        const saveBtn = document.querySelector("button[name='_savedok']");
        if (saveBtn) {
            saveBtn.addEventListener('click', evt => {
                this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, true, evt)
            });
        }


    }

    async updateStorage(plugin, tracker, storageElement, addRevisionOnSubmit, evt) {
        await tracker.update();
        storageElement.value = JSON.stringify(plugin.getRevisions({
            toJSON: true
        }));
        if (addRevisionOnSubmit) {
            this._saveRevisions(evt, storageElement.value);
        }
    }

    async _saveRevisions(evt, revisionsData) {
        const formData = new FormData();
        const documentData = this.editor.getData();
        const contentId = this.editor.sourceElement.id;
        formData.append('revisionsData', revisionsData);
        formData.append('contentId', contentId);
        await fetch('/ckeditor-premium/revisions/update/', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .catch((error) => {
                console.log(error, "Error");
            });
        if (!window.revisionSaved) {
            evt.preventDefault();
            window.revisionSaved = true;
            this.cms.attemptFormSubmission();
        }
    }
}
export default RevisionHistoryTrackerAdapter;
