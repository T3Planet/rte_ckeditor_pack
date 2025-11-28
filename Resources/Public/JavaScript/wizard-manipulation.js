import Icons from "@typo3/backend/icons.js";
import $ from "jquery";
import Severity from "@typo3/backend/severity.js";
import {default as modalObject}  from "@typo3/backend/modal.js";

class GlobalButtonBarModal {
    listenEvent(url, title, modalSize, icon, coreIcon, comingSoon)  {
        modalObject.advanced({
            type: modalObject.types.iframe,
            title: title,
            staticBackdrop: true,
            size: modalSize,
            additionalCssClasses: [`ckeditor--modal ckeditor--modal-${modalSize}`],
            content: url,
            severity: comingSoon ? Severity.info : Severity.notice,
            callback: (currentModal) => {
                $(currentModal).find("iframe").on("load", function (p) {
                    Icons.getIcon(icon, Icons.sizes.small,null,'default','inline').then(function (markup) {
                        if (coreIcon) {
                            var titleWithIcon = markup + ' ' + title;
                        } else {
                            var titleWithIcon = icon + ' ' + title;
                        }
                        currentModal.querySelector('.modal-header .t3js-modal-title').innerHTML = titleWithIcon;
                        if (!coreIcon && currentModal.querySelector('.t3js-modal-title .t3js-icon')) {
                            currentModal.querySelector('.t3js-modal-title .t3js-icon').classList.add('icon', 'icon-size-small');
                        }
                    });

                    let iframeDocument = $(this).contents();
                    let closeBtn = iframeDocument.find('[t3editor-modal="close"]');

                    if (closeBtn.length) {
                        closeBtn.each(function() {
                            $(this).on('click', function() {
                                currentModal.hideModal();
                            });
                        });
                    }  

                    let submitBtn = iframeDocument.find('button[type="submit"]');
                    if (submitBtn.length) {
                        submitBtn.each(function() {
                            $(this).on('click', function() {
                                localStorage.setItem("isModified", true);
                            });
                        });
                    }  
                });

                // Helper function to attach event listeners for both Bootstrap and TYPO3 modal events
                // This ensures compatibility with all TYPO3 versions (v11 and below use Bootstrap, v12+ use TYPO3 events)
                const attachModalEvent = (element, eventNames, handler) => {
                    const events = Array.isArray(eventNames) ? eventNames : [eventNames];
                    let executed = false;
                    const guardedHandler = function(...args) {
                        // Prevent double execution if both events fire (shouldn't happen, but safety measure)
                        if (!executed) {
                            executed = true;
                            handler.apply(this, args);
                            // Reset flag after a short delay to allow for legitimate re-triggers
                            setTimeout(() => {
                                executed = false;
                            }, 200);
                        }
                    };
                    events.forEach(eventName => {
                        element.addEventListener(eventName, guardedHandler, { once: false });
                    });
                };

                // Modal shown event - compatible with both Bootstrap (v11 and below) and TYPO3 (v12+) events
                const handleModalShown = function () {
                    if (currentModal.querySelector('iframe')) {
                        currentModal.querySelector('iframe').focus();
                    }
                };
                attachModalEvent(currentModal, ['shown.bs.modal', 'typo3-modal-shown'], handleModalShown);

                // Modal hide event - compatible with both Bootstrap (v11 and below) and TYPO3 (v12+) events
                const handleModalHide = function () {
                    if (localStorage.getItem("isModified") === 'true') {
                        const dashboardTabs = document.querySelector('.dashboard-tabs');
                        let currentModule = '';

                        if (dashboardTabs) {
                            const activeTab = dashboardTabs.querySelector('.dashboard-tab.active');
                            if (activeTab && activeTab.id) {
                                currentModule = activeTab.id.replace(/-tab$/, '');
                            }
                        }

                        const updatedUrl = new URL(window.location.href);
                        if (currentModule) {
                            updatedUrl.searchParams.set('current_module', currentModule);
                        }

                        localStorage.removeItem('isModified');
                        window.location.href = updatedUrl.toString();
                    }
                };
                attachModalEvent(currentModal, ['hide.bs.modal', 'typo3-modal-hide'], handleModalHide);
            }
        });
    }
   
}

export default new GlobalButtonBarModal();
