import * as Core from "@ckeditor/ckeditor5-core";

window.commentSaved = typeof window.commentSaved !== 'undefined' ? window.commentSaved : true;
window.revisionSaved = typeof window.revisionSaved !== 'undefined' ? window.revisionSaved : true;

class UserAdapter extends Core.Plugin {
    constructor( editor ) {
        super();
        this.editor = editor;
    }
    static get pluginName() {
        return 'UserAdapter'
    }
    init() {
        const toolbarItems = Array.from( this.editor.ui.componentFactory.names() );
        const uniqueItems = new Set();
        toolbarItems.forEach(item => {
            // Split item at ':' and take the first part for the base item
            const baseItem = item.split(':')[0];
            // Add the base item to the Set (will automatically handle duplicates)
            uniqueItems.add(baseItem);
        });

        if (!this.editor.plugins.has('Users') ) {
            return;
        }
        const usersPlugin = this.editor.plugins.get( 'Users' );
        const users = TYPO3.settings.AppData.appData.users;
        for (const user in users) {
            usersPlugin.addUser(users[user]);
        }
        // Set the current user.
        usersPlugin.defineMe( TYPO3.settings.AppData.appData.userId );
    }
}

export default UserAdapter;
