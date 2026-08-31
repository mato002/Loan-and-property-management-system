import Chart from 'chart.js/auto';

const HEAVY_METRICS_HEADER = 'X-Property-Dashboard-Metrics';
const FETCH_TIMEOUT_MS = 20000;

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

function showHeavyMetricsError(host, message) {
    if (!(host instanceof HTMLElement)) {
        return;
    }

    const text = message || 'Could not load dashboard metrics. Please try again.';
    host.innerHTML = `
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-5 text-sm text-rose-900" role="alert">
            <p class="font-semibold">Dashboard metrics could not load</p>
            <p class="mt-1 text-rose-800">${text}</p>
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-2 rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800"
                data-property-dashboard-metrics-retry
            >
                Try again
            </button>
        </div>
    `;
    host.dataset.loaded = '0';
    host.dataset.loading = '0';
}

function wireHeavyMetricsRetry(host) {
    if (!(host instanceof HTMLElement)) {
        return;
    }

    const retryBtn = host.querySelector('[data-property-dashboard-metrics-retry]');
    if (!(retryBtn instanceof HTMLButtonElement) || retryBtn.dataset.wired === '1') {
        return;
    }

    retryBtn.dataset.wired = '1';
    retryBtn.addEventListener('click', () => {
        host.dataset.loaded = '0';
        host.dataset.loading = '0';
        loadHeavyDashboardMetrics(host);
    });
}

function shouldAbortHeavyMetricsForClick(event) {
    const link = event.target?.closest?.('a[href]');
    if (!link) {
        return false;
    }

    const host = findHeavyMetricsHost();
    if (!(host instanceof HTMLElement) || host.dataset.loading !== '1') {
        return false;
    }

    let href = '';
    try {
        href = new URL(link.getAttribute('href') || '', window.location.href).pathname;
    } catch {
        return true;
    }

    if (/\/property\/dashboard(?:\/|$)/.test(href)) {
        return false;
    }

    return true;
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
    const timeoutId = window.setTimeout(() => {
        heavyMetricsAbort?.abort();
    }, FETCH_TIMEOUT_MS);

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

        const html = await response.text();
        if (heavyMetricsAbort?.signal.aborted) {
            return;
        }

        if (!response.ok) {
            if (html.trim() !== '') {
                host.innerHTML = html;
                wireHeavyMetricsRetry(host);
            } else {
                showHeavyMetricsError(host, `Server returned ${response.status}.`);
            }
            return;
        }

        if (!html.includes('property-dashboard-charts') && !html.includes('dashboard-chart-invoices')) {
            showHeavyMetricsError(host, 'Unexpected response from the server.');
            return;
        }

        host.innerHTML = html;
        host.dataset.loaded = '1';
        wireHeavyMetricsRetry(host);
        initPropertyDashboardCharts(host);
    } catch (error) {
        if (error?.name === 'AbortError') {
            if (host.isConnected && host.dataset.loaded !== '1') {
                showHeavyMetricsError(host, 'Loading timed out or was interrupted. Try again.');
            }
            return;
        }

        showHeavyMetricsError(host, error?.message || 'Network error while loading dashboard metrics.');
    } finally {
        window.clearTimeout(timeoutId);
        host.dataset.loading = '0';
        if (heavyMetricsAbort?.signal.aborted) {
            heavyMetricsAbort = null;
        }
    }
}

const PIE_COLORS = ['#4f46e5', '#0891b2', '#059669', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b', '#ec4899'];

function pieOptions(showLegend = true) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: 4 },
        plugins: {
            legend: {
                display: showLegend,
                position: 'bottom',
                labels: { boxWidth: 10, font: { size: 10 }, padding: 8 },
            },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const v = ctx.parsed ?? 0;
                        const s = Number(v).toLocaleString(undefined, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0,
                        });
                        return `${ctx.label}: ${Number.isFinite(v) && v >= 1000 ? `KES ${s}` : s}`;
                    },
                },
            },
        },
    };
}

function normalizePieSeries(labels, values) {
    const nums = (values ?? []).map((v) => Number(v) || 0);
    const total = nums.reduce((sum, v) => sum + v, 0);
    if (total <= 0) {
        return { labels: [], values: [], isEmpty: true };
    }

    return { labels: labels ?? [], values: nums, isEmpty: false };
}

