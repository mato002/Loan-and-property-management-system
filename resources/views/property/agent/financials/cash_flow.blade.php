@php
    $dual = $cashDual ?? [];
@endphp

<x-property.workspace
    title="Cash flow"
    subtitle="Completed tenant payments vs maintenance outflows — illustrative operating cash picture."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No cash movements"
    empty-hint="Completed payments appear as inflows; completed maintenance jobs with quotes count as outflows."
>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.financials.cash_flow') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.financials.cash_flow', array_merge(request()->query(), ['export' => 'csv']), false),
                'pdfUrl' => route('property.financials.cash_flow', array_merge(request()->query(), ['export' => 'pdf']), false),
                'class' => 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
            ])
        </form>
    </x-slot>
    <x-property.chart-line-dual
        title="Monthly cash in vs maintenance out ({{ $periodLabel ?? now()->format('M Y') }})"
        label-a="Collections"
        label-b="Maint. (completed)"
        :series="$dual"
    />
</x-property.workspace>
