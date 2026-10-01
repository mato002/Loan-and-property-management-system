@props([
    'storageKey' => 'property.workspace.summaryStatsVisible',
    'defaultVisible' => true,
])

    <div {{ $attributes->merge(['class' => 'print-hide property-workspace-stats w-full min-w-0']) }}
    data-property-workspace-stats
    data-storage-key="{{ $storageKey }}"
    data-default-visible="{{ $defaultVisible ? '1' : '0' }}"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div data-property-workspace-stats-body @class(['min-w-0 flex-1', 'hidden' => ! $defaultVisible])>
            {{ $slot }}
        </div>
        <button
            type="button"
            data-property-workspace-stats-toggle
            class="shrink-0 inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-expanded="{{ $defaultVisible ? 'true' : 'false' }}"
            title="Hide or show summary"
        >
            <i class="fa-solid fa-chevron-down text-[9px]" aria-hidden="true"></i>
            <span data-property-workspace-stats-toggle-label>{{ $defaultVisible ? 'Hide' : 'Show' }}</span>
        </button>
    </div>
</div>
