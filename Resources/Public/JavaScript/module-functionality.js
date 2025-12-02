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
        // Function to toggle visibility based on auth type
        const updateAuthFieldsVisibility = (selectedAuthType) => {
            const keyTypeFormItems = document.querySelectorAll('.form-item-auth-key');
            const tokenTypeFormItems = document.querySelectorAll('.form-item-auth-dev-token');
    
            const toggleVisibility = (elements, shouldShow) => {
                elements.forEach(element => {
                    element.classList.toggle('d-none', !shouldShow);
                });
            };
    
            switch (selectedAuthType) {
                case 'key':
                    // Show: Environment ID, Access Key, Organization ID, API Key
                    toggleVisibility(keyTypeFormItems, true);
                    toggleVisibility(tokenTypeFormItems, false);
                    break;
    
                case 'dev_token':
                    // Show: Token URL, Organization ID, API Key
                    toggleVisibility(keyTypeFormItems, false);
                    toggleVisibility(tokenTypeFormItems, true);
                    break;
    
                case 'none':
                    // Show: Organization ID, API Key only
                    toggleVisibility(keyTypeFormItems, false);
                    toggleVisibility(tokenTypeFormItems, false);
                    break;
            }
        };
        
        // Set initial visibility based on current selection
        updateAuthFieldsVisibility(authTypeSelect.value);
        
        // Update visibility on change
        authTypeSelect.addEventListener('change', (event) => {
            event.preventDefault();
            updateAuthFieldsVisibility(event.target.value);
        });
    }
    
    
    const toggleModules = document.querySelectorAll('.feature-toggle');
    if (toggleModules) {
        const dashboardTabs = document.querySelector('.dashboard-tabs');
    
        toggleModules.forEach(function (element) {
            element.addEventListener('click', function (event) {
                let isChecked = event.target.checked;
                
                // If checkbox has 'config-not-saved' class and is being enabled,
                // open modal first before allowing toggle
                if (element.classList.contains('config-not-saved') && isChecked) {
                    // Find the card containing this checkbox
                    const card = element.closest('.card');
                    if (card) {
                        // Find the settings button in the same card
                        const settingsButton = card.querySelector('button[data-identifier="ckeditorGlobalWizardButton"]');
                        
                        if (settingsButton) {
                            // Prevent form submission
                            event.preventDefault();
                            event.stopPropagation();
                            
                            // Reset checkbox to unchecked state
                            element.checked = false;
                            
                            // Trigger click on settings button to open modal
                            settingsButton.click();
                            return false;
                        }
                    }
                }
                
                // Normal behavior - proceed with form submission
                if(loaderDiv){
                    loaderDiv.classList.add("ns-show-overlay");
                }
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

function selectPresetFromLocalStorage() {
    const activePreset = localStorage.getItem('activePreset');
    const shouldSelectLastPreset = sessionStorage.getItem('selectLastPreset');
    
    const presetSelect = document.getElementById('rtePresets');
    if (!presetSelect || presetSelect.options.length === 0) {
        return;
    }
    
    // Handle newly created preset
    if (shouldSelectLastPreset === 'true') {
        if (presetSelect.options.length > 0) {
            const lastIndex = presetSelect.options.length - 1;
            const lastPresetValue = presetSelect.options[lastIndex].value;
            
            presetSelect.selectedIndex = lastIndex;
            presetSelect.value = lastPresetValue;
            
            // Save to localStorage
            localStorage.setItem('activePreset', lastPresetValue);
            
            // Trigger change to submit form and load preset data
            presetSelect.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Remove flag
            sessionStorage.removeItem('selectLastPreset');
        }
        return;
    }
    
    // Handle restoring from localStorage
    if (activePreset) {
        // Check if current selected value matches localStorage
        const currentValue = presetSelect.value;
        
        // Check if backend has already selected the localStorage preset
        let backendHasCorrectPreset = false;
        for (let i = 0; i < presetSelect.options.length; i++) {
            if (presetSelect.options[i].value === activePreset && presetSelect.options[i].hasAttribute('selected')) {
                backendHasCorrectPreset = true;
                break;
            }
        }
        
        // Only trigger change if:
        // 1. Current value is different from localStorage value
        // 2. AND backend hasn't already selected the localStorage preset
        if (currentValue !== activePreset && !backendHasCorrectPreset) {
            // Find and select the preset
            for (let i = 0; i < presetSelect.options.length; i++) {
                if (presetSelect.options[i].value === activePreset) {
                    presetSelect.selectedIndex = i;
                    presetSelect.value = activePreset;
                    
                    // Trigger change event to load the preset data (will submit form once)
                    presetSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    break;
                }
            }
        }
        // If values match OR backend already selected it, do nothing (no reload)
    }
}

initModuleFunc();
// Wait for page to fully load before selecting preset
if (document.readyState === 'complete') {
    selectPresetFromLocalStorage();
} else {
    window.addEventListener('load', () => {
        selectPresetFromLocalStorage();
    });
}
document.addEventListener('DOMContentLoaded', () => {
    initModuleFunc();
});
