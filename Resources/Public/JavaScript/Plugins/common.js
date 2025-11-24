class CheckForm {
	attemptFormSubmission() {
        if (window.commentSaved && window.revisionSaved) {
        	TYPO3.FormEngine.saveDocument();
        }
    }
}
export default CheckForm;
