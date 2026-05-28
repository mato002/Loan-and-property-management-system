import Chart from 'chart.js/auto';

function destroyChart(canvasId, root = document) {
    const canvas = root.querySelector?.(`#${CSS.escape(canvasId)}`) ?? document.getElementById(canvasId);
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
}

function baseLineOptions(yLabel = 'm³') {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: yLabel, font: { size: 10 } },
            },
        },
    };
}

function initUtilityAnalyticsCharts(root = document) {
    const holder = root.querySelector?.('#utility-analytics-charts') ?? document.getElementById('utility-analytics-charts');
    if (!holder) return;

    let payload = {};
    try {
        payload = JSON.parse(holder.dataset.payload || '{}');
    } catch {
        return;
    }

    const monthly = payload.monthly_trends || [];
    const monthLabels = monthly.map((r) => r.month);
    const monthUnits = monthly.map((r) => r.total_units);
    const monthAmounts = monthly.map((r) => r.total_amount);

    destroyChart('utility-chart-monthly', root);
    const monthlyCanvas = root.querySelector?.('#utility-chart-monthly');
    if (monthlyCanvas?.getContext) {
        new Chart(monthlyCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Total usage (m³)',
                        data: monthUnits,
                        borderColor: '#0d9488',
                        backgroundColor: '#0d94882a',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: 'Billed (KES)',
                        data: monthAmounts,
                        borderColor: '#6366f1',
                        backgroundColor: '#6366f12a',
                        fill: false,
                        tension: 0.35,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                ...baseLineOptions(),
                scales: {
                    y: { beginAtZero: true, position: 'left' },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });
    }

    const seasonal = payload.seasonal || { labels: [], values: [] };
    destroyChart('utility-chart-seasonal', root);
    const seasonalCanvas = root.querySelector?.('#utility-chart-seasonal');
    if (seasonalCanvas?.getContext) {
        new Chart(seasonalCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: seasonal.labels || [],
                datasets: [{
                    label: 'Avg usage by calendar month',
                    data: seasonal.values || [],
                    backgroundColor: '#14b8a6aa',
                    borderColor: '#0f766e',
                    borderWidth: 1,
                }],
            },
            options: baseLineOptions(),
        });
    }

    const propertyTrends = payload.property_trends || [];
    destroyChart('utility-chart-properties', root);
    const propCanvas = root.querySelector?.('#utility-chart-properties');
    if (propCanvas?.getContext) {
        new Chart(propCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: propertyTrends.map((r) => r.property),
                datasets: [{
                    label: 'Total usage (m³)',
                    data: propertyTrends.map((r) => r.total_units),
                    backgroundColor: '#0891b2aa',
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } },
            },
        });
    }

    const unitTypes = payload.unit_type_trends || [];
    destroyChart('utility-chart-unit-types', root);
    const typeCanvas = root.querySelector?.('#utility-chart-unit-types');
    if (typeCanvas?.getContext) {
        new Chart(typeCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: unitTypes.map((r) => r.label),
                datasets: [{
                    data: unitTypes.map((r) => r.total_units),
                    backgroundColor: ['#0d9488', '#0891b2', '#6366f1', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
            },
        });
    }

    const comparison = payload.comparison || {};
    const anomalyCounts = comparison.anomaly_counts || {};
    destroyChart('utility-chart-anomalies', root);
    const anomalyCanvas = root.querySelector?.('#utility-chart-anomalies');
    if (anomalyCanvas?.getContext) {
        const labels = Object.keys(anomalyCounts).map((k) => k.replace(/_/g, ' '));
        new Chart(anomalyCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Anomaly count',
                    data: Object.values(anomalyCounts),
                    backgroundColor: '#f59e0baa',
                    borderColor: '#d97706',
                    borderWidth: 1,
                }],
            },
            options: baseLineOptions('Count'),
        });
    }
}

document.addEventListener('DOMContentLoaded', () => initUtilityAnalyticsCharts(document));
document.addEventListener('turbo:frame-load', (e) => {
    if (e.target.id === 'property-main') {
        initUtilityAnalyticsCharts(e.target);
    }
});
