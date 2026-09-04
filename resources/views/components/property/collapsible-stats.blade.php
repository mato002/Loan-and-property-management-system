@props([
    'storageKey' => 'property.workspace.summaryStatsVisible',
])

<div
    {{ $attributes->merge(['class' => 'print-hide property-workspace-stats w-full min-w-0']) }}
    data-property-workspace-stats
    data-storage-key="{{ $storageKey }}"
>
    <div class="flex items-center justify-end mb-1.5">
        <button
            type="button"
            data-property-workspace-stats-toggle
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-expanded="true"
            title="Hide or show summary cards"
        >
            <i class="fa-solid fa-chart-simple text-[10px]" aria-hidden="true"></i>
            <span data-property-workspace-stats-toggle-label>Hide summary</span>
        </button>
    </div>
    <div data-property-workspace-stats-body>
        {{ $slot }}
    </div>
</div>
