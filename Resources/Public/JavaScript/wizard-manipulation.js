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

                currentModal.addEventListener('shown.bs.modal', function () {
                    if (currentModal.querySelector('iframe')) {
                        currentModal.querySelector('iframe').focus();
                    }
                });

                currentModal.addEventListener('hide.bs.modal', function () {
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
                });
            }
        });
    }
   
}

export default new GlobalButtonBarModal();
