function initModuleFunc(params) {
    //Global ButtonBar functionality..
    const featureForm = document.getElementById('ckEditorModules');
    const settingsForm = document.getElementById('ckEditorSettings');
    let loaderDiv = document.querySelector("#rte-ckeditor__loader");
    
    if (settingsForm) {
        settingsForm.addEventListener('submit', (e) => {
            e.preventDefault();
    
            let organizationId = settingsForm.querySelector('input[name="organizationId"]');
            if (organizationId) {
                const webSocketUrlField = settingsForm.querySelector('input[name="webSocketUrl"]');
                const webSocketUrl = `wss://${organizationId.value}.cke-cs.com/ws`;
                webSocketUrlField.value = webSocketUrl;
            }
    
            let environmentId = settingsForm.querySelector('input[name="environmentId"]');
            if (environmentId && organizationId) {
                const apiBaseUrlField = settingsForm.querySelector('input[name="apiBaseUrl"]');
                const apiBaseUrl = `https://${organizationId.value}.cke-cs.com/api/v5/${environmentId.value ? `${environmentId.value}/` : ''}`;
                apiBaseUrlField.value = apiBaseUrl;
            }
            settingsForm.submit();
    
        });
    }
    
    
    const authTypeSelect = document.getElementById('authType');
    if (authTypeSelect) {
        authTypeSelect.addEventListener('change', (event) => {
            event.preventDefault();
            const selectedAuthType = event.target.value;
            const keyTypeFormItems = document.querySelectorAll('.form-item-key-type');
            const tokenTypeFormItems = document.querySelectorAll('.form-item-dev-token-type');
    
            const toggleVisibility = (elements, shouldShow) => {
                elements.forEach(element => {
                    element.classList.toggle('d-none', !shouldShow);
                });
            };
    
            switch (selectedAuthType) {
                case 'key':
                    toggleVisibility(keyTypeFormItems, true);
                    toggleVisibility(tokenTypeFormItems, false);
                    break;
    
                case 'dev_token':
                    toggleVisibility(keyTypeFormItems, false);
                    toggleVisibility(tokenTypeFormItems, true);
                    break;
    
                case 'none':
                    toggleVisibility(keyTypeFormItems, false);
                    toggleVisibility(tokenTypeFormItems, false);
                    break;
            }
        });
    }
    
    
    const toggleModules = document.querySelectorAll('.feature-toggle');
    if (toggleModules) {
        const dashboardTabs = document.querySelector('.dashboard-tabs');
    
        toggleModules.forEach(function (element) {
            element.addEventListener('click', function (event) {
                if(loaderDiv){
                    loaderDiv.classList.add("ns-show-overlay");
                }
                let isChecked = event.target.checked;
                let inputName = event.target.name;
                let hiddenInput = featureForm.querySelector(`input[name="${inputName}"]`);
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = inputName;
                    featureForm.appendChild(hiddenInput);
                }
                hiddenInput.value = isChecked;
    
                let currentTab = document.createElement('input');
                currentTab.type = 'hidden';
                currentTab.name = 'active_tab';
    
                // active tab
                let activeTab = dashboardTabs.querySelector('.dashboard-tab.active');
                currentTab.value = activeTab.getAttribute('id').split('-tab')[0];
    
                featureForm.appendChild(currentTab);
               
                featureForm.submit();
            });
        });
    }
    
    if(featureForm){
        featureForm.addEventListener('submit', function () {
            if(loaderDiv){
                loaderDiv.classList.add("ns-show-overlay");
            }
        });
    }
}

const cardCheck = document.querySelectorAll('.btn-toggle');
if (cardCheck.length) {
  cardCheck.forEach(element => {
    if (element.closest('.card')) {
      if (element.checked) {
        element.closest('.card').classList.add('card--active');
      } else {
        element.closest('.card').classList.remove('card--active');
      }
      element.addEventListener('change', (event) => {
        if (element.checked) {
          element.closest('.card').classList.add('card--active');
        } else {
          element.closest('.card').classList.remove('card--active');
        }
      });
    }
  });
}

initModuleFunc();
document.addEventListener('DOMContentLoaded', () => {
    initModuleFunc();
});
