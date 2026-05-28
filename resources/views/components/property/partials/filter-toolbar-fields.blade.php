@php
    $layout = $layout ?? 'desktop';
    $isMobile = $layout === 'mobile';
    $hasPrimary = isset($primary) && ! $primary->isEmpty();
    $hasSecondary = isset($secondary) && ! $secondary->isEmpty();
    $hasDateRange = isset($dateRange) && ! $dateRange->isEmpty();
    $hasExport = isset($export) && ! $export->isEmpty();
    $hasActions = isset($actions) && ! $actions->isEmpty();
@endphp

@if ($hasDateRange)
    <div
        @class([
            'min-w-0',
            'w-full space-y-2' => $isMobile,
            'flex flex-row flex-wrap items-end gap-2 shrink-0' => ! $isMobile,
        ])
        data-filter-date-range
    >
        @if ($isMobile)
            <p class="w-full text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date range</p>
        @endif
        {{ $dateRange }}
    </div>
@endif

@if ($hasPrimary)
    <div
        @class([
            'min-w-0',
            'flex flex-col gap-2 w-full' => $isMobile,
            'flex flex-row flex-wrap items-end gap-2 flex-1' => ! $isMobile,
        ])
        data-filter-primary
    >
        {{ $primary }}
    </div>
@endif

@if ($hasSecondary)
    @if ($isMobile)
        <div class="w-full min-w-0 space-y-2 pt-2 border-t border-slate-200 dark:border-slate-700" data-filter-secondary>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">More filters</p>
            <div class="flex flex-col gap-2 w-full">
                {{ $secondary }}
            </div>
        </div>
    @else
        <details class="property-filter-toolbar__more relative shrink-0">
            <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 min-h-[38px]">
                <span>More filters</span>
                <i class="fa-solid fa-chevron-down text-xs text-slate-400" aria-hidden="true"></i>
            </summary>
            <div class="absolute left-0 top-full z-30 mt-1 min-w-[min(100%,22rem)] max-w-xl rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 p-3 shadow-lg">
                <div class="flex flex-wrap items-end gap-2">
                    {{ $secondary }}
                </div>
            </div>
        </details>
    @endif
@endif

@if ($hasExport || $hasActions)
    <div
        @class([
            'flex gap-2 min-w-0',
            'flex-col w-full' => $isMobile,
            'flex-row flex-wrap items-center shrink-0 md:ml-auto' => ! $isMobile,
        ])
        data-filter-actions
    >
        @if ($hasExport)
            {{ $export }}
        @endif
        @if ($hasActions)
            {{ $actions }}
        @endif
    </div>
@endif
