@php
    $chartPropertyValues = collect($chartByProperty['values'] ?? [])->map(fn ($v) => (float) $v);
    $chartSplitValues = collect($chartCommissionSplit['values'] ?? [])->map(fn ($v) => (float) $v);
    $hasCommissionCharts = $chartPropertyValues->sum() > 0 || $chartSplitValues->sum() > 0;
@endphp

<x-property.workspace
    title="Agent earnings & commission"
    subtitle="Your management fees and what each landlord keeps — filter by month, property, or owner."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-footer-row="$tableFooterRow ?? null"
    :show-search="false"
    empty-title="No earnings for this period"
    empty-hint="Try another month or clear property/landlord filters."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.commission', get_defined_vars())
    </x-slot>

    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'financials-invoice-commission') }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >Invoice owners</a>
    </x-slot>

    @if ($hasCommissionCharts)
        <x-slot name="above">
            <details class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i>
                        Charts (optional)
                    </span>
                    <span class="text-xs font-normal text-slate-500 group-open:hidden">Show</span>
                    <span class="hidden text-xs font-normal text-slate-500 group-open:inline">Hide</span>
                </summary>
                <div class="grid gap-3 border-t border-slate-100 dark:border-slate-700 p-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700/80 p-3">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Commission by property</h3>
                        <div class="h-36 w-full" id="commission-chart-by-property" data-labels='@json($chartByProperty['labels'] ?? [])' data-values='@json($chartByProperty['values'] ?? [])'>
                            <canvas id="commission-earnings-chart-properties" aria-label="Commission by property"></canvas>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-100 dark:border-slate-700/80 p-3">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">You vs landlord net</h3>
                        <div class="h-36 w-full" id="commission-chart-split" data-labels='@json($chartCommissionSplit['labels'] ?? [])' data-values='@json($chartCommissionSplit['values'] ?? [])'>
                            <canvas id="commission-earnings-chart-split" aria-label="Commission split"></canvas>
                        </div>
                    </div>
                </div>
            </details>
        </x-slot>
    @endif
</x-property.workspace>

@if ($hasCommissionCharts)
    @push('scripts')
    <script type="module">
    import Chart from 'chart.js/auto';

    function pieChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const v = ctx.parsed ?? 0;
                            return `${ctx.label}: KES ${Number(v).toLocaleString()}`;
                        },
                    },
                },
            },
        };
    }

    function initCommissionCharts(root = document) {
        const propsHolder = root.querySelector('#commission-chart-by-property');
        const splitHolder = root.querySelector('#commission-chart-split');
        if (!propsHolder && !splitHolder) return;

        if (propsHolder) {
            const canvas = propsHolder.querySelector('canvas');
            const existing = canvas ? Chart.getChart(canvas) : null;
            if (existing) existing.destroy();
            const labels = JSON.parse(propsHolder.dataset.labels || '[]');
            const values = JSON.parse(propsHolder.dataset.values || '[]');
            if (canvas?.getContext && values.some((v) => Number(v) > 0)) {
                new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels,
                        datasets: [{ data: values, backgroundColor: ['#4f46e5', '#0891b2', '#059669', '#f59e0b', '#ef4444', '#8b5cf6'], borderWidth: 1, borderColor: '#fff' }],
                    },
                    options: pieChartOptions(),
                });
            }
        }

        if (splitHolder) {
            const canvas = splitHolder.querySelector('canvas');
            const existing = canvas ? Chart.getChart(canvas) : null;
            if (existing) existing.destroy();
            const labels = JSON.parse(splitHolder.dataset.labels || '[]');
            const values = JSON.parse(splitHolder.dataset.values || '[]');
            if (canvas?.getContext && values.some((v) => Number(v) > 0)) {
                new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels,
                        datasets: [{ data: values, backgroundColor: ['#4f46e5', '#7c3aed'], borderWidth: 1, borderColor: '#fff' }],
                    },
                    options: pieChartOptions(),
                });
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => initCommissionCharts(document));
    document.addEventListener('turbo:load', () => initCommissionCharts(document));
    document.addEventListener('turbo:frame-load', (e) => initCommissionCharts(e.target));

    document.addEventListener('toggle', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLDetailsElement) || !target.open) {
            return;
        }
        if (target.querySelector('#commission-chart-by-property, #commission-chart-split')) {
            requestAnimationFrame(() => initCommissionCharts(target));
        }
    }, true);
    </script>
    @endpush
@endif
