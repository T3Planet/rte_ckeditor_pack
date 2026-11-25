/**
 * CKEditor fullscreen calls element.replaceWith() and crashes when an optional
 * node is missing (pagination view, menu bar, document outline) inside FormEngine.
 * Real toolbar and editable nodes are still moved.
 */
export function installFullscreenGuard(editor) {
  const command = editor?.commands?.get?.('toggleFullscreen');
  const handler = command?.fullscreenHandler;
  if (!handler || typeof handler.moveToFullscreen !== 'function' || typeof handler.defaultOnEnter !== 'function') {
    return;
  }

  const originalMove = handler.moveToFullscreen.bind(handler);
  handler.moveToFullscreen = (element, placeholderName) => {
    if (!element || typeof element.replaceWith !== 'function') {
      return;
    }
    originalMove(element, placeholderName);
  };

  handler.defaultOnEnter = () => {
    const ui = editor.ui;
    const view = ui?.view;
    if (editor.plugins?.has?.('Pagination') && editor.plugins.get('Pagination')?.isEnabled) {
      const editable = ui?.getEditableElement?.() || null;
      const paginationView = editable?.parentElement?.querySelector?.('.ck-pagination-view') || null;
      handler.moveToFullscreen(paginationView, 'pagination-view');
    }

    const editableElement = ui?.getEditableElement?.() || view?.editable?.element || null;
    handler.moveToFullscreen(editableElement, 'editable');

    if (view?.toolbar?.element) {
      handler.moveToFullscreen(view.toolbar.element, 'toolbar');
      if (typeof view.toolbar.switchBehavior === 'function') {
        const shouldNotGroup = readConfig(editor, 'fullscreen.toolbar.shouldNotGroupWhenFull') === true;
        view.toolbar.switchBehavior(shouldNotGroup ? 'static' : 'dynamic');
      }
    }

    const wrapper = typeof handler.getWrapper === 'function' ? handler.getWrapper() : null;
    if (wrapper && view?.element) {
      wrapper.setAttribute('dir', view.element.getAttribute('dir') || '');
      wrapper.classList.add('ck-rounded-corners');
    }

    if (readConfig(editor, 'fullscreen.menuBar.isVisible') && view?.menuBarView?.element) {
      handler.moveToFullscreen(view.menuBarView.element, 'menu-bar');
    }

    return wrapper;
  };
}

function readConfig(editor, key) {
  try {
    return editor.config.get(key);
  } catch (error) {
    return undefined;
  }
}
