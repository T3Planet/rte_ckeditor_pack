import * as Core from "@ckeditor/ckeditor5-core";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import CheckForm from "@t3planet/RteCkeditorPack/common.js";

const cms = new CheckForm();
window.commentSaved = false;

/**
 * CommentsAdapter - Handles comment editor configuration and comment operations
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

        // Ensure toolbar exists
        if (!commentsConfig.toolbar) {
            commentsConfig.toolbar = { ...CommentsAdapter.COMMENT_TOOLBAR };
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
        const rteId = this.editor.sourceElement.name;
        const commentsRepositoryPlugin = this.editor.plugins.get('CommentsRepository');
        const editor = this.editor;

        this._configureCommentMentionFeeds();
        this._setupCommentSaveHandler(editor, rteId, commentsRepositoryPlugin);
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
     * Setup comment save handler and adapter
     */
    _setupCommentSaveHandler(editor, rteId, commentsRepositoryPlugin) {
        const saveBtn = document.querySelector("button[name='_savedok']");
        if (!saveBtn) return;

        saveBtn.addEventListener('click', evt => {
            const comments = editor.plugins.get('CommentsRepository');
            const allThreads = comments.getCommentThreads({
                skipNotAttached: true,
                skipEmpty: true,
                toJSON: true
            });

            // Filter out resolved threads - only save unresolved comments
            const unresolvedThreads = [];
            const resolvedThreads = [];
            
            allThreads.forEach(thread => {
                // A thread is resolved if it has a 'resolvedAt' property
                if (thread.resolvedAt || thread.resolvedBy) {
                    resolvedThreads.push(thread);
                } else {
                    unresolvedThreads.push(thread);
                }
            });

            // Archive resolved comments (mark them as resolved in database)
            if (resolvedThreads.length > 0) {
                archiveResolvedComments(resolvedThreads, rteId);
            }

            // Save all comments (both resolved and unresolved) for archive access
            saveComments(
                editor.sourceElement.getAttribute('name'),
                JSON.stringify(allThreads),  // Save ALL threads
                evt
            );
        });

        commentsRepositoryPlugin.adapter = {
            async addComment(data) {
                const formData = new FormData();
                formData.append('id', data.commentId);
                formData.append('thread_id', data.threadId);
                formData.append('content', data.content);
                formData.append('rteId', rteId);

                const response = await fetch('/comments', {
                    method: 'POST',
                    body: formData
                });
                const responseData = await response.json();
                return {
                    createdAt: new Date(responseData.created_at * 1000)
                };
            },
            async getCommentThread({threadId}) {
                const response = await fetch('/comments/thread/?threadId=' + threadId);
                const data = await response.json();
                const thread = { threadId };
                thread.comments = data.map(c => ({
                    commentId: c.id,
                    authorId: c.user_id.toString(),
                    content: c.content,
                    createdAt: new Date(c.created_at * 1000)
                }));
                
                // Add resolved status if available
                if (data.length > 0 && data[0].resolved_at) {
                    thread.resolvedAt = new Date(data[0].resolved_at * 1000);
                    thread.resolvedBy = data[0].resolved_by?.toString();
                    thread.isResolved = true;
                }
                
                return thread;
            },
            updateComment(data) {
                const formData = new FormData();
                formData.append('commentId', data.commentId);
                formData.append('content', data.content);
                formData.append('threadId', data.threadId);

                return fetch('/comments/update/', {
                    method: 'POST',
                    body: formData
                });
            },
            async removeComment(data) {
                await fetch('/comments/delete/?comment_id=' + data.commentId + '&thread_id=' + data.threadId)
                    .then(response => response.json());
            }
        };
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

    // Send resolved data to server to mark as archived
    new AjaxRequest(TYPO3.settings.ajaxUrls['archive_comments'] || '/comments/archive/')
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
 * Save comments to server
 */
function saveComments(rteId, commentsData, evt) {
    new AjaxRequest(TYPO3.settings.ajaxUrls['save_comments'])
        .post({
            rteId: rteId,
            commentsData: commentsData
        })
        .then(async function (response) {
            const resolved = await response.resolve();
            const responseBody = JSON.parse(resolved);
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
        });

    if (!window.commentSaved) {
        window.commentSaved = true;
    }
    evt.preventDefault();
    cms.attemptFormSubmission();
}
