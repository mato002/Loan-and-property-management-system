import './bootstrap';
import './swal-init';
import './superadmin-auto-filter';
import './property-auto-filter';
import './property-flash-lifecycle';
import './property-portal-ui';
import './property-page-modals';
import './property-modal-manager';
import './property-form-modal';
import './property-lease-row-actions';
import './property-dropdown-cleanup';
import './property-export-dropdowns';
import './property-workspace-ui';
import './property-workspace-tabs';
import './property-bulk-actions';
import './lease-form-modals';
import './property-portal-turbo';
import './loan-portal-turbo';
import './staff-module-switch';
import './property-quick-create-select';
import './property-invoice-create-sync';
import './property-payment-reversal';
import './property-dashboard';
import './utility-analytics';
import './utility-operations';
import './utility-revenue-page-modals';
import { registerLoanWorkspaceAlpine } from './loan-workspace';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';
import { propertyModalState } from './property-modal-manager';

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
Alpine.data('propertyModalState', propertyModalState);
registerLoanWorkspaceAlpine(Alpine);

Alpine.start();
