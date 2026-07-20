/**
 * Bootstrap Tab handler for TYPO3 v12/v13 (v14 uses @typo3/backend/tab.js).
 */
import { Tab } from 'bootstrap';
import DocumentService from '@typo3/core/document-service.js';

DocumentService.ready().then(() => {
    const dashboardTabs = document.querySelector('.dashboard-tabs');
    if (!dashboardTabs) {
        return;
    }

    dashboardTabs.querySelectorAll('[data-bs-toggle="tab"]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            Tab.getOrCreateInstance(trigger).show();
        });
    });
});
