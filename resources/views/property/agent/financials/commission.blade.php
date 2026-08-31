<x-property.workspace
    title="Agent earnings & commission"
    subtitle="Your management fees and what each landlord keeps — filter by month, property, or owner."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No earnings for this period"
    empty-hint="Try another month or clear property/landlord filters."
>
    <x-slot name="actions">
        <form method="get" action="{{ route('property.financials.commission') }}" class="flex flex-col xl:flex-row flex-wrap gap-2 w-full xl:w-auto">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search owner or property…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 text-sm px-3 py-2 min-w-0 w-full sm:w-48" />
            <select name="landlord_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-44">
                <option value="0">Landlord: All</option>
                @foreach ($landlords as $landlord)
                    <option value="{{ $landlord->id }}" @selected((int) ($filters['landlord_id'] ?? 0) === (int) $landlord->id)>{{ $landlord->name }}</option>
                @endforeach
            </select>
            <select name="property_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-44">
                <option value="0">Property: All</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}" @selected((int) ($filters['property_id'] ?? 0) === (int) $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" title="Financial year when month is empty" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
            <a href="{{ route('property.financials.commission', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 text-center">Export CSV</a>
        </form>
        <a
            href="{{ route('property.workspace.form.show', 'financials-invoice-commission') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Invoice owners</a>
    </x-slot>

    <x-slot name="above">
        <div class="grid gap-3 sm:gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Commission by property</h3>
                <div class="h-52 w-full" id="commission-chart-by-property" data-labels='@json($chartByProperty['labels'] ?? [])' data-values='@json($chartByProperty['values'] ?? [])'>
                    <canvas id="commission-earnings-chart-properties" aria-label="Commission by property"></canvas>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">You vs landlord net</h3>
                <div class="h-52 w-full" id="commission-chart-split" data-labels='@json($chartCommissionSplit['labels'] ?? [])' data-values='@json($chartCommissionSplit['values'] ?? [])'>
                    <canvas id="commission-earnings-chart-split" aria-label="Commission split"></canvas>
                </div>
            </div>
        </div>
    </x-slot>
</x-property.workspace>

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
</script>
@endpush
