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
            const threads = comments.getCommentThreads({
                skipNotAttached: true,
                skipEmpty: true,
                toJSON: true
            });

            saveComments(
                editor.sourceElement.getAttribute('name'),
                JSON.stringify(threads),
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
                Notification.success('Comment', 'Comment Successfully saved and retrieved');
            }
        })
        .catch((error) => {
            Notification.error('error', error);
        });

    if (!window.commentSaved) {
        window.commentSaved = true;
    }
    evt.preventDefault();
    cms.attemptFormSubmission();
}
