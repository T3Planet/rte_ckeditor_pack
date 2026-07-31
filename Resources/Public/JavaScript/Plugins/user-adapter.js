import * as Core from "@ckeditor/ckeditor5-core";

window.commentSaved = typeof window.commentSaved !== 'undefined' ? window.commentSaved : true;
window.revisionSaved = typeof window.revisionSaved !== 'undefined' ? window.revisionSaved : true;

const REALTIME_COLLABORATION_EXPORTS = [
    'RealTimeCollaborativeEditing',
    'RealTimeCollaborativeComments',
    'RealTimeCollaborativeRevisionHistory',
    'RealTimeCollaborativeTrackChanges',
];

class UserAdapter extends Core.Plugin {
    constructor(editor) {
        super();
        this.editor = editor;
    }

    static get pluginName() {
        return 'UserAdapter';
    }

    static get requires() {
        return ['Users'];
    }

    _shouldDeferToCloudCollaboration() {
        // Only defer when Real-time Collaboration plugins are actually loaded.
        // cloudServices.tokenUrl is also used by other premium features (e.g. Import
        // from Word) and must not skip local Users.me for Non-RTC Comments.
        return this._isRealtimeCollaborationEnabled();
    }

    init() {
        if (!this.editor.plugins.has('Users')) {
            return;
        }

        if (this._shouldDeferToCloudCollaboration()) {
            return;
        }

        const usersPlugin = this.editor.plugins.get('Users');
        const appData = globalThis?.TYPO3?.settings?.AppData?.appData;
        const collaboration = this.editor.config.get('collaboration') || {};

        if (Array.isArray(appData?.users) && appData.users.length > 0) {
            appData.users.forEach((user) => {
                if (user) {
                    usersPlugin.addUser(user);
                }
            });
            const meId = appData.userId || appData.users[0]?.id;
            if (meId) {
                usersPlugin.defineMe(String(meId));
            }
            return;
        }

        const collaborationUsers = Array.isArray(collaboration.users) ? collaboration.users : [];
        collaborationUsers.forEach((user) => {
            if (user) {
                usersPlugin.addUser(user);
            }
        });

        if (collaboration.userId) {
            usersPlugin.defineMe(String(collaboration.userId));
            return;
        }

        if (collaborationUsers.length > 0 && collaborationUsers[0]?.id) {
            usersPlugin.defineMe(String(collaborationUsers[0].id));
            return;
        }

        if (collaboration.userName) {
            const fallbackId = 'typo3-user';
            usersPlugin.addUser({
                id: fallbackId,
                name: collaboration.userName,
            });
            usersPlugin.defineMe(fallbackId);
        }
    }

    _isRealtimeCollaborationEnabled() {
        const importModules = this.editor.config.get('importModules') || [];

        return importModules.some((importModule) => {
            if (typeof importModule === 'string') {
                return importModule.includes('ckeditor5-real-time-collaboration');
            }

            const modulePath = importModule?.module ?? '';
            if (typeof modulePath === 'string' && modulePath.includes('ckeditor5-real-time-collaboration')) {
                return true;
            }

            const exports = importModule?.exports ?? [];
            const exportList = Array.isArray(exports)
                ? exports
                : String(exports).split(',').map((entry) => entry.trim());

            return exportList.some((exportName) => REALTIME_COLLABORATION_EXPORTS.includes(exportName));
        });
    }
}

export default UserAdapter;
