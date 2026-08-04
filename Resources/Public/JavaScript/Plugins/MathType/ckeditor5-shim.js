/**
 * Aggregate shim so MathType browser build can import from "ckeditor5".
 * Maps to the granular CKEditor packages already registered by this extension.
 */
export { Plugin, Command } from '@ckeditor/ckeditor5-core';
export { ButtonView } from '@ckeditor/ckeditor5-ui';
export {
    ClickObserver,
    XmlDataProcessor,
    ViewUpcastWriter,
    HtmlDataProcessor,
} from '@ckeditor/ckeditor5-engine';
export {
    Widget,
    viewToModelPositionOutsideModelElement,
    toWidget,
} from '@ckeditor/ckeditor5-widget';
