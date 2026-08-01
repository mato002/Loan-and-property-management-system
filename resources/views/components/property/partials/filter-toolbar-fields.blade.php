@php
    $layout = $layout ?? 'desktop';
    $isMobile = $layout === 'mobile';
    $hasPrimary = isset($primary) && ! $primary->isEmpty();
    $hasSecondary = isset($secondary) && ! $secondary->isEmpty();
    $hasDateRange = isset($dateRange) && ! $dateRange->isEmpty();
    $hasExport = isset($export) && ! $export->isEmpty();
    $hasActions = isset($actions) && ! $actions->isEmpty();
    $hasFilterFields = $hasDateRange || $hasPrimary || $hasSecondary;
@endphp

@if ($hasFilterFields)
    @if ($isMobile)
        @if ($hasDateRange)
            <div class="min-w-0 w-full space-y-2" data-filter-date-range>
                <p class="w-full text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date range</p>
                {{ $dateRange }}
            </div>
        @endif

        @if ($hasPrimary)
            <div class="min-w-0 flex flex-col gap-2 w-full" data-filter-primary>
                {{ $primary }}
            </div>
        @endif

        @if ($hasSecondary)
            <div class="w-full min-w-0 space-y-2 pt-2 border-t border-slate-200 dark:border-slate-700" data-filter-secondary>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">More filters</p>
                <div class="flex flex-col gap-2 w-full">
                    {{ $secondary }}
                </div>
            </div>
        @endif
    @else
        <div class="flex flex-row flex-wrap items-end gap-x-2 gap-y-2 w-full min-w-0 flex-1" data-filter-main-row>
            @if ($hasDateRange)
                <div class="contents" data-filter-date-range>
                    {{ $dateRange }}
                </div>
            @endif

            @if ($hasPrimary)
                <div class="contents" data-filter-primary>
                    {{ $primary }}
                </div>
            @endif

            @if ($hasSecondary)
                <div class="contents" data-filter-secondary>
                    {{ $secondary }}
                </div>
            @endif
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
            <div data-property-export-actions>
                {{ $export }}
            </div>
        @endif
        @if ($hasActions)
            {{ $actions }}
        @endif
    </div>
@endif
