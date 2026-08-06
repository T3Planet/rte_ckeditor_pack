// ErrorNotifications CKEditor 5 plugin (ESM version) for TYPO3 rte_ckeditor_pack
// Ported from Drupal ckeditor5_premium_features errorNotifications plugin.
//
// Compatible with TYPO3 12–14 FormEngine and Visual Editor (v13+).
// Page-level singleton: one banner. FormEngine floats near the editable;
// Visual Editor pins to viewport bottom-right.

import { Plugin } from '@ckeditor/ckeditor5-core';
import { View } from '@ckeditor/ckeditor5-ui';
import { Rect } from '@ckeditor/ckeditor5-utils';

const FALLBACK_LABELS = {
  'errorNotifications.header.oops': 'Oops...',
  'errorNotifications.description.oops':
    'It seems that the editor encountered an error. Save your content and refresh the page. If the error persists, contact your site administrator.',
  'errorNotifications.header.trialExceeded': 'Trial limit exceeded',
  'errorNotifications.description.trialExceeded':
    'You have reached the usage limit of your trial license key. Restart the editor - you can reload the page or save edited content.',
  'errorNotifications.header.usageLimit': 'Usage limit reached',
  'errorNotifications.description.usageLimit':
    'You have reached the usage limit of your license key. Premium features (such as AI, export, and collaboration) will stop working until the limit is extended. Please contact support at https://ckeditor.com/contact/ or ask your administrator to update the license in CKEditor Pack settings.',
  'errorNotifications.header.wproofreaderAuth': 'WProofreader Authorization Error',
  'errorNotifications.description.wproofreaderAuth':
    'Some problems occurred during WProofreader initialization. Check the WProofreader plugin configuration.',
  'errorNotifications.header.wproofreaderLimit': 'WProofreader usage limit exceeded',
  'errorNotifications.description.wproofreaderLimit':
    'The daily limit for the number of words checked using the WProofreader grammar and spell checker has been reached. Please contact your site administrator for help. Access to the service will resume at 00:00 UTC.',
  'errorNotifications.header.wproofreaderError': 'WProofreader Error',
  'errorNotifications.description.wproofreaderError':
    'You have no permission to access the WProofreader proxy.',
  'errorNotifications.header.accessDenied': 'Access denied',
  'errorNotifications.description.accessDenied':
    "You don't have enough permissions for this action.",
  'errorNotifications.header.invalidLicenseKey': 'Invalid license key',
  'errorNotifications.description.invalidLicenseKey':
    'The provided license key is invalid. Please check your license key configuration and contact your site administrator.',
  'errorNotifications.header.websocketMissingToken':
    'WebSocket connection error. The WebSocket gateway token is missing, Please check your configuration and contact your site administrator.',
};

function lang(key) {
  try {
    const labels = (typeof TYPO3 !== 'undefined' && TYPO3 && TYPO3.lang) ? TYPO3.lang : {};
    const value = labels[key];
    if (typeof value === 'string' && value !== '') {
      return value;
    }
  } catch (e) {
    // Ignore — fall through to defaults (Visual Editor FE host may lack TYPO3.lang).
  }
  return FALLBACK_LABELS[key] || key;
}

