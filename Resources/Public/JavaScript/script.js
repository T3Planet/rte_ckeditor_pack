import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import Modal from "@typo3/backend/modal.js";
import DeferredAction from "@typo3/backend/action-button/deferred-action.js";
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
    if (event.target.classList.contains('indent-feature-toggle')) {
        let classes = document.querySelectorAll('.use-indent-classes');
        let customIndent = document.querySelectorAll('.custom-indent');

        if(classes && customIndent){
            if (event.target.checked) {
                classes.forEach(element => {
                    element.classList.add('d-none');
                });
                customIndent.forEach(element => {
                    element.classList.remove('d-none');
                });
            } else {
                classes.forEach(element => {
                    element.classList.remove('d-none');
                });
                customIndent.forEach(element => {
                    element.classList.add('d-none');
                });
            }
        }
    }

    if (event.target.classList.contains('outdent-feature-toggle')) {
        let classes = document.querySelectorAll('.use-outdent-classes');
        let customOutdent = document.querySelectorAll('.custom-outdent');

        if(classes && customOutdent){
            if (event.target.checked) {
                classes.forEach(element => {
                    element.classList.add('d-none');
                });
                customOutdent.forEach(element => {
                    element.classList.remove('d-none');
                });
            } else {
                classes.forEach(element => {
                    element.classList.remove('d-none');
                });
                customOutdent.forEach(element => {
                    element.classList.add('d-none');
                });
            }
        }
    }

    if (event.target.tagName === 'BUTTON') {
        if (event.target.classList.contains('sync-preset')) {
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
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (TYPO3.lang['js.syncing'] || 'Syncing...');

            const formData = new FormData();
            formData.append('presetUid', presetUid);

            new AjaxRequest(TYPO3.settings.ajaxUrls['sync_preset'])
                .post(formData)
                .then(async (response) => {
                    const responseBody = await response.resolve();
                    
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
                })
                .catch((error) => {
                    Notification.error(TYPO3.lang['js.sync.error'] || 'Sync Error', error.message ?? (TYPO3.lang['js.sync.error.message'] || 'Failed to sync preset'));
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                });
        }

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

        if (event.target.classList.contains('insert-section')) {
            let panelGroup = event.target.closest('.panel-group-wrap')?.querySelector('.panel-group');
            if (panelGroup) {
                let searchHitSection = panelGroup.querySelector('.origin-section');
                if (searchHitSection) {
                    const clonedSection = searchHitSection.cloneNode(true);
                    let btnGroup = clonedSection.querySelector('.panel-heading .btn-group');
                    btnGroup.classList.remove('d-none');
                    clonedSection.classList.remove('origin-section');
                    panelGroup.append(clonedSection);
                    updateAllFieldNames();
                }
            }
        }

        if (event.target.classList.contains('remove-section')) {
            const searchHitSection = event.target.closest('.searchhit');
            if (searchHitSection) {
                searchHitSection.remove();
                updateAllFieldNames();
            }
        }

        if (event.target.classList.contains('insert-inner-section')) {
            const panelChildGroup = event.target.closest('.panel-group-wrap')?.querySelector('.panel-group');
        
            if (panelChildGroup) {
                let searchHitInnerSection = panelChildGroup.querySelector('.origin-child-section');
                const parentSection = searchHitInnerSection.closest('.searchhit');
                
                if (searchHitInnerSection) {
                    const clonedSection = searchHitInnerSection.cloneNode(true);
                    let btnGroup = clonedSection.querySelector('.panel-heading .btn-group');
                    btnGroup.classList.remove('d-none');
                    clonedSection.classList.remove('origin-child-section');
                    panelChildGroup.append(clonedSection);
                    updateAllFieldNames(parentSection);
                }
            }
        }

        if (event.target.classList.contains('remove-inner-section')) {

            const searchHitInnerSection = event.target.closest('.child-section');
            const parentSection = searchHitInnerSection.closest('.searchhit');
            if (searchHitInnerSection) {
                searchHitInnerSection.remove();
                updateAllFieldNames(parentSection);
            }
        }

        if (event.target.classList.contains('insert-group')) {

            const activeItemsField = document.querySelector('input[name="activeItems"]');
            const activeItems = activeItemsField.value.split(',');
            const optionsHTML = activeItems.map(item => `<option value="${item.trim()}">${item.trim()}</option>`).join('');

            const toolBarItems = document.querySelector('input[name="toolBarItems"]');
            const toolBarItem = toolBarItems?.value.split(',');
            const toolBarOptionsHTML = toolBarItem?.map(item => `<option value="${item.trim()}">${item.trim()}</option>`).join('');

            const groupWrapper = document.getElementById('groupWrapper');
            const currentIndex = groupWrapper.children.length;
            const nextIndex = currentIndex + 1;
            const newGroupHTML = `
                 <div class="panel panel-default searchhit">
                     <div class="mb-0 d-flex" id="flush-heading${nextIndex}">
                         <a class="panel-heading flex-grow-1 d-block collapsed" type="button" data-bs-toggle="collapse" href="#flush-collapse${nextIndex}"
                             aria-expanded="false" aria-controls="flush-collapse${nextIndex}">
                             <span class="caret"></span>
                             <strong>ToolBar Group</strong>
                         </a>
                         <div class="btn-group p-1">
                             <button class="btn btn-danger delete remove-section" type="button">
                                <span class="t3js-icon icon icon-size-small icon-state-default icon-actions-delete" data-identifier="actions-delete" aria-hidden="true">
                                    <span class="icon-markup">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M7 5H6v8h1zM10 5H9v8h1z"/><path d="M13 3h-2v-.75C11 1.56 10.44 1 9.75 1h-3.5C5.56 1 5 1.56 5 2.25V3H3v10.75c0 .69.56 1.25 1.25 1.25h7.5c.69 0 1.25-.56 1.25-1.25V3zm-7-.75A.25.25 0 0 1 6.25 2h3.5a.25.25 0 0 1 .25.25V3H6v-.75zm6 11.5a.25.25 0 0 1-.25.25h-7.5a.25.25 0 0 1-.25-.25V4h8v9.75z"/><path d="M13.5 4h-11a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1z"/></g></svg>
                                    </span>
                                </span>
                             </button>
                         </div>
                     </div>
                     <div id="flush-collapse${nextIndex}" class="panel-collapse search-item collapse show" aria-labelledby="flush-heading${nextIndex}">
                         <div class="form-section">
                            <div class="form-group">
                                <label for="label${nextIndex}" class="form-label">Label</label>
                                <input type="text" id="label${nextIndex}" class="form-control" name="group[${currentIndex}][label]" required>
                            </div>
                            <div class="form-group">
                                <label for="tooltip${nextIndex}" class="form-label">ToolTip</label>
                                <input type="text" id="tooltip${nextIndex}" class="form-control" name="group[${currentIndex}][tooltip]">
                            </div>
                            <div class="form-group">
                                <label for="icon${nextIndex}" class="form-label">Icon</label>
                                <select id="icon${nextIndex}" name="group[${currentIndex}][icon]" class="form-control form-select icon-selector" data-id="customIconDiv${nextIndex}">
                                    ${toolBarOptionsHTML}
                                </select>
                            </div>
                            <div class="form-group d-none" id="customIconDiv${nextIndex}">
                                <label for="customIcon${nextIndex}" class="form-label">Custom Icon</label>
                                <textarea rows="4" id="customIcon${nextIndex}" class="form-control" name="group[${currentIndex}][customIcon]"></textarea>  
                            </div>
                            <div class="form-group">
                            <label for="items${nextIndex}" class="form-label">Items</label>
                            <select name="group[${currentIndex}][items][]" class="form-control form-select" multiple>
                                ${optionsHTML}
                            </select>
                        </div>
                         </div>
                     </div>
                 </div>
             `;

            groupWrapper.insertAdjacentHTML('beforeend', newGroupHTML);
        }
    }

     if (event.target.classList.contains('reset-preset')) {
            event.preventDefault();
            const button = event.target;
            const presetUid = button.getAttribute('data-preset-uid');
            
            if (!presetUid) {
                Notification.error(TYPO3.lang['js.error'] || 'Error', TYPO3.lang['js.error.preset_uid_missing'] || 'Preset UID is missing');
                return;
            }

            if (button.disabled) {
                return;
            }
            Modal.confirm(
                'Reset Configuration',
                'Are you sure you want to reset the configuration of selected preset?',
                Severity.warning,
                [
                    {
                        text: TYPO3.lang['LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:buttons.confirm.delete_record.no'] || 'Cancel',
                        active: true,
                        btnClass: 'btn-default',
                        trigger: () => {
                            Modal.dismiss();
                        }
                    },
                    {
                        text: TYPO3.lang['LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:buttons.confirm.delete_record.yes'] || 'Yes, reset it',
                        btnClass: 'btn-warning',
                        action: new DeferredAction(() => {
                            const formData = new FormData();
                            formData.append('presetUid', presetUid);

                            return new AjaxRequest(TYPO3.settings.ajaxUrls['reset_preset'])
                                .post(formData)
                                .then(async (response) => {
                                    const responseBody = await response.resolve();
                                    
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
                                })
                                .catch((error) => {
                                    Notification.error('Reset Error', error.message ?? 'Failed to reset preset');
                                });
                        })
                    }
                ]
            );
        }

    if (event.target.classList.contains('feature-configuration')) {
        const submitButton = event.target;
        const form = submitButton.closest('form');

        if (!form) {
            return;
        }

        event.preventDefault();

        const inputs = form.querySelectorAll('input');
        if (inputs) {
            inputs.forEach(input => {
                if (input.value.trim() === '' && input.hasAttribute('data-default')) {
                    input.value = input.getAttribute('data-default');
                }
            });
        }

        const formData = new FormData(form);
        submitButton.disabled = true;
        submitButton.classList.add('is-loading');

        new AjaxRequest(TYPO3.settings.ajaxUrls['save_feature_configuration'])
            .post(formData)
            .then(async (response) => {
                const responseBody = await response.resolve();
                
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
            })
            .catch((error) => {
                Notification.error('Configuration', error.message ?? error);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.classList.remove('is-loading');
            });
    }

    function updateAllFieldNames(parentSection) {

        let prefix = 'flush';
        var allSections = document.querySelectorAll('.searchhit');

        if (parentSection) {
            allSections = parentSection.querySelectorAll('.child-section');
            prefix = 'inner';
        }

        if (allSections) {
            allSections.forEach((section, index) => {

                const allHeadings = section.querySelectorAll(`[id^="${prefix}-heading"]`);
                const allCollapses = section.querySelectorAll(`[id^="${prefix}-collapse"]`);

                const allInputFields = section.querySelectorAll('input, select, textarea'); // Update field names


                allHeadings.forEach((heading, headingIndex) => {
                    const newId = `${prefix}-heading-${index}-${headingIndex}`;
                    heading.id = newId;
                    heading.querySelector('a').setAttribute('href', `#${prefix}-collapse-${index}-${headingIndex}`);
                    heading.querySelector('a').setAttribute('aria-controls', `${prefix}-collapse-${index}-${headingIndex}`);
                });


                allCollapses.forEach((collapse, collapseIndex) => {
                    collapse.id = `${prefix}-collapse-${index}-${collapseIndex}`;
                    collapse.setAttribute('aria-labelledby', `${prefix}-heading-${index}-${collapseIndex}`);
                });

                allInputFields.forEach((field) => {
                    let currentName = field.name;
                    if (currentName) {
                        if (prefix == 'inner') {
                            let newName = currentName.replace(/(\[\d+\])(?!.*\[\d+\])/, `[${index}]`);
                            field.name = newName;
                        } else {
                            let newName = currentName.replace(/\[\d+\]/, `[${index}]`);
                            field.name = newName;
                        }

                    }
                });

            });
        }

    }

   if (event.target.id === 'newPresetBtn' || event.target.closest('#newPresetBtn')) {
        event.preventDefault();
        const submitButton = event.target.id === 'newPresetBtn' ? event.target : event.target.closest('#newPresetBtn');
        const form = submitButton.closest('form');
        if (!form || !submitButton || submitButton.disabled) {
            return;
        }
        const presetNameInput = form.querySelector('#preset-name');
        const ajaxUrl = form.getAttribute('data-ajax-url');
        if (!ajaxUrl) {
            Notification.error('Configuration Error', 'AJAX URL for new_preset is not configured');
            return;
        }
        
        const formData = new FormData(form);
        submitButton.disabled = true;
        submitButton.classList.add('is-loading');
        new AjaxRequest(ajaxUrl)
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
                    
                    presetNameInput.value = '';
                    submitButton.setAttribute('disabled', 'disabled');
                    top.window.location.reload();
                }
            })
            .catch((error) => {
                console.error('AJAX Error:', error);
                Notification.error('Error', error.message ?? 'An error occurred while creating the preset');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.classList.remove('is-loading');
            });
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
            Notification.error(TYPO3.lang['js.error'] || 'Error', TYPO3.lang['js.import.file.invalid.short'] || 'Invalid filename!');
            return;
        }
        
        const presetKey = fileNameWithoutExt.toLowerCase().trim();
        
        if (!presetKey) {
            Notification.error(TYPO3.lang['js.error'] || 'Error', TYPO3.lang['js.import.file.invalid.short'] || 'Invalid filename!');
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

const newPresetBtn = document.getElementById('newPresetBtn');

document.addEventListener('input', (event) => {
    if (event.target.classList.contains('icon-selector')) {
        let customIconSelector = document.getElementById(event.target.getAttribute('data-id'))
        if (event.target.value === 'other') {
            customIconSelector.classList.remove('d-none');
        } else {
            customIconSelector.classList.add('d-none');
        }
    }

    if (event.target.id === 'preset-name'){
        let presetName = event.target.value.trim();
        const regex = /^[A-Za-z_]{1,20}$/;

        if (presetName !== '' && regex.test(presetName)) {
            newPresetBtn.removeAttribute('disabled');
        } else {
            newPresetBtn.setAttribute('disabled', 'disabled');
        }
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



let indentType = document.getElementById('indentType');

if(indentType){

    let classes = document.querySelectorAll('.use-indent-classes');
    let customIndent = document.querySelectorAll('.custom-indent');

    if(indentType.checked){
        classes.forEach(element => {
            element.classList.add('d-none');
        });
        customIndent.forEach(element => {
            element.classList.remove('d-none');
        });
    }  else {
        classes.forEach(element => {
            element.classList.remove('d-none');
        });
        customIndent.forEach(element => {
            element.classList.add('d-none');
        });
    }
}

let outdentType = document.getElementById('outdentType');

if (outdentType) {
    let classes = document.querySelectorAll('.use-outdent-classes');
    let customOutdent = document.querySelectorAll('.custom-outdent');

    if(classes && customOutdent){
        if (outdentType.checked) {
            classes.forEach(element => {
                element.classList.add('d-none');
            });
            customOutdent.forEach(element => {
                element.classList.remove('d-none');
            });
        } else {
            classes.forEach(element => {
                element.classList.remove('d-none');
            });
            customOutdent.forEach(element => {
                element.classList.add('d-none');
            });
        }
    }
}
