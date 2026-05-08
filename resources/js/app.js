import './bootstrap';
import './swal-init';
import './superadmin-auto-filter';
import './property-portal-ui';
import './property-portal-turbo';
import './property-dashboard';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

window.Alpine = Alpine;
Chart.register(...registerables);
window.Chart = Chart;

/** Sidebar accordion groups: survives Turbo navigations (sessionStorage). */
function persistedSidebarAccordion(storageKey) {
    return (groupName, routeDefaultOpen) => ({
        open: Boolean(routeDefaultOpen),
        init() {
            try {
                const raw = sessionStorage.getItem(storageKey);
                const map = raw ? JSON.parse(raw) : {};
                if (Object.prototype.hasOwnProperty.call(map, groupName)) {
                    this.open = Boolean(map[groupName]);
                }
            } catch {
                // ignore corrupt storage
            }
        },
        toggleGroup() {
            this.open = !this.open;
            try {
                const raw = sessionStorage.getItem(storageKey);
                const map = raw ? JSON.parse(raw) : {};
                map[groupName] = this.open;
                sessionStorage.setItem(storageKey, JSON.stringify(map));
            } catch {
                // ignore quota / private mode
            }
        },
    });
}

Alpine.data('loanSidebarGroup', persistedSidebarAccordion('loan.sidebar.openGroups'));
Alpine.data('propertySidebarGroup', persistedSidebarAccordion('property.sidebar.openGroups'));

Alpine.start();
