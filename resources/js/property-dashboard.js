import Chart from 'chart.js/auto';

const HEAVY_METRICS_HEADER = 'X-Property-Dashboard-Metrics';

let heavyMetricsAbort = null;

function lineDataset(label, data, color) {
    return {
        label,
        data,
        fill: true,
        borderColor: color,
        backgroundColor: `${color}2a`,
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
    };
}

function baseOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const v = ctx.parsed.y ?? 0;
                        const s = Number(v).toLocaleString(undefined, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        });
                        return `${ctx.dataset.label}: KES ${s}`;
                    },
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback(value) {
                        const n = Number(value);
                        if (n >= 1e6) return `${(n / 1e6).toFixed(1)}M`;
                        if (n >= 1000) return `${(n / 1000).toFixed(0)}k`;
                        return n;
                    },
                },
            },
        },
    };
}

function destroyChartOnCanvas(canvasId, root = document) {
    const canvas = root.querySelector?.(`#${CSS.escape(canvasId)}`) ?? document.getElementById(canvasId);
    if (!canvas) {
        return;
    }
    const existing = Chart.getChart(canvas);
    if (existing) {
        existing.destroy();
    }
}

function findHeavyMetricsHost(scope = document) {
    const root = scope instanceof Document ? scope : scope;
    return root.querySelector?.('[data-property-heavy-metrics]')
        ?? document.querySelector('[data-property-heavy-metrics]');
}

function cancelHeavyDashboardMetrics() {
    heavyMetricsAbort?.abort();
    heavyMetricsAbort = null;
}

/**
 * Fetch heavy dashboard HTML outside Turbo so navigation is never blocked.
 */
async function loadHeavyDashboardMetrics(host) {
    if (!(host instanceof HTMLElement)) {
        return;
    }

    const url = host.dataset.metricsUrl;
    if (!url || host.dataset.loaded === '1' || host.dataset.loading === '1') {
        return;
    }

    host.dataset.loading = '1';
    cancelHeavyDashboardMetrics();
    heavyMetricsAbort = new AbortController();

    try {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            signal: heavyMetricsAbort.signal,
            headers: {
                [HEAVY_METRICS_HEADER]: '1',
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Heavy metrics failed (${response.status})`);
        }

        const html = await response.text();
        if (heavyMetricsAbort?.signal.aborted) {
            return;
        }

        host.innerHTML = html;
        host.dataset.loaded = '1';
        initPropertyDashboardCharts(host);
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }
        host.dataset.loaded = '0';
    } finally {
        host.dataset.loading = '0';
        if (heavyMetricsAbort?.signal.aborted) {
            heavyMetricsAbort = null;
        }
    }
}

function initPropertyDashboardCharts(root = document) {
    const holder = root.querySelector?.('#property-dashboard-charts') ?? document.getElementById('property-dashboard-charts');
    if (!holder) {
        return;
    }

    destroyChartOnCanvas('dashboard-chart-invoices', root);
    destroyChartOnCanvas('dashboard-chart-payments', root);

    let labels = [];
    let invoices = [];
    let payments = [];
    try {
        labels = JSON.parse(holder.dataset.labels || '[]');
        invoices = JSON.parse(holder.dataset.invoices || '[]');
        payments = JSON.parse(holder.dataset.payments || '[]');
    } catch {
        return;
    }

    const invCanvas = root.querySelector?.('#dashboard-chart-invoices') ?? document.getElementById('dashboard-chart-invoices');
    if (invCanvas?.getContext) {
        new Chart(invCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [lineDataset('Invoices issued', invoices, '#059669')],
            },
            options: baseOptions(),
        });
    }

    const payCanvas = root.querySelector?.('#dashboard-chart-payments') ?? document.getElementById('dashboard-chart-payments');
    if (payCanvas?.getContext) {
        new Chart(payCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [lineDataset('Payments received', payments, '#0d9488')],
            },
            options: baseOptions(),
        });
    }
}

function bootPropertyDashboard(scope = document) {
    const host = findHeavyMetricsHost(scope);
    if (host instanceof HTMLElement) {
        loadHeavyDashboardMetrics(host);
    }
    initPropertyDashboardCharts(scope);
}

document.addEventListener('DOMContentLoaded', () => {
    bootPropertyDashboard(document);
});

document.addEventListener('turbo:load', () => {
    bootPropertyDashboard(document);
});

document.addEventListener('turbo:click', () => {
    cancelHeavyDashboardMetrics();
}, true);

document.addEventListener('turbo:before-visit', () => {
    cancelHeavyDashboardMetrics();
});

document.addEventListener('turbo:frame-load', (e) => {
    if (!(e.target instanceof HTMLElement)) {
        return;
    }

    if (e.target.id === 'property-main') {
        bootPropertyDashboard(e.target);
    }
});
