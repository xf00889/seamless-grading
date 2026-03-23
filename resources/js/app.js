import './bootstrap';
import './modules/confirmations';
import './modules/dialogs';
import './modules/app-shell';

import Alpine from 'alpinejs';

function loadDashboardCharts() {
    if (!document.querySelector('[data-dashboard-chart]')) {
        return;
    }

    void import('./modules/dashboard-charts');
}

window.Alpine = Alpine;

Alpine.start();

loadDashboardCharts();
