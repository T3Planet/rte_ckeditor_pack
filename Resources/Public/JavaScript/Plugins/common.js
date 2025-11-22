class CheckForm {
	attemptFormSubmission() {
        console.log(window.commentSaved, 'commentSaved');
        console.log(window.revisionSaved, 'revisionSaved');
        if (window.commentSaved && window.revisionSaved) {
        	TYPO3.FormEngine.saveDocument();
        }
    }
}
export default CheckForm;
