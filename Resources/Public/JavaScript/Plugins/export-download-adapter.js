import { Plugin } from '@ckeditor/ckeditor5-core';

/**
 * Fixes PDF/Word export downloads inside TYPO3 backend iframes.
 *
 * CKEditor triggers download via a detached anchor + blob URL. In nested backend
 * frames the browser may navigate the current frame instead of downloading,
 * which surfaces as a Firefox error for the site host (e.g. v14compo.ddev.site).
 */
class ExportDownloadAdapter extends Plugin {
    static get pluginName() {
        return 'ExportDownloadAdapter';
    }

    afterInit() {
        this.#patchDownloadCommand('exportPdf');
        this.#patchDownloadCommand('exportWord');
    }

    #patchDownloadCommand(commandName) {
        const command = this.#getCommand(commandName);
        if (!command) {
            return;
        }

        if (typeof command._downloadFile !== 'function') {
            return;
        }

        command._downloadFile = (blob, fileName) => {
            downloadBlob(blob, fileName);
        };
    }

    #getCommand(commandName) {
        const commandCollection = this.editor?.commands;
        if (!commandCollection || typeof commandCollection.get !== 'function') {
            return null;
        }

        try {
            return commandCollection.get(commandName) || null;
        } catch (error) {
            return null;
        }
    }
}

/**
 * @param {Blob} blob
 * @param {string} fileName
 */
function downloadBlob(blob, fileName) {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = fileName;
    link.rel = 'noopener';
    link.style.display = 'none';

    const targetDocument = getDownloadDocument();
    targetDocument.body.appendChild(link);
    link.click();

    window.setTimeout(() => {
        URL.revokeObjectURL(objectUrl);
        link.remove();
    }, 100);
}

function getDownloadDocument() {
    try {
        if (window.top?.document?.body) {
            return window.top.document;
        }
    } catch (error) {
        // Cross-origin parent; fall back to the current document.
    }

    return document;
}

export default ExportDownloadAdapter;
