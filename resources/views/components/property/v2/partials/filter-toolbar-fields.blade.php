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
        <div
            class="flex flex-row flex-wrap items-end gap-2 w-full min-w-0 shrink-0"
            data-filter-secondary
        >
            {{ $secondary }}
        </div>
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
