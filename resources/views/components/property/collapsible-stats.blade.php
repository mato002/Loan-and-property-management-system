@props([
    'storageKey' => 'property.workspace.summaryStatsVisible',
])

    <div {{ $attributes->merge(['class' => 'print-hide property-workspace-stats w-full min-w-0']) }}
    data-property-workspace-stats
    data-storage-key="{{ $storageKey }}"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div data-property-workspace-stats-body class="min-w-0 flex-1">
            {{ $slot }}
        </div>
        <button
            type="button"
            data-property-workspace-stats-toggle
            class="shrink-0 inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-expanded="true"
            title="Hide or show summary"
        >
            <i class="fa-solid fa-chart-simple text-[9px]" aria-hidden="true"></i>
            <span data-property-workspace-stats-toggle-label>Hide</span>
        </button>
    </div>
</div>
