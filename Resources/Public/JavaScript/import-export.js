import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import Modal from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";

// Helper function to proceed with import
function proceedWithImport(form, submitButton) {
    const formData = new FormData(form);
    submitButton.disabled = true;
    submitButton.classList.add('is-loading');
    const originalContent = submitButton.innerHTML;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (TYPO3.lang['js.importing'] || 'Importing...');
    
    new AjaxRequest(TYPO3.settings.ajaxUrls['import_preset'])
        .post(formData)
        .then(async (response) => {
            const responseBody = await response.resolve();
            
            // Handle notifications
            if (typeof responseBody === 'object' && responseBody.notifications && Array.isArray(responseBody.notifications)) {
                responseBody.notifications.forEach((notification) => {
                    const title = TYPO3.lang[notification.title] ?? notification.title ?? '';
                    const message = TYPO3.lang[notification.message] ?? notification.message ?? '';
                    const severity = notification.severity ?? 0;
                    if (severity === 0) {
                        Notification.success(title, message);
                    } else if (severity === 1) {
                        Notification.warning(title, message);
                    } else if (severity === 2) {
                        Notification.error(title, message);
                    } else {
                        Notification.info(title, message);
                    }
                });
            }
            
            // Clear form and reload on success
            if (responseBody.notifications && responseBody.notifications.length > 0 && responseBody.notifications[0].severity === 0) {
                // Store flag to select last item after reload
                sessionStorage.setItem('selectLastPreset', 'true');
                
                form.reset();
                top.window.location.reload();
            }
        })
        .catch((error) => {
            console.error('AJAX Error:', error);
            Notification.error(TYPO3.lang['js.import.error'] || 'Import Error', error.message ?? (TYPO3.lang['js.import.error.message'] || 'An error occurred while importing the preset'));
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.classList.remove('is-loading');
            submitButton.innerHTML = originalContent;
        });
}

document.addEventListener('click', (event) => {
    if (event.target.tagName === 'BUTTON') {
        if (event.target.classList.contains('export-preset')) {
            event.preventDefault();
            const button = event.target;
            const presetUid = button.getAttribute('data-preset-uid');
            
            if (!presetUid) {
                Notification.error(TYPO3.lang['js.error'] || 'Error', TYPO3.lang['js.error.preset_uid_missing'] || 'Preset UID is missing');
                return;
            }

            // Disable button during request
            button.disabled = true;
            const originalContent = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (TYPO3.lang['js.exporting'] || 'Exporting...');

            // Use direct form submission to avoid CSP issues with blob URLs
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = TYPO3.settings.ajaxUrls['export_preset'];
            form.style.display = 'none';
            
            // Add presetUid as hidden input
            const presetUidInput = document.createElement('input');
            presetUidInput.type = 'hidden';
            presetUidInput.name = 'presetUid';
            presetUidInput.value = presetUid;
            form.appendChild(presetUidInput);
            
            // Append form to body
            document.body.appendChild(form);
            
            // Submit form - this will trigger the download directly without CSP issues
            form.submit();
            
            // Re-enable button and show notification after a short delay
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
                Notification.success(TYPO3.lang['js.export.started'] || 'Export Started', TYPO3.lang['js.export.started.message'] || 'YAML file download should start shortly');
                
                // Clean up form after delay
                setTimeout(() => {
                    if (form.parentNode) {
                        document.body.removeChild(form);
                    }
                }, 1000);
            }, 500);
        }
    }
    if (event.target.id === 'importPresetBtn' || event.target.closest('#importPresetBtn')) {
        event.preventDefault();
        const submitButton = event.target.id === 'importPresetBtn' ? event.target : event.target.closest('#importPresetBtn');
        const form = submitButton.closest('form');
        if (!form || !submitButton || submitButton.disabled) {
            return;
        }
        
        const fileInput = form.querySelector('#yaml-file');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Notification.error(TYPO3.lang['js.error'] || 'Error', TYPO3.lang['js.import.file.select'] || 'Please select a YAML file to import');
            return;
        }
        
        // Extract preset key from filename
        const fileName = fileInput.files[0].name;
        const fileNameWithoutExt = fileName.replace(/\.(yaml|yml)$/i, '');
        
        // Validate filename: only alphanumeric, underscores, and hyphens allowed
        const validFileNamePattern = /^[a-zA-Z0-9_-]+$/;
        if (!validFileNamePattern.test(fileNameWithoutExt)) {
            Notification.warning(TYPO3.lang['js.import.file.invalid.short'] || 'Error');
            return;
        }
        
        const presetKey = fileNameWithoutExt.toLowerCase().trim();
        
        if (!presetKey) {
            Notification.warning(TYPO3.lang['js.import.file.invalid.short'] || 'Error');
            return;
        }
        
        // Check if preset exists
        const checkData = new FormData();
        checkData.append('presetKey', presetKey);
        
        new AjaxRequest(TYPO3.settings.ajaxUrls['check_preset_exists'])
            .post(checkData)
            .then(async (response) => {
                const responseBody = await response.resolve();
                
                if (responseBody.exists) {
                    // Show confirmation modal
                    const updateMessage = (TYPO3.lang['js.import.update.message'] || 'A preset with the name "%s" already exists. Do you want to update it? This will replace all existing configuration and features.').replace('%s', presetKey);
                    const modal = Modal.advanced({
                        title: TYPO3.lang['js.import.update.title'] || 'Update Existing Preset',
                        content: updateMessage,
                        severity: Severity.warning,
                        buttons: [
                            {
                                text: TYPO3.lang['button.cancel'] || 'Cancel',
                                active: true,
                                btnClass: 'btn-default',
                                name: 'cancel',
                                trigger: (event, currentModal) => {
                                    currentModal.hideModal();
                                }
                            },
                            {
                                text: 'Update',
                                btnClass: 'btn-warning',
                                name: 'update',
                                trigger: (event, currentModal) => {
                                    currentModal.hideModal();
                                    proceedWithImport(form, submitButton);
                                }
                            }
                        ]
                    });
                } else {
                    // Preset doesn't exist, proceed directly
                    proceedWithImport(form, submitButton);
                }
            })
            .catch((error) => {
                console.error('Error checking preset:', error);
                // If check fails, proceed anyway
                proceedWithImport(form, submitButton);
            });
    }
}); 


// Handle file input change to enable/disable import button
document.addEventListener('change', (event) => {
    if (event.target.id === 'yaml-file') {
        const importBtn = document.getElementById('importPresetBtn');
        if (importBtn) {
            if (event.target.files && event.target.files.length > 0) {
                importBtn.removeAttribute('disabled');
            } else {
                importBtn.setAttribute('disabled', 'disabled');
            }
        }
    }
});
