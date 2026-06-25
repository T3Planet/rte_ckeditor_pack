import * as Core from "@ckeditor/ckeditor5-core";

/**
 * Makes Users plugin idempotent for cloud collaboration (CloudServices + RTC both call defineMe/addUser).
 */
class UsersCollaborationGuard extends Core.Plugin {
    static get pluginName() {
        return 'UsersCollaborationGuard';
    }

    static get requires() {
        return ['Users'];
    }

    init() {
        const users = this.editor.plugins.get('Users');
        if (users._rteCkeditorPackUsersPatched) {
            return;
        }

        const originalDefineMe = users.defineMe.bind(users);
        users.defineMe = (userId) => {
            if (users.me) {
                return;
            }
            originalDefineMe(userId);
        };

        const originalAddUser = users.addUser.bind(users);
        users.addUser = (user) => {
            const userId = user?.id;
            if (userId && typeof users.getUser === 'function') {
                const existingUser = users.getUser(userId);
                if (existingUser) {
                    return existingUser;
                }
            }

            return originalAddUser(user);
        };

        users._rteCkeditorPackUsersPatched = true;
    }
}

export default UsersCollaborationGuard;
