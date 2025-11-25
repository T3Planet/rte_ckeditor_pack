// ErrorNotifications CKEditor 5 plugin (ESM version) for TYPO3 rte_ckeditor_pack
// Ported from Drupal ckeditor5_premium_features errorNotifications plugin.

import { Plugin } from '@ckeditor/ckeditor5-core';
import { View } from '@ckeditor/ckeditor5-ui';
import { Rect, Collection } from '@ckeditor/ckeditor5-utils';

const definitions = [
  {
    header: TYPO3.lang['errorNotifications.header.oops'],
    description: TYPO3.lang['errorNotifications.description.oops'],
    type: 'error',
    reactsTo: { name: 'CKEditorError' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.trialExceeded'],
    description: TYPO3.lang['errorNotifications.description.trialExceeded'],
    type: 'error',
    reactsTo: { message: 'trial-license-key-reached-limit' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.invalidLicenseKey'],
    description: TYPO3.lang['errorNotifications.description.invalidLicenseKey'],
    type: 'error',
    reactsTo: { message: 'invalid-license-key' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.trialExceeded'],
    description: TYPO3.lang['errorNotifications.description.trialExceeded'],
    type: 'error',
    reactsTo: { message: 'license-key-trial-limit' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.usageLimit'],
    description: TYPO3.lang['errorNotifications.description.usageLimit'],
    type: 'error',
    reactsTo: { message: 'license-key-usage-limit' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.wproofreaderAuth'],
    description: TYPO3.lang['errorNotifications.description.wproofreaderAuth'],
    type: 'error',
    reactsTo: { message: 'wproofreader-service-id-error' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.wproofreaderLimit'],
    description: TYPO3.lang['errorNotifications.description.wproofreaderLimit'],
    type: 'error',
    reactsTo: { message: 'wproofreader-usage-limit-error' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.wproofreaderError'],
    description: TYPO3.lang['errorNotifications.description.wproofreaderError'],
    type: 'error',
    reactsTo: { message: 'wproofreader-permission-error' }
  },
  {
    header: TYPO3.lang['errorNotifications.header.accessDenied'],
    description: TYPO3.lang['errorNotifications.description.accessDenied'],
    type: 'unhandledrejection',
    reactsTo: { message: "You don't have enough permissions to access this resource" }
  },
  {
    header: '',
    description: TYPO3.lang['errorNotifications.header.websocketMissingToken'],
    type: 'unhandledrejection',
    reactsTo: { message: "websocketgateway-missing-token" }
  }
];


export default class ErrorNotifications extends Plugin {
  constructor(...args) {
    super(...args);
    this.availableNotifications = new Collection();
    this.activeNotification = null;
  }

  static get pluginName() {
    return 'ErrorNotifications';
  }

  init() {
    const editor = this.editor;

    this._setupNotifications(definitions);

    this.set('_editable', null);
    editor.ui.once('ready', () => this.set('_editable', editor.ui.view.editable.element));

    this._attachListeners();
  }

  destroy() {
    if (this.activeNotification) {
      this.activeNotification.hide();
      this.editor.ui.view.main.remove(this.activeNotification);
    }
    this.activeNotification = null;
    this._detachListeners();
    super.destroy();
  }

  _setupNotifications(defs) {
    for (const definition of defs) {
      const notification = new NotificationView(this.editor.locale, definition);
      notification.bind('_editable').to(this, '_editable');
      notification.on('closeNotification', () => {
        notification.hide();
        this.activeNotification = null;
        this.editor.ui.view.main.remove(notification);
        this.editor.editing.view.focus();
      });
      this.availableNotifications.add(notification);
    }
  }

  _attachListeners() {
    window.addEventListener('error', this._handleError.bind(this));
    window.addEventListener('unhandledrejection', this._handleError.bind(this));
  }

  _detachListeners() {
    window.removeEventListener('error', this._handleError.bind(this));
    window.removeEventListener('unhandledrejection', this._handleError.bind(this));
  }

  _handleError(evt) {
    let notificationToShow = null;
    let msg = null;
    const matches = new Collection();

    if (
      this.activeNotification ||
      (evt.type === 'error' && !evt.error) ||
      (evt.type === 'unhandledrejection' && !evt.reason)
    ) {
      return;
    }

    for (const notification of this.availableNotifications) {
      const reactsTo = notification.reactsTo;
      for (const key in reactsTo) {
        if (evt.type === 'error' && evt.error[key] && evt.error[key].includes(reactsTo[key])) {
          matches.add(notification);
        }
        if (
          evt.type === 'unhandledrejection' &&
          evt.reason[key] &&
          evt.reason[key].includes(reactsTo[key])
        ) {
          matches.add(notification);
        }
      }
    }

    // Notifications that react to the specific error message have higher priority
    if (matches.length > 1) {
      notificationToShow = matches.find(n => n.reactsTo.message);
    } else {
      notificationToShow = matches.first;
    }

    if (!notificationToShow) {
      return;
    }

    // Get the header value from the matched notification
    msg = notificationToShow.header || null;

    this.activeNotification = notificationToShow;
    this.activeNotification.show();
    if(this.editor.ui.view.element){
      this.editor.ui.view.main.add(this.activeNotification);
    }else{
      console.warn((msg || 'Something went wrong'));
    }
    
  }
}

class NotificationView extends View {
  constructor(locale, definition) {
    super(locale);
    this.reactsTo = definition.reactsTo;
    this.header = definition.description;
    this.closeNotificationButton = null;
    this.set('_editable', null);
    this.set('isVisible', false);
    this.set('positionBottom', '20px');
    this.set('positionRight', '15px');
    this.createTemplate(definition);
    this.render();
    this.on('change:isVisible', () => this._updateNotificationPosition());
    this.listenTo(document, 'scroll', () => {
      if (this.isVisible) {
        this._updateNotificationPosition();
      }
    });
  }

  createTemplate(definition) {
    const bind = this.bindTemplate;
    const notificationHeader = this._createNotificationHeader(definition.header, definition.type);
    const notificationDescription = this._createNotificationDescription(definition.description);
    const closeNotificationButton = this._createCloseNotificationButton();
    this.setTemplate({
      tag: 'div',
      attributes: {
        class: ['ck-notification', `ck-notification__${definition.type}`, bind.if('isVisible', 'ck-hidden', value => !value)],
        style: {
          position: 'absolute',
          bottom: bind.to('positionBottom'),
          right: bind.to('positionRight'),
          'z-index': 999
        }
      },
      children: [notificationHeader, notificationDescription, closeNotificationButton]
    });
  }

  show() {
    this.isVisible = true;
  }

  hide() {
    this.isVisible = false;
  }

  _createNotificationHeader(text, type) {
    const view = new View();
    view.setTemplate({
      tag: 'h4',
      attributes: { class: ['ck-notification__header', `ck-notification__header-${type}`] },
      children: [text]
    });
    return view;
  }

  _createNotificationDescription(text) {
    const view = new View();
    view.setTemplate({
      tag: 'p',
      attributes: { class: ['ck-notification__description'] },
      children: [text]
    });
    return view;
  }

  _createCloseNotificationButton() {
    const view = new View();
    const bind = view.bindTemplate;
    view.setTemplate({
      tag: 'span',
      attributes: { class: ['ck-notification__close'] },
      children: ['x'],
      on: { click: bind.to(() => this.fire('closeNotification')) }
    });
    return view;
  }

  _updateNotificationPosition() {
    const editable = this._editable;
    if (!editable) {
      return;
    }
    const editableRect = new Rect(editable);
    const offsetTop = editableRect.top;
    const clientTop = document.documentElement.clientTop || 0;
    const scrollTop = document.documentElement.scrollTop;
    const top = offsetTop - clientTop - scrollTop;
    const bottom = top + editableRect.height;
    const positionRight = document.body.offsetWidth - editableRect.right + scrollTop;
    this.positionRight = `${positionRight}px`;
    this.positionBottom = `${Math.max(20, document.documentElement.clientHeight - bottom)}px`;
  }
}

