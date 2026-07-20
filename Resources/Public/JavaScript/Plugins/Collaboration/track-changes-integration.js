import * as Core from '@ckeditor/ckeditor5-core';

export class TrackChangesIntegration extends Core.Plugin {
    static get pluginName() {
        return 'TrackChangesIntegration';
    }

    static get requires() {
        return ['Comments', 'TrackChanges'];
    }

    afterInit() {
        const rteId = this._resolveContentId();
        if (!rteId) {
            return;
        }

        const trackChangesPlugin = this.editor.plugins.get('TrackChanges');
        trackChangesPlugin.adapter = {
            getSuggestion: async (id) => {
                try {
                    const response = await fetch('/ckeditor_premium/suggestions/get/?suggestionId=' + id);
                    const suggestion = await response.json();
                    suggestion.createdAt = new Date(suggestion.created_at * 1000);
                    suggestion.authorId = suggestion.user_id;
                    suggestion.hasComments = !!parseInt(suggestion.has_comments, 10);
                    return suggestion;
                } catch (error) {
                    console.log(error, 'Error');
                }
            },
            addSuggestion: async (params) => {
                const formData = new FormData();
                formData.append('id', params.id);
                formData.append('type', params.type);
                formData.append('content_id', rteId);
                formData.append('data', JSON.stringify(params.data));

                if (params.originalSuggestionId) {
                    formData.append('original_suggestion_id', params.originalSuggestionId);
                }

                const response = await fetch('/ckeditor_premium/suggestions', {
                    method: 'POST',
                    body: formData,
                });
                const responseData = await response.json();
                return {
                    createdAt: new Date(responseData.created_at * 1000),
                };
            },
            updateSuggestion: (id, options) => {
                const formData = new FormData();
                if (options.hasComments !== undefined) {
                    formData.append('has_comments', options.hasComments);
                }
                if (options.state !== undefined) {
                    formData.append('state', options.state);
                }
                return fetch('/ckeditor_premium/suggestions/update/?suggestionId=' + id, {
                    method: 'POST',
                    body: formData,
                });
            },
        };
    }

    _resolveContentId() {
        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.name) {
            return sourceEl.name;
        }

        const veHost = sourceEl?.closest?.('ve-editable-rich-text')
            || this.editor.ui?.element?.closest?.('ve-editable-rich-text');
        if (veHost?.table && veHost.uid !== undefined && veHost.field) {
            return `data[${veHost.table}][${veHost.uid}][${veHost.field}]`;
        }

        return sourceEl?.id || '';
    }
}
