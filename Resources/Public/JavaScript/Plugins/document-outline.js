import { Plugin } from '@ckeditor/ckeditor5-core';

export default class DocumentOutlineJs extends Plugin {
    // static pluginName = "DocumentOutlineContainer";

    init() {
        if(this.editor.plugins.has('DocumentOutline')){
            const DocumentOutlinePlugin = this.editor.plugins.get('DocumentOutline');
            if (DocumentOutlinePlugin) {
                const channelId = this.editor.sourceElement.id;
                const documentOutlineContainerId = this.editor.sourceElement.id + 'document-outline-container';
                const formItem = document.querySelector('#'+channelId).closest('.form-control-wrap');
                const documentOutlineWrapper = document.createElement("div");
                
                if (documentOutlineWrapper) {
                    documentOutlineWrapper.className =  "ck-document-outline-container";                
                    documentOutlineWrapper.id = documentOutlineContainerId;
                }
                
                if (formItem) {
                    formItem.classList.add('rte-ckeditor-document-outline');
                    formItem.insertBefore(documentOutlineWrapper, formItem.firstChild);
                }
                
                // presenceListConfig.container = documentOutlineWrapper;
                if (document.querySelector('#'+documentOutlineContainerId)) {
                    this.editor.config._config.documentOutline = {
                        container: document.querySelector('#'+documentOutlineContainerId)
                    }
                }
            }
        }
       
    }
}
