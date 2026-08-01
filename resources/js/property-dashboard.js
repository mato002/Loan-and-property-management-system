import Chart from 'chart.js/auto';

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

function findHeavyMetricsFrame(scope = document) {
    const root = scope instanceof Document ? scope : scope;
    return root.querySelector?.('#property-dashboard-heavy[data-property-heavy-metrics]')
        ?? document.getElementById('property-dashboard-heavy');
}

/**
 * Nested turbo-frames inside #property-main do not always fetch src after a frame swap.
 * Force a reload so only the heavy section loads asynchronously.
 */
function bootHeavyDashboardMetrics(scope = document) {
    const frame = findHeavyMetricsFrame(scope);
    if (!(frame instanceof HTMLElement)) {
        return;
    }

    const src = frame.getAttribute('src');
    if (!src || frame.hasAttribute('complete')) {
        return;
    }

    if (typeof frame.reload === 'function') {
        frame.reload();
        return;
    }

    frame.setAttribute('src', src);
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
    bootHeavyDashboardMetrics(scope);
    initPropertyDashboardCharts(scope);
}

document.addEventListener('DOMContentLoaded', () => {
    bootPropertyDashboard(document);
});

document.addEventListener('turbo:frame-load', (e) => {
    if (!(e.target instanceof HTMLElement)) {
        return;
    }

    if (e.target.id === 'property-main') {
        bootPropertyDashboard(e.target);
        return;
    }

    if (e.target.id === 'property-dashboard-heavy') {
        initPropertyDashboardCharts(e.target);
    }
});

document.addEventListener('turbo:load', () => {
    bootPropertyDashboard(document);
});
