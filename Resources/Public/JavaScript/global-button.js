//Global ButtonBar functionality..
import GlobalButtonBarModal from '@t3planet/RteCkeditorPack/wizard-manipulation.js';
import Notification from "@typo3/backend/notification.js";


class globalButtonBar {
    constructor() {
        this.init(event);
    }

    init(event) {
        let selectedPreset = 'default';
        
        if (event.target.hasAttribute('data-key') && event.target.hasAttribute('data-identifier', 'ckeditorGlobalWizardButton')) {
            if (event.target.getAttribute('data-feature') === '0') {
                let label = event.target.getAttribute('data-key') ?? 'API key is missing';
                Notification.warning(label);
                if (document.querySelector('#settings-tab')) {
                    document.querySelector('#settings-tab').click();
                }
            } else {

                let params = new URL(location.href).searchParams;
                let id = params.get('id');
                let presetSelector = document.querySelector('#rtePresets');
                if(presetSelector){
                    selectedPreset = presetSelector.value;
                }
                let additionalParams = event.target.getAttribute('data-additionalParams');
                let moduleKey = event.target.getAttribute('data-module-key');
                let buttonFor = event.target.getAttribute('data-key');
                var coreIcon = true;
                if (event.target.closest('.card') && event.target.closest('.card').querySelector('.card-icon .t3js-icon') && event.target.closest('.card').querySelector('.card-icon .t3js-icon').getAttribute('data-identifier')) {
                    var coreIcon = true;
                    var cardIcon = event.target.closest('.card').querySelector('.card-icon .t3js-icon').getAttribute('data-identifier');
                } else if (!event.target.closest('.card') && event.target.querySelector('.t3js-icon')) {
                    var coreIcon = true;
                    var cardIcon = event.target.querySelector('.t3js-icon').getAttribute('data-identifier');
                } else if (event.target.closest('.card') && event.target.closest('.card').querySelector('.card-icon')) {
                    var coreIcon = false;
                    var cardIcon = event.target.closest('.card').querySelector('.card-icon').innerHTML;
                }
                
                let icon = cardIcon ? cardIcon : 'module-rte-ckeditor';

                if (event.target.closest('.card') && event.target.closest('.card').querySelector('.card-title')) {
                    var cardTitle = event.target.closest('.card').querySelector('.card-title').textContent;
                }
                let buttonTitle = event.target.getAttribute('title');
                let title = cardTitle ? cardTitle : buttonTitle;

                let buttonSize = event.target.getAttribute('data-modal-size');
                let modalSize = buttonSize ? buttonSize : 'large';

                let buttonName = event.target.getAttribute('data-model-name');
                buttonName = buttonName ? buttonName : '';

                let pageId = event.target.getAttribute('data-page-id');
                pageId = pageId ? pageId : '';

                let returnUrl = event.target.getAttribute('data-return-url');
                returnUrl = returnUrl ? returnUrl : '';

                let url = TYPO3.settings.ajaxUrls[buttonFor] + '&pageId=' + id;
                if (additionalParams) {
                    url += '&additionalParams=' + additionalParams;
                }
                if (selectedPreset) {
                    url += '&selectedPreset=' + selectedPreset;
                }
                if (moduleKey) {
                    url += '&moduleKey=' + moduleKey;
                }
                if (buttonName) {
                    url += '&name=' + buttonName;
                }
                if (pageId) {
                    url += '&pid=' + pageId;
                }
                if (returnUrl) {
                    url += '&returnUrl=' + returnUrl;
                }
                
                GlobalButtonBarModal.listenEvent(url, title, modalSize, icon, coreIcon);
            }
        }
    }
}

export default globalButtonBar;

document.addEventListener('click', (event) => {
    if (event.target.hasAttribute('data-key') && event.target.hasAttribute('data-identifier', 'ckeditorGlobalWizardButton')) {
        const yourVariable = new globalButtonBar(event);
    }
});