function buildDefinitions() {
  return [
    {
      header: lang('errorNotifications.header.oops'),
      description: lang('errorNotifications.description.oops'),
      type: 'error',
      eventType: 'error',
      reactsTo: { name: 'CKEditorError' },
      specificity: 1,
    },
    {
      header: lang('errorNotifications.header.trialExceeded'),
      description: lang('errorNotifications.description.trialExceeded'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'trial-license-key-reached-limit' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.invalidLicenseKey'),
      description: lang('errorNotifications.description.invalidLicenseKey'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'invalid-license-key' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.trialExceeded'),
      description: lang('errorNotifications.description.trialExceeded'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'license-key-trial-limit' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.usageLimit'),
      description: lang('errorNotifications.description.usageLimit'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'license-key-usage-limit' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.wproofreaderAuth'),
      description: lang('errorNotifications.description.wproofreaderAuth'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'wproofreader-service-id-error' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.wproofreaderLimit'),
      description: lang('errorNotifications.description.wproofreaderLimit'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'wproofreader-usage-limit-error' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.wproofreaderError'),
      description: lang('errorNotifications.description.wproofreaderError'),
      type: 'error',
      eventType: 'error',
      reactsTo: { message: 'wproofreader-permission-error' },
      specificity: 10,
    },
    {
      header: lang('errorNotifications.header.accessDenied'),
      description: lang('errorNotifications.description.accessDenied'),
      type: 'error',
      eventType: 'unhandledrejection',
      reactsTo: { message: "You don't have enough permissions to access this resource" },
      specificity: 10,
    },
    {
      header: '',
      description: lang('errorNotifications.header.websocketMissingToken'),
      type: 'error',
      eventType: 'unhandledrejection',
      reactsTo: { message: 'websocketgateway-missing-token' },
      specificity: 10,
    },
  ];
}

function normalizeErrorSource(source) {
  if (source == null) {
    return null;
  }
  if (typeof source === 'string') {
    return { message: source, name: '' };
  }
  if (typeof source === 'object') {
    return source;
  }
  return { message: String(source), name: '' };
}

function resolveEditableElement(editor) {
  return editor.ui.getEditableElement?.()
    || editor.ui.view?.editable?.element
    || null;
}

/**
 * Visual Editor (TYPO3 v13+) hosts RTEs in <ve-editable-rich-text>.
 * FormEngine has no such hosts — keep editable-anchored float there.
 */
function isVisualEditorContext(editor = null) {
  if (typeof document === 'undefined') {
    return false;
  }
  if (document.querySelector('ve-editable-rich-text')) {
    return true;
  }
  if (!editor) {
    return false;
  }
  const source = editor.sourceElement;
  return !!(
    source?.closest?.('ve-editable-rich-text')
    || editor.ui?.element?.closest?.('ve-editable-rich-text')
    || editor.ui?.view?.element?.closest?.('ve-editable-rich-text')
  );
}

/**
 * Shared host for all RTE instances on the page (critical for Visual Editor multi-CE).
 * One window listener + at most one floating banner anchored to the focused editable.
 */
const notificationHost = {
  refs: 0,
  views: null,
  activeView: null,
  boundHandler: null,
  boundReposition: null,
  /** @type {Map<object, HTMLElement|null>} */
  editors: new Map(),
  /** Fingerprints already shown this page load (license limits fire once per editor). */
  shownFingerprints: new Set(),

  acquire(editor) {
    if (!this.views) {
      this.views = buildDefinitions().map((definition) => {
        const view = new NotificationView(editor.locale, definition);
        view.on('closeNotification', () => this.hide());
        return view;
      });
    }

    this.editors.set(editor, null);
    editor.ui.once('ready', () => {
      if (this.editors.has(editor)) {
        this.editors.set(editor, resolveEditableElement(editor));
        this.reposition();
      }
    });

    if (this.refs === 0) {
      this.boundHandler = (evt) => this.handleError(evt);
      this.boundReposition = () => this.reposition();
      window.addEventListener('error', this.boundHandler);
      window.addEventListener('unhandledrejection', this.boundHandler);
      window.addEventListener('scroll', this.boundReposition, true);
      window.addEventListener('resize', this.boundReposition);
    }
    this.refs += 1;
  },

  release(editor) {
    this.editors.delete(editor);
    this.refs = Math.max(0, this.refs - 1);
    if (this.refs > 0) {
      this.reposition();
      return;
    }
    if (this.boundHandler) {
      window.removeEventListener('error', this.boundHandler);
      window.removeEventListener('unhandledrejection', this.boundHandler);
      this.boundHandler = null;
    }
    if (this.boundReposition) {
      window.removeEventListener('scroll', this.boundReposition, true);
      window.removeEventListener('resize', this.boundReposition);
      this.boundReposition = null;
    }
    this.hide();
    this._destroyViews();
  },

  _destroyViews() {
    if (!this.views) {
      return;
    }
    for (const view of this.views) {
      try {
        view.destroy();
      } catch (e) {
        // Ignore teardown errors during editor destroy races.
      }
    }
    this.views = null;
  },

  hide() {
    if (!this.activeView) {
      return;
    }
    this.activeView.hide();
    const el = this.activeView.element;
    if (el?.parentNode) {
      el.parentNode.removeChild(el);
    }
    this.activeView = null;
  },

  handleError(evt) {
    if (!this.views || this.activeView) {
      return;
    }
    if (
      (evt.type === 'error' && !evt.error) ||
      (evt.type === 'unhandledrejection' && evt.reason == null)
    ) {
      return;
    }

    const source = normalizeErrorSource(
      evt.type === 'error' ? evt.error : evt.reason
    );
    if (!source) {
      return;
    }

    const fingerprint = this._fingerprint(evt.type, source);
    if (fingerprint && this.shownFingerprints.has(fingerprint)) {
      return;
    }

    const matches = this.views.filter((view) => this._viewMatches(view, evt.type, source));
    if (!matches.length) {
      return;
    }

    matches.sort((a, b) => (b.specificity || 0) - (a.specificity || 0));
    const notificationToShow = matches[0];

    if (fingerprint) {
      this.shownFingerprints.add(fingerprint);
    }

    this.activeView = notificationToShow;
    this.activeView.show();
    this._mount(this.activeView);
    this.reposition();
  },

  /**
   * FormEngine: float near the focused editable.
   * Visual Editor: pin to viewport bottom-right (stable with multi-CE pages).
   */
  reposition() {
    if (!this.activeView?.isVisible) {
      return;
    }
    if (isVisualEditorContext()) {
      this.activeView.pinToViewportBottomRight();
      return;
    }
    const editable = this._resolveAnchorEditable();
    this.activeView.updateFloatingPosition(editable);
  },

  _resolveAnchorEditable() {
    // Prefer focused / active editable across FormEngine instances.
    for (const [editor, editable] of this.editors) {
      if (editor.ui?.focusTracker?.isFocused && editable) {
        return editable;
      }
    }
    for (const editable of this.editors.values()) {
      if (editable && document.contains(editable)) {
        return editable;
      }
    }
    for (const editor of this.editors.keys()) {
      const editable = resolveEditableElement(editor);
      if (editable) {
        this.editors.set(editor, editable);
        return editable;
      }
    }
    return null;
  },

  _fingerprint(type, source) {
    if (typeof source.message === 'string' && source.message) {
      return `${type}:message:${source.message}`;
    }
    if (typeof source.name === 'string' && source.name) {
      return `${type}:name:${source.name}`;
    }
    return null;
  },

  _viewMatches(view, type, source) {
    if (view.eventType && view.eventType !== type) {
      return false;
    }
    const reactsTo = view.reactsTo;
    for (const key of Object.keys(reactsTo)) {
      const expected = reactsTo[key];
      const actual = source[key];
      if (actual != null && String(actual).includes(expected)) {
        return true;
      }
    }
    return false;
  },

  _mount(notification) {
    const el = notification.element;
    if (!el) {
      return;
    }
    if (el.parentNode !== document.body) {
      document.body.appendChild(el);
    }
  },
};

export default class ErrorNotifications extends Plugin {
  static get pluginName() {
    return 'ErrorNotifications';
  }

  init() {
    notificationHost.acquire(this.editor);
  }

  destroy() {
    notificationHost.release(this.editor);
    super.destroy();
  }
}

class NotificationView extends View {
  constructor(locale, definition) {
    super(locale);
    this.reactsTo = definition.reactsTo;
    this.eventType = definition.eventType || 'error';
    this.specificity = definition.specificity || 0;
    this.header = definition.description;
    this.set('isVisible', false);
    this.set('positionBottom', '20px');
    this.set('positionRight', '15px');
    this.createTemplate(definition);
    this.render();
  }

  createTemplate(definition) {
    const bind = this.bindTemplate;
    const notificationHeader = this._createNotificationHeader(definition.header, definition.type);
    const notificationDescription = this._createNotificationDescription(definition.description);
    const closeNotificationButton = this._createCloseNotificationButton();
    this.setTemplate({
      tag: 'div',
      attributes: {
        class: [
          'ck',
          'ck-notification',
          'ck-notification_floating',
          `ck-notification__${definition.type}`,
          bind.if('isVisible', 'ck-hidden', (value) => !value),
        ],
        role: 'alert',
        'data-ck-pack-notification': '1',
        style: {
          position: 'fixed',
          bottom: bind.to('positionBottom'),
          right: bind.to('positionRight'),
          'z-index': 50000,
        },
      },
      children: [notificationHeader, notificationDescription, closeNotificationButton],
    });
  }

  show() {
    this.isVisible = true;
  }

  hide() {
    this.isVisible = false;
  }

  /**
   * Visual Editor: stable viewport bottom-right (does not follow each CE).
   */
  pinToViewportBottomRight() {
    if (window.matchMedia('(max-width: 480px)').matches) {
      this.positionBottom = '1rem';
      this.positionRight = '1rem';
      return;
    }
    this.positionBottom = '20px';
    this.positionRight = '15px';
  }

  /**
   * FormEngine: float at the bottom-right of the editable (viewport-relative via position:fixed).
   */
  updateFloatingPosition(editable) {
    if (window.matchMedia('(max-width: 480px)').matches) {
      this.positionBottom = '1rem';
      this.positionRight = '1rem';
      return;
    }
    if (!editable) {
      this.pinToViewportBottomRight();
      return;
    }
    const rect = new Rect(editable);
    this.positionRight = `${Math.max(15, window.innerWidth - rect.right)}px`;
    this.positionBottom = `${Math.max(20, window.innerHeight - rect.bottom)}px`;
  }

  _createNotificationHeader(text, type) {
    const view = new View();
    view.setTemplate({
      tag: 'h4',
      attributes: { class: ['ck-notification__header', `ck-notification__header-${type}`] },
      children: [text || ''],
    });
    return view;
  }

  _createNotificationDescription(text) {
    const view = new View();
    view.setTemplate({
      tag: 'p',
      attributes: { class: ['ck-notification__description'] },
      children: [text || ''],
    });
    return view;
  }

  _createCloseNotificationButton() {
    const view = new View();
    const bind = view.bindTemplate;
    view.setTemplate({
      tag: 'button',
      attributes: {
        type: 'button',
        class: ['ck-notification__close'],
        'aria-label': 'Close',
      },
      children: ['×'],
      on: {
        click: bind.to(() => this.fire('closeNotification')),
      },
    });
    return view;
  }
}
