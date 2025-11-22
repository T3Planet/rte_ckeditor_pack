class TrackChangesIntegration {
	constructor( editor ) {
		this.editor = editor;
	}
	afterInit() {
		if (!this.editor.plugins.has('Comments') || !this.editor.plugins.has('TrackChanges')) {
	      return
	    }
	    const rteId = this.editor.sourceElement.name;
	    const trackChangesPlugin = this.editor.plugins.get( 'TrackChanges' );
		trackChangesPlugin.adapter = {
			getSuggestion: async id => {
				try {
					const response = await fetch('/ckeditor_premium/suggestions/get/?suggestionId=' + id);
					const suggestion = await response.json();
					suggestion.createdAt = new Date(suggestion.created_at * 1000);
					suggestion.authorId = suggestion.user_id;
					suggestion.hasComments = !!parseInt(suggestion.has_comments);
					return suggestion;
				} catch (error) {
					console.log(error, "Error");
				}
			},
			addSuggestion: async params => {
				const formData = new FormData();
				formData.append( 'id', params.id );
				formData.append( 'type', params.type );
				formData.append( 'content_id', rteId );
				formData.append( 'data', JSON.stringify( params.data ) );

				if ( params.originalSuggestionId ) {
					formData.append( 'original_suggestion_id', params.originalSuggestionId );
				}

				const response = await fetch('/ckeditor_premium/suggestions', {
					method: 'POST',
					body: formData
				});
				const responseData = await response.json();
				return {
					createdAt: new Date(responseData.created_at * 1000)
				};
			},
			updateSuggestion: ( id, options ) => {
				const formData = new FormData();
				if ( options.hasComments !== undefined ) {
					formData.append( 'has_comments', options.hasComments );
				}
				if ( options.state !== undefined ) {
					formData.append( 'state', options.state );
				}
				return fetch( '/ckeditor_premium/suggestions/update/?suggestionId=' + id, {
					method: 'POST',
					body: formData
				} );
			}
		}
	}
}
export default TrackChangesIntegration;
