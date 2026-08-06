/**
 * Named export wrapper for MathType (Wiris) CKEditor 5 plugin.
 *
 * Visual Editor (and any page with multiple RTEs) creates one MathType plugin
 * per editor. Without coordination, each instance opens its own Wiris modal at
 * the same fixed viewport position, so dialogs stack on top of each other.
 * This wrapper keeps a registry and closes other open modals before opening.
 *
 * Demo/trial Wiris builds also inject a license notice that needs a taller modal
 * than Wiris' default 338px stack height — otherwise the notice sits outside
 * the dialog box in TYPO3 backend.
 */
import MathTypePlugin, {
    CKEditor5Integration,
    MathTypeCommand,
    ChemTypeCommand,
} from '@t3planet/RteCkeditorPack/mathtype-ckeditor5.js';

/** Minimum stack-modal size so the trial/license banner stays inside the dialog. */
const MATHTYPE_MODAL_MIN_HEIGHT = 560;
const MATHTYPE_MODAL_MIN_WIDTH = 640;

/** @type {Set<object>} */
const mathTypeIntegrationRegistry = new Set();

/**
 * @param {string|undefined|null} id
 * @returns {string|null}
 */
function modalInstanceIndex(id) {
    if (!id) {
        return null;
    }
    const match = String(id).match(/\[(\d+)\]/);
    return match ? match[1] : null;
}

/**
 * Hide leftover / orphaned Wiris modal nodes that are not the active one.
 *
 * @param {HTMLElement|null|undefined} activeContainer
 */
function hideInactiveMathTypeModals(activeContainer) {
    const activeIndex = modalInstanceIndex(activeContainer?.id);

    document.querySelectorAll('.wrs_modal_dialogContainer').forEach((container) => {
        if (activeContainer && container === activeContainer) {
            return;
        }
        container.classList.add('wrs_closed');
        container.classList.remove('wrs_stack', 'wrs_maximized', 'wrs_minimized', 'wrs_drag');
    });

    document.querySelectorAll('.wrs_modal_overlay').forEach((overlay) => {
        if (activeIndex !== null && modalInstanceIndex(overlay.id) === activeIndex) {
            return;
        }
        overlay.classList.add('wrs_closed');
        overlay.classList.remove('wrs_overlay_active', 'wrs_stack', 'wrs_maximized', 'wrs_minimized');
    });
}

/**
 * Close MathType/ChemType dialogs belonging to other RTE instances.
 *
 * @param {object} activeIntegration
 */
function closeOtherMathTypeModals(activeIntegration) {
    for (const integration of mathTypeIntegrationRegistry) {
        if (integration === activeIntegration) {
            continue;
        }
        const modal = integration?.core?.modalDialog;
        if (!modal) {
            continue;
        }
        try {
            if (modal.properties?.open || (modal.container && !modal.container.classList.contains('wrs_closed'))) {
                modal.close();
            }
        } catch (error) {
            console.warn('[rte_ckeditor_pack] Failed to close MathType modal for inactive editor', error);
        }
    }

    hideInactiveMathTypeModals(activeIntegration?.core?.modalDialog?.container ?? null);
}

/**
 * Enlarge Wiris stack modal so trial/license text fits inside the dialog.
 *
 * @param {object} integration
 */
function ensureMathTypeModalFitsContent(integration) {
    const modal = integration?.core?.modalDialog;
    const container = modal?.container;
    if (!modal || !container || container.classList.contains('wrs_closed')) {
        return;
    }

    // Do not fight fullscreen maximize mode.
    if (container.classList.contains('wrs_maximized')) {
        return;
    }

    const currentHeight = parseInt(container.style.height, 10) || container.clientHeight || 0;
    const currentWidth = parseInt(container.style.width, 10) || container.clientWidth || 0;
    const nextHeight = Math.max(currentHeight, MATHTYPE_MODAL_MIN_HEIGHT);
    const nextWidth = Math.max(currentWidth, MATHTYPE_MODAL_MIN_WIDTH);

    if (typeof modal.setSize === 'function') {
        modal.setSize(nextHeight, nextWidth);
    } else {
        container.style.height = `${nextHeight}px`;
        container.style.width = `${nextWidth}px`;
    }

    if (modal.properties?.size) {
        modal.properties.size.height = nextHeight;
        modal.properties.size.width = nextWidth;
    }

    // Keep the dialog on-screen after growing.
    const maxBottom = Math.max(8, window.innerHeight - nextHeight - 8);
    const maxRight = Math.max(8, window.innerWidth - nextWidth - 8);
    const bottom = Math.min(parseInt(container.style.bottom, 10) || 0, maxBottom);
    const right = Math.min(parseInt(container.style.right, 10) || 10, maxRight);
    if (typeof modal.setPosition === 'function') {
        modal.setPosition(bottom, right);
    } else {
        container.style.bottom = `${bottom}px`;
        container.style.right = `${right}px`;
    }

    container.classList.add('wrs_pack_modal_fitted');
}

/**
 * @param {object} integration
 * @param {(...args: unknown[]) => unknown} original
 * @param {unknown[]} args
 * @returns {unknown}
 */
function openMathTypeEditor(integration, original, args) {
    closeOtherMathTypeModals(integration);
    if (typeof window !== 'undefined' && window.WirisPlugin) {
        window.WirisPlugin.currentInstance = integration;
    }
    const result = original.apply(integration, args);
    // After open/create, keep only this instance's modal visible and sized for trial UI.
    queueMicrotask(() => {
        hideInactiveMathTypeModals(integration?.core?.modalDialog?.container ?? null);
        ensureMathTypeModalFitsContent(integration);
    });
    // Editor iframe / trial banner can load slightly later.
    setTimeout(() => ensureMathTypeModalFitsContent(integration), 250);
    setTimeout(() => ensureMathTypeModalFitsContent(integration), 800);
    return result;
}

const originalOpenNew = CKEditor5Integration.prototype.openNewFormulaEditor;
CKEditor5Integration.prototype.openNewFormulaEditor = function openNewFormulaEditorPatched(...args) {
    return openMathTypeEditor(this, originalOpenNew, args);
};

const originalOpenExisting = CKEditor5Integration.prototype.openExistingFormulaEditor;
CKEditor5Integration.prototype.openExistingFormulaEditor = function openExistingFormulaEditorPatched(...args) {
    return openMathTypeEditor(this, originalOpenExisting, args);
};

const originalAddIntegration = MathTypePlugin.prototype._addIntegration;
MathTypePlugin.prototype._addIntegration = function addIntegrationPatched() {
    const integration = originalAddIntegration.call(this);
    if (integration) {
        this._mathTypeIntegration = integration;
        mathTypeIntegrationRegistry.add(integration);
    }
    return integration;
};

MathTypePlugin.prototype.destroy = function destroyPatched() {
    const integration = this._mathTypeIntegration;
    if (!integration) {
        return;
    }

    mathTypeIntegrationRegistry.delete(integration);
    this._mathTypeIntegration = null;

    try {
        // integration.destroy() also removes this instance's Wiris modal.
        integration.destroy?.();
    } catch (error) {
        console.warn('[rte_ckeditor_pack] Failed to destroy MathType integration', error);
    }
};

class MathType extends MathTypePlugin {
    static get pluginName() {
        return 'MathType';
    }
}

export {
    MathType,
    CKEditor5Integration,
    MathTypeCommand,
    ChemTypeCommand,
};

export default MathType;
