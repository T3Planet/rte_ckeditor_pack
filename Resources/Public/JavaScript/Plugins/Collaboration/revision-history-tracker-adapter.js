import * as Core from "@ckeditor/ckeditor5-core";
import CollaborationStorage from "@t3planet/RteCkeditorPack/collaboration-storage.js";
import CheckForm from "@t3planet/RteCkeditorPack/common.js";

window.revisionSaved = false;

class RevisionHistoryTrackerAdapter extends Core.Plugin {
    constructor(editor, editorId) {
        super();
        this.editor = editor;
        this.editorId = this.editor.sourceElement.id;
        this.storage = new CollaborationStorage(this.editor);
        this.cms = new CheckForm();
    }

    static get pluginName() {
        return 'RevisionHistoryTrackerAdapter'
    }

    static get requires() {
        return ['RevisionHistory', 'RevisionTracker']
    }

    afterInit() {
        if (this.storage.processRevisionDisable()) {
            return;
        }
        this._initWrappers();
        // Initialize revision history settings.
        if (typeof TYPO3.settings.ckeditor5Premium == "undefined") {
            return;
        }
        const revisionHistoryPlugin = this.editor.plugins.get('RevisionHistory');
        const revisionTrackerPlugin = this.editor.plugins.get('RevisionTracker');
        const revisionHistoryElement = document.querySelector('[data-ckeditor5-premium-element-id="' + this.editorId + '"]');
        const revisions = JSON.parse(revisionHistoryElement?.value);
        
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
        saveBtn.addEventListener('click', evt => {
            this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, true, evt)
        });


    }

    _initWrappers() {
        if (this.editor.plugins.has('RevisionHistory')) {
            let revisionHistoryContainer = document.querySelector('#' + this.editorId).closest('.form-wizards-item-element');
            if (revisionHistoryContainer) {
                revisionHistoryContainer.insertAdjacentHTML('afterend', `
                 <div id="` + this.editorId + `revision_viewer_container" class="revision_viewer_container">
                    <div id="` + this.editorId + `revision_viewer_editor" class="revision_viewer_editor"></div>
                    <div id="` + this.editorId + `revision_viewer_sidebar" class="revision_viewer_sidebar"></div>
                </div>
			`);
            }

            this.editor.config._config.revisionHistory.editorContainer = document.querySelector('#' + this.editorId).closest('.form-wizards-item-element');
            this.editor.config._config.revisionHistory.viewerContainer = document.querySelector('#' + this.editorId + 'revision_viewer_container');
            this.editor.config._config.revisionHistory.viewerEditorElement = document.querySelector('#' + this.editorId + 'revision_viewer_editor');
            this.editor.config._config.revisionHistory.viewerSidebarContainer = document.querySelector('#' + this.editorId + 'revision_viewer_sidebar');
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
        console.log(window.revisionSaved);
        if (!window.revisionSaved) {
            evt.preventDefault();
            window.revisionSaved = true;
            console.log(window.revisionSaved, "ta")
            this.cms.attemptFormSubmission();
        }
    }
}
export default RevisionHistoryTrackerAdapter;
