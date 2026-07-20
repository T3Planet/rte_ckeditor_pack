/**
 * Keeps a single .dashboard-tab.active across TYPO3 v12–v14 tab implementations.
 */
function syncDashboardTabActiveState(dashboardTabs, activeTab) {
    if (!activeTab?.classList.contains('dashboard-tab')) {
        return;
    }

    dashboardTabs.querySelectorAll('.dashboard-tab.active').forEach((tab) => {
        if (tab !== activeTab) {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
            tab.setAttribute('tabindex', '-1');
        }
    });
}

function initDashboardTabSync() {
    const dashboardTabs = document.querySelector('.dashboard-tabs');
    if (!dashboardTabs) {
        return;
    }

    const handleTabShown = (event) => {
        syncDashboardTabActiveState(dashboardTabs, event.target);
    };

    dashboardTabs.addEventListener('typo3:tab:shown', handleTabShown);
    dashboardTabs.addEventListener('shown.bs.tab', handleTabShown);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardTabSync);
} else {
    initDashboardTabSync();
}
