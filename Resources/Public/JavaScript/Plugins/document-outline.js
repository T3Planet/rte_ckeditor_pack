import { Plugin } from '@ckeditor/ckeditor5-core';

export default class DocumentOutlineJs extends Plugin {
    init() {
        if (!this.editor.plugins.has('DocumentOutline')) {
            return;
        }

        const channelId = this._resolveEditorId();
        const mountContainer = this._resolveMountContainer();
        if (!mountContainer || !channelId) {
            return;
        }

        const documentOutlineContainerId = `${channelId}document-outline-container`;
        let documentOutlineWrapper = document.getElementById(documentOutlineContainerId);

        if (!documentOutlineWrapper) {
            documentOutlineWrapper = document.createElement('div');
            documentOutlineWrapper.className = 'ck-document-outline-container';
            documentOutlineWrapper.id = documentOutlineContainerId;

            mountContainer.classList.add('rte-ckeditor-document-outline');
            mountContainer.insertBefore(documentOutlineWrapper, mountContainer.firstChild);
        }

        this.editor.config.set('documentOutline', {
            container: documentOutlineWrapper,
        });
    }

    _resolveEditorId() {
        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.id) {
            return sourceEl.id;
        }

        const veHost = this._findVeEditableHost();
        if (veHost?.table && veHost.uid !== undefined && veHost.field) {
            return `ve-${veHost.table}-${veHost.uid}-${veHost.field}`;
        }

        return `ck-outline-${Math.random().toString(36).slice(2, 11)}`;
    }

    _findVeEditableHost() {
        const anchor = this.editor.ui?.element || this.editor.sourceElement;
        return anchor?.closest?.('ve-editable-rich-text') || null;
    }

    _resolveMountContainer() {
        const veHost = this._findVeEditableHost();
        if (veHost) {
            return veHost;
        }

        const sourceEl = this.editor.sourceElement;
        if (sourceEl?.id) {
            const formItem = document.getElementById(sourceEl.id)?.closest('.form-control-wrap');
            if (formItem) {
                return formItem;
            }
        }

        const anchors = [this.editor.ui?.element, this.editor.sourceElement].filter(Boolean);
        for (const anchor of anchors) {
            const formItem = anchor.closest('.form-control-wrap');
            if (formItem) {
                return formItem;
            }
        }

        return anchors[0]?.parentElement || null;
    }
}
