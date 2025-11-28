import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

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
                    // Get preset name before clearing
                    const presetName = presetNameInput.value.trim();
                    
                    // Store preset name in localStorage
                    if (presetName) {
                        localStorage.setItem('activePreset', presetName);
                    }
                    
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
