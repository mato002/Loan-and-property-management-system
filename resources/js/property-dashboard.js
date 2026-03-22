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

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('property-dashboard-charts');
    if (!root) return;

    let labels = [];
    let invoices = [];
    let payments = [];
    try {
        labels = JSON.parse(root.dataset.labels || '[]');
        invoices = JSON.parse(root.dataset.invoices || '[]');
        payments = JSON.parse(root.dataset.payments || '[]');
    } catch {
        return;
    }

    const invCanvas = document.getElementById('dashboard-chart-invoices');
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

    const payCanvas = document.getElementById('dashboard-chart-payments');
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
});
