@props([])

@php
    $formId = $formId();
    $chips = $activeChips();
    $hasPrimary = isset($primary) && ! $primary->isEmpty();
    $hasSecondary = isset($secondary) && ! $secondary->isEmpty();
    $hasDateRange = isset($dateRange) && ! $dateRange->isEmpty();
    $hasExport = isset($export) && ! $export->isEmpty();
    $hasActions = isset($actions) && ! $actions->isEmpty();
    $hasBulk = isset($bulk) && ! $bulk->isEmpty();
    $hasFields = $hasPrimary || $hasSecondary || $hasDateRange || $hasExport || $hasActions;
    $toolbarViewport = $__propertyToolbarViewport ?? 'all';
    $showDesktopToolbar = $toolbarViewport === 'all' || $toolbarViewport === 'desktop';
    $showMobileToolbar = $toolbarViewport === 'all' || $toolbarViewport === 'mobile';
@endphp

@php
    $filterCascadeCatalogJson = (string) ($attributes->get('data-filter-cascade-catalog') ?? '');
    $filterToolbarAttributes = $attributes->except('data-filter-cascade-catalog');
@endphp

<div
    data-property-filter-toolbar
    {{ $filterToolbarAttributes->merge(['class' => 'property-filter-toolbar property-workspace-toolbar-host w-full min-w-0 space-y-2']) }}
>
    @if (filled($attributes->get('data-filter-cascade')) && $filterCascadeCatalogJson !== '')
        <script type="application/json" data-filter-cascade-catalog-json>{!! $filterCascadeCatalogJson !!}</script>
    @endif
    @if ($showDesktopToolbar && $submitFilters && $action)
        <form
            id="{{ $formId }}"
            method="{{ $method }}"
            action="{{ $action }}"
            @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
            @if ($revenueDateFilter) data-revenue-date-filter="{{ $revenueDateFilter }}" @endif
            class="property-filter-toolbar__form hidden md:flex flex-row flex-wrap items-end gap-x-2 gap-y-2 w-full min-w-0"
            data-property-filter-form-desktop
        >
            @include('components.property.partials.filter-toolbar-fields', ['layout' => 'desktop', 'fieldFormId' => $formId])
            <button type="submit" class="inline-flex min-h-[38px] items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 shrink-0">Apply</button>
            @if ($resetUrl)
                <a
                    href="{{ $resetUrl }}"
                    @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                    class="inline-flex min-h-[38px] items-center rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 shrink-0"
                >Reset</a>
            @endif
        </form>
    @elseif ($showDesktopToolbar && $hasFields)
        <div class="property-filter-toolbar__static hidden md:flex flex-row flex-wrap items-end gap-x-2 gap-y-2 w-full min-w-0">
            @include('components.property.partials.filter-toolbar-fields', ['layout' => 'desktop'])
            @if ($hasBulk)
                <div class="flex flex-wrap items-center gap-2 md:ml-auto" data-filter-bulk>
                    {{ $bulk }}
                </div>
            @endif
        </div>
    @endif

    @if ($showMobileToolbar)
        @include('components.property.partials.filter-toolbar-mobile-panel', [
            'formId' => $formId,
            'hasFields' => $hasFields,
            'submitFilters' => $submitFilters,
            'hasPrimary' => $hasPrimary,
            'action' => $action,
            'method' => $method,
            'turboFrame' => $turboFrame,
            'revenueDateFilter' => $revenueDateFilter,
            'resetUrl' => $resetUrl,
        ])
    @endif

    @if ($hasBulk && $submitFilters && $showMobileToolbar)
        <div class="md:hidden w-full min-w-0 pt-1" data-filter-bulk-mobile>
            {{ $bulk }}
        </div>
    @endif

    @if ($showDesktopToolbar && $chips->isNotEmpty())
        <div class="property-filter-toolbar__chips flex flex-wrap items-center gap-1.5" data-property-filter-chips>
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 shrink-0">Active</span>
            @foreach ($chips as $chip)
                <a
                    href="{{ $chip['removeUrl'] }}"
                    @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                    class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100"
                    title="Remove {{ $chip['label'] }} filter"
                >
                    <span class="text-emerald-700/80 dark:text-emerald-300/90">{{ $chip['label'] }}:</span>
                    <span>{{ Str::limit($chip['value'], 32) }}</span>
                    <span class="text-emerald-600/70" aria-hidden="true">×</span>
                </a>
            @endforeach
            @if ($resetUrl)
                <a
                    href="{{ $resetUrl }}"
                    @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                    class="text-xs font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300"
                >Clear all</a>
            @endif
        </div>
    @endif
</div>