function togglePieEmptyState(canvasId, isEmpty, root = document) {
    const emptyEl = root.querySelector?.(`#${CSS.escape(canvasId)}-empty`)
        ?? document.getElementById(`${canvasId}-empty`);
    const canvas = root.querySelector?.(`#${CSS.escape(canvasId)}`) ?? document.getElementById(canvasId);
    if (emptyEl instanceof HTMLElement) {
        emptyEl.classList.toggle('hidden', !isEmpty);
    }
    if (canvas instanceof HTMLCanvasElement) {
        canvas.classList.toggle('hidden', isEmpty);
    }
}

function renderPieChart(canvasId, labels, values, colors = PIE_COLORS, root = document) {
    destroyChartOnCanvas(canvasId, root);
    const canvas = root.querySelector?.(`#${CSS.escape(canvasId)}`) ?? document.getElementById(canvasId);
    if (!canvas?.getContext) {
        return null;
    }

    const series = normalizePieSeries(labels, values);
    togglePieEmptyState(canvasId, series.isEmpty, root);
    if (series.isEmpty) {
        return null;
    }

    const chart = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: {
            labels: series.labels,
            datasets: [{
                data: series.values,
                backgroundColor: colors.slice(0, series.values.length),
                borderWidth: 1,
                borderColor: '#ffffff',
            }],
        },
        options: pieOptions(true),
    });

    window.requestAnimationFrame(() => chart.resize());

    return chart;
}

function doughnutOptions() {
    return pieOptions(true);
}

function initPropertyDashboardCharts(root = document) {
    const holder = root.querySelector?.('#property-dashboard-charts') ?? document.getElementById('property-dashboard-charts');
    if (!holder) {
        return;
    }

    destroyChartOnCanvas('dashboard-chart-invoices', root);
    destroyChartOnCanvas('dashboard-chart-payments', root);
    destroyChartOnCanvas('dashboard-chart-commission-properties', root);
    destroyChartOnCanvas('dashboard-chart-commission-split', root);
    destroyChartOnCanvas('dashboard-chart-occupancy', root);
    destroyChartOnCanvas('dashboard-chart-collections-billed', root);

    let labels = [];
    let invoices = [];
    let payments = [];
    let commissionByProperty = { labels: [], values: [] };
    let commissionSplit = { labels: [], values: [] };
    let occupancy = { labels: [], values: [] };
    let collectionsBilled = { labels: [], values: [] };
    try {
        labels = JSON.parse(holder.dataset.labels || '[]');
        invoices = JSON.parse(holder.dataset.invoices || '[]');
        payments = JSON.parse(holder.dataset.payments || '[]');
        commissionByProperty = JSON.parse(holder.dataset.commissionByProperty || '{"labels":[],"values":[]}');
        commissionSplit = JSON.parse(holder.dataset.commissionSplit || '{"labels":[],"values":[]}');
        occupancy = JSON.parse(holder.dataset.occupancy || '{"labels":[],"values":[]}');
        collectionsBilled = JSON.parse(holder.dataset.collectionsBilled || '{"labels":[],"values":[]}');
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

    renderPieChart('dashboard-chart-commission-properties', commissionByProperty.labels, commissionByProperty.values, PIE_COLORS, root);
    renderPieChart('dashboard-chart-commission-split', commissionSplit.labels, commissionSplit.values, ['#4f46e5', '#7c3aed'], root);
    renderPieChart('dashboard-chart-occupancy', occupancy.labels, occupancy.values, ['#059669', '#f59e0b'], root);
    renderPieChart('dashboard-chart-collections-billed', collectionsBilled.labels, collectionsBilled.values, ['#0d9488', '#f97316'], root);
}

export function bootPropertyDashboard(scope = document) {
    const host = findHeavyMetricsHost(scope);
    if (host instanceof HTMLElement) {
        if (host.dataset.loaded !== '1' && host.dataset.loading !== '1') {
            loadHeavyDashboardMetrics(host);
        }
    }
    initPropertyDashboardCharts(scope);
}

document.addEventListener('click', (event) => {
    const retryBtn = event.target?.closest?.('[data-property-dashboard-metrics-retry]');
    if (!(retryBtn instanceof HTMLButtonElement)) {
        return;
    }

    const host = retryBtn.closest('[data-property-heavy-metrics]');
    if (host instanceof HTMLElement) {
        event.preventDefault();
        host.dataset.loaded = '0';
        host.dataset.loading = '0';
        loadHeavyDashboardMetrics(host);
    }
}, true);

document.addEventListener('DOMContentLoaded', () => {
    bootPropertyDashboard(document);
});

document.addEventListener('turbo:load', () => {
    bootPropertyDashboard(document);
});

document.addEventListener('turbo:click', (event) => {
    if (shouldAbortHeavyMetricsForClick(event)) {
        cancelHeavyDashboardMetrics();
    }
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
