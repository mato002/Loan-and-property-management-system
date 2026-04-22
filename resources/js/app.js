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

Alpine.start();
