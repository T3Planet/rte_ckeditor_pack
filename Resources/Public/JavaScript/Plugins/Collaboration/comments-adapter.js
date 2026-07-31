import * as Core from "@ckeditor/ckeditor5-core";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import CheckForm from "@t3planet/RteCkeditorPack/common.js";

const cms = new CheckForm();
window.commentSaved = false;

/**
 * CommentsAdapter - Non-RTC comments persistence (TYPO3 storage).
 * Real-time Collaboration uses Cloud Services instead; this adapter must still
 * register whenever Comments is enabled without RTC (FormEngine + Visual Editor).
 */
export class CommentsAdapter extends Core.Plugin {
    static DEFAULT_MARKER = '@';
    static COMMENT_PLUGINS = ['Bold', 'Italic', 'Underline', 'Mention'];
    static COMMENT_TOOLBAR = {
        items: ['bold', 'italic', 'underline', '|', 'mention'],
        shouldNotGroupWhenFull: false
    };

    constructor(editor) {
        super();
        this.editor = editor;
        this._configureCommentsPlugins();
    }

    static get pluginName() {
        return 'CommentsAdapter';
    }


    /**
     * Configure comment editor with toolbar and plugins
     */
    _configureCommentsPlugins() {
        const commentsConfig = this.editor.config._config.comments?.editorConfig;
        if (!commentsConfig) return;

        if (!commentsConfig.toolbar) {
            commentsConfig.toolbar = { ...CommentsAdapter.COMMENT_TOOLBAR };
        }

        if (!Array.isArray(commentsConfig.extraPlugins)) {
            commentsConfig.extraPlugins = [];
        }

        const addPlugins = () => {
            try {
                const availablePlugins = Array.from(this.editor.plugins._availablePlugins.values());
                const targetPlugins = CommentsAdapter.COMMENT_PLUGINS;
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
        const rteId = this._resolveRteId();
        const commentsRepositoryPlugin = this.editor.plugins.get('CommentsRepository');

        this._configureCommentMentionFeeds();
        // Adapter must always be registered — do not gate on FormEngine save button.
        this._attachCommentsRepositoryAdapter(commentsRepositoryPlugin, rteId);
        this._setupCommentSaveHandler(rteId);
        this._setupVisualEditorPersist(rteId);
    }

    /**
     * Shared FormEngine + Visual Editor storage key: data[table][uid][field].
     * Prefer PHP-injected collaboration.rteId so both contexts hit the same DB rows.
     */
    _resolveRteId() {
        const configured = this.editor.config.get('collaboration')?.rteId;
        if (typeof configured === 'string' && configured.startsWith('data[')) {
            return configured;
        }

        const source = this.editor.sourceElement;
        const formName = source?.getAttribute?.('name') || source?.name;
        if (typeof formName === 'string' && formName.startsWith('data[')) {
            return formName;
        }

        const veHost = source?.closest?.('ve-editable-rich-text')
            || this.editor.ui?.element?.closest?.('ve-editable-rich-text')
            || this.editor.ui?.view?.element?.closest?.('ve-editable-rich-text')
            || document.querySelector?.('ve-editable-rich-text:focus-within');
        if (veHost) {
            const table = veHost.table || veHost.getAttribute?.('table');
            const uid = veHost.uid ?? veHost.getAttribute?.('uid');
            const field = veHost.field || veHost.getAttribute?.('field');
            if (table != null && uid != null && field) {
                return `data[${table}][${uid}][${field}]`;
            }
        }

        // Do not fall back to channelId (ckdoc-…) — that splits VE/FormEngine storage.
        return formName || '';
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

                const marker = feed.marker || CommentsAdapter.DEFAULT_MARKER;
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
                feeds: [{ marker: CommentsAdapter.DEFAULT_MARKER, feed: [] }]
            };
        }
    }

