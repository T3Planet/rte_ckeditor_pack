class CollaborationStorage {
    constructor( editor ) {
        this.editor = editor;
        this.elementId = this.editor.sourceElement.id;
    }

    /**
     * Checks if collaboration is set to be disabled and blocks the specified command (button).
     *
     * @param commandName
     *   Command name (related to a button)
     *
     * @returns {boolean}
     *   TRUE if command was blocked, FALSE otherwise.
     */
    processCollaborationCommandDisable(commandName) {
        if (!this.isCollaborationDisabled()) {
            return false;
        }

        const command = this.editor.commands._commands.get( commandName );

        if (typeof command == 'undefined') {
            return true;
        }

        command.forceDisabled( 'premium-features-module' );

        return true;
    }

    /**
     * Checks if collaboration is set to be disabled and blocks the revision history feature (button).
     *
     * @returns {boolean}
     *   TRUE if feature was blocked, FALSE otherwise.
     */
    processRevisionDisable() {
        if (!this.isCollaborationDisabled()) {
            return false;
        }

        if (this.editor.plugins.has( 'RevisionTracker' )) {

            this.editor.plugins.get( 'RevisionTracker' ).isEnabled = false;
        }

        return true;
    }

    /**
     * Checks if collaboration is set to be disabled.
     *
     * @returns {boolean}
     *   TRUE if conditions for blocking collaboration are met, FALSE otherwise.
     */
    isCollaborationDisabled() {
        
        return typeof TYPO3.settings.ckeditor5Premium != 'undefined' &&
          typeof TYPO3.settings.ckeditor5Premium.disableCollaboration != "undefined" &&
          TYPO3.settings.ckeditor5Premium.disableCollaboration === true;

        return true;
    }

    /**
     * Returns parent element of an editors' element matching passed ID.
     *
     * @param elementId
     *   HTML ID of an editor.
     *
     * @returns {HTMLElement|null}
     */
    getEditorParentContainer(elementId) {
        let editorElement = document.getElementById(elementId);

        while (editorElement && typeof editorElement !== "undefined"
        && typeof editorElement.classList !== "undefined" &&
        !editorElement.classList.contains('ck-editor-container')) {

            editorElement = editorElement.parentElement;
        }

        if (!editorElement || typeof editorElement === "undefined") {
            return null;
        }

        // We get parentElement one more time to be able to search for all related
        // editor elements (like sidebar, presence list etc)
        return editorElement.parentElement;
    }
}

export default CollaborationStorage;