    /**
     * Ensure comment authors exist in Users (needed when AppData is incomplete, e.g. Visual Editor).
     */
    _ensureUsersFromThread(thread) {
        if (!this.editor.plugins.has('Users') || !thread?.comments?.length) {
            return;
        }

        const usersPlugin = this.editor.plugins.get('Users');
        thread.comments.forEach((comment) => {
            const authorId = comment.authorId != null ? String(comment.authorId) : '';
            if (!authorId) {
                return;
            }
            try {
                if (!usersPlugin.getUser(authorId)) {
                    usersPlugin.addUser({
                        id: authorId,
                        name: comment.authorName || ('User ' + authorId),
                    });
                }
            } catch (e) {
                try {
                    usersPlugin.addUser({
                        id: authorId,
                        name: comment.authorName || ('User ' + authorId),
                    });
                } catch (ignore) {
                    // User already defined
                }
            }
        });
    }

    /**
     * Persist a single comment row via FE /comments middleware.
     */
    async _persistComment(commentId, threadId, content, rteId) {
        const resolvedRteId = this._resolveRteId() || rteId;
        if (!resolvedRteId || !String(resolvedRteId).startsWith('data[')) {
            throw new Error('Cannot save comment: missing content field id (rteId)');
        }

        const formData = new FormData();
        formData.append('id', commentId);
        formData.append('thread_id', threadId);
        formData.append('content', content ?? '');
        formData.append('rteId', resolvedRteId);

        const response = await fetch('/comments', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        if (!response.ok) {
            const message = await response.text().catch(() => response.statusText);
            throw new Error(message || 'Failed to save comment');
        }
        const responseData = await response.json();
        if (responseData?.error) {
            throw new Error(responseData.message || 'Failed to save comment');
        }

        return {
            createdAt: new Date((responseData.created_at || Math.floor(Date.now() / 1000)) * 1000)
        };
    }

    /**
     * Wire CKEditor CommentsRepository to TYPO3 backend storage.
     * First comment in a new thread calls addCommentThread (not only addComment).
     */
    _attachCommentsRepositoryAdapter(commentsRepositoryPlugin, rteId) {
        commentsRepositoryPlugin.adapter = {
            addComment: async (data) => {
                return this._persistComment(data.commentId, data.threadId, data.content, rteId);
            },

            // Required for the first comment of a new thread (VE + FormEngine Non-RTC).
            addCommentThread: async (data) => {
                const threadId = data.threadId;
                const comments = Array.isArray(data.comments) ? data.comments : [];
                const saved = [];

                for (const comment of comments) {
                    if (!comment?.commentId) {
                        continue;
                    }
                    const result = await this._persistComment(
                        comment.commentId,
                        threadId,
                        comment.content,
                        rteId
                    );
                    saved.push({
                        commentId: comment.commentId,
                        createdAt: result.createdAt,
                    });
                }

                // Empty thread open/submit with no comments yet — still acknowledge thread id.
                if (saved.length === 0 && threadId) {
                    return { threadId, comments: [] };
                }

                return {
                    threadId,
                    comments: saved,
                };
            },

            getCommentThread: async ({ threadId }) => {
                const response = await fetch('/comments/thread/?threadId=' + encodeURIComponent(threadId), {
                    credentials: 'same-origin',
                });
                const data = await response.json();
                const thread = { threadId, comments: [], isFromAdapter: true };

                if (Array.isArray(data)) {
                    thread.comments = data.map(c => ({
                        commentId: c.id,
                        authorId: String(c.user_id),
                        content: c.content,
                        createdAt: new Date(c.created_at * 1000)
                    }));

                    if (data.length > 0 && data[0].resolved_at) {
                        thread.resolvedAt = new Date(data[0].resolved_at * 1000);
                        thread.resolvedBy = data[0].resolved_by != null
                            ? String(data[0].resolved_by)
                            : undefined;
                        thread.isResolved = true;
                    }
                }

                this._ensureUsersFromThread(thread);
                return thread;
            },

            updateComment: (data) => {
                const formData = new FormData();
                formData.append('commentId', data.commentId);
                formData.append('content', data.content);
                formData.append('threadId', data.threadId);

                return fetch('/comments/update/', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
            },

            updateCommentThread: async () => {
                // Attributes/context only — comment rows already stored.
                return;
            },

            resolveCommentThread: async (data) => {
                const resolvedAt = new Date();
                const me = this.editor.plugins.has('Users')
                    ? this.editor.plugins.get('Users').me?.id
                    : null;
                archiveResolvedComments([{
                    threadId: data.threadId,
                    resolvedAt,
                    resolvedBy: me,
                    comments: (data.comments || []).map(c => c.commentId || c),
                }], this._resolveRteId() || rteId);

                return {
                    resolvedAt,
                    resolvedBy: me != null ? String(me) : undefined,
                };
            },

            reopenCommentThread: async (data) => {
                // Soft reopen: clear resolved flags via archive endpoint with empty/unresolved
                // is handled on FormEngine bulk save; adapter reopen is best-effort.
                return;
            },

            removeComment: async (data) => {
                await fetch(
                    '/comments/delete/?comment_id=' + encodeURIComponent(data.commentId)
                    + '&thread_id=' + encodeURIComponent(data.threadId),
                    { credentials: 'same-origin' }
                ).then(response => response.json());
            },

            removeCommentThread: async (data) => {
                const threadId = data.threadId;
                if (!threadId) {
                    return;
                }
                // Delete known comments if provided; otherwise fetch then delete.
                let commentIds = Array.isArray(data.comments)
                    ? data.comments.map(c => c.commentId || c).filter(Boolean)
                    : [];
                if (commentIds.length === 0) {
                    const response = await fetch(
                        '/comments/thread/?threadId=' + encodeURIComponent(threadId),
                        { credentials: 'same-origin' }
                    );
                    const rows = await response.json();
                    if (Array.isArray(rows)) {
                        commentIds = rows.map(r => r.id).filter(Boolean);
                    }
                }
                for (const commentId of commentIds) {
                    await fetch(
                        '/comments/delete/?comment_id=' + encodeURIComponent(commentId)
                        + '&thread_id=' + encodeURIComponent(threadId),
                        { credentials: 'same-origin' }
                    );
                }
            },
        };
    }

    /**
     * Persist threads on FormEngine save (TYPO3 12–14 button variants).
     * Visual Editor / missing save button: per-comment adapter methods still persist.
     */
    _setupCommentSaveHandler(rteId) {
        const editor = this.editor;
        const saveBtn = this._resolveSaveButton();
        if (!saveBtn || saveBtn.dataset.rteCkeditorCommentsBound === '1') {
            return;
        }
        saveBtn.dataset.rteCkeditorCommentsBound = '1';

        saveBtn.addEventListener('click', evt => {
            const comments = editor.plugins.get('CommentsRepository');
            const allThreads = comments.getCommentThreads({
                skipNotAttached: true,
                skipEmpty: true,
                toJSON: true
            });

            const unresolvedThreads = [];
            const resolvedThreads = [];

            allThreads.forEach(thread => {
                if (thread.resolvedAt || thread.resolvedBy) {
                    resolvedThreads.push(thread);
                } else {
                    unresolvedThreads.push(thread);
                }
            });

            if (resolvedThreads.length > 0) {
                archiveResolvedComments(resolvedThreads, rteId);
            }

            saveComments(
                this._resolveRteId() || rteId,
                JSON.stringify(allThreads),
                evt
            );
        });
    }

    /**
     * Visual Editor: per-comment adapter already persists immediately.
     * On destroy, archive any resolved threads (no blur spam).
     */
    _setupVisualEditorPersist(rteId) {
        const veHost = this.editor.sourceElement?.closest?.('ve-editable-rich-text')
            || this.editor.ui?.element?.closest?.('ve-editable-rich-text');
        if (!veHost) {
            return;
        }

        this.listenTo(this.editor, 'destroy', () => {
            const id = this._resolveRteId() || rteId;
            if (!id || !String(id).startsWith('data[')) {
                return;
            }
            if (!this.editor.plugins.has('CommentsRepository')) {
                return;
            }
            const comments = this.editor.plugins.get('CommentsRepository');
            const allThreads = comments.getCommentThreads({
                skipNotAttached: true,
                skipEmpty: true,
                toJSON: true
            });
            if (!Array.isArray(allThreads) || allThreads.length === 0) {
                return;
            }
            const resolvedThreads = allThreads.filter(
                thread => thread.resolvedAt || thread.resolvedBy
            );
            if (resolvedThreads.length > 0) {
                archiveResolvedComments(resolvedThreads, id);
            }
        });
    }

    /**
     * FormEngine save buttons across TYPO3 12–14.
     */
    _resolveSaveButton() {
        return document.querySelector("button[name='_savedok']")
            || document.querySelector("button[name='_save']")
            || document.querySelector("button[name='data[_save]']")
            || document.querySelector("button[data-test-save]")
            || document.querySelector(".t3js-editform-submitButton[name='_savedok']")
            || document.querySelector(".t3js-editform-submitButton[name='_save']")
            || document.querySelector('button[form="EditDocumentController"][name="_savedok"]')
            || null;
    }
}

/**
 * Archive resolved comments (mark them as resolved in database)
 */
function archiveResolvedComments(resolvedThreads, rteId) {
    const resolvedData = resolvedThreads.map(thread => ({
        threadId: thread.threadId,
        resolvedAt: thread.resolvedAt ? new Date(thread.resolvedAt).getTime() / 1000 : Math.floor(Date.now() / 1000),
        resolvedBy: thread.resolvedBy || null,
        comments: thread.comments.map(c => c.commentId)
    }));

    const archiveUrl = TYPO3?.settings?.ajaxUrls?.archive_comments || '/comments/archive/';
    // Prefer fetch on Visual Editor FE (AjaxRequest may lack BE ajaxUrls).
    if (!TYPO3?.settings?.ajaxUrls?.archive_comments) {
        const formData = new FormData();
        formData.append('rteId', rteId);
        formData.append('resolvedData', JSON.stringify(resolvedData));
        fetch(archiveUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        }).catch(() => {
            // Archive failed silently - comments remain saved via adapter.
        });
        return;
    }

    new AjaxRequest(archiveUrl)
        .post({
            rteId: rteId,
            resolvedData: JSON.stringify(resolvedData)
        })
        .then(async function (response) {
            await response.resolve();
        })
        .catch(() => {
            // Archive failed silently - comments will still be saved
        });
}

/**
 * Save comments to server, then continue FormEngine save.
 * Must await AJAX — submitting the form immediately aborts the request (NetworkError).
 */
function saveComments(rteId, commentsData, evt) {
    if (evt) {
        evt.preventDefault();
        evt.stopPropagation();
    }

    const finishFormSave = () => {
        window.commentSaved = true;
        cms.attemptFormSubmission();
    };

    if (!TYPO3?.settings?.ajaxUrls?.save_comments) {
        Notification.error(
            TYPO3.lang['comments.save.error.title'] || 'Comment Error',
            'save_comments AJAX route is not available'
        );
        finishFormSave();
        return;
    }

    new AjaxRequest(TYPO3.settings.ajaxUrls['save_comments'])
        .post({
            rteId: rteId,
            commentsData: commentsData
        })
        .then(async function (response) {
            const resolved = await response.resolve();
            const responseBody = typeof resolved === 'string' ? JSON.parse(resolved) : resolved;
            if (responseBody.status === 'OK') {
                const title = TYPO3.lang['comments.save.success.title'] || 'Comments';
                const message = TYPO3.lang['comments.save.success.message'] || 'Comments successfully saved and archived';
                Notification.success(title, message);
            }
        })
        .catch((error) => {
            const title = TYPO3.lang['comments.save.error.title'] || 'Comment Error';
            const message = TYPO3.lang['comments.save.error.message'] || 'Failed to save comments';
            Notification.error(title, error.message || message);
        })
        .finally(finishFormSave);
}

export default CommentsAdapter;
