@props([])

@php
    $formId = $formId();
    $chips = $activeChips();
    $activeCount = $activeFilterCount();
    $hasPrimary = isset($primary) && ! $primary->isEmpty();
    $hasSecondary = isset($secondary) && ! $secondary->isEmpty();
    $hasDateRange = isset($dateRange) && ! $dateRange->isEmpty();
    $hasExport = isset($export) && ! $export->isEmpty();
    $hasActions = isset($actions) && ! $actions->isEmpty();
    $hasBulk = isset($bulk) && ! $bulk->isEmpty();
    $hasFields = $hasPrimary || $hasSecondary || $hasDateRange || $hasExport || $hasActions;
@endphp

<div
    data-property-filter-toolbar
    {{ $attributes->merge(['class' => 'property-filter-toolbar property-workspace-toolbar-host w-full min-w-0 space-y-2']) }}
    x-data="{ filterOpen: false }"
    x-on:keydown.escape.window="filterOpen = false"
    x-on:turbo:before-visit.window="filterOpen = false"
    x-on:turbo:before-render.window="filterOpen = false"
    x-on:turbo:before-frame-render.window="filterOpen = false"
>
    @if ($hasFields && ($submitFilters || $hasPrimary))
        <div class="md:hidden w-full min-w-0 space-y-2">
            <button
                type="button"
                @click="filterOpen = true"
                class="inline-flex w-full min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
            >
                <i class="fa-solid fa-sliders text-slate-500" aria-hidden="true"></i>
                {{ $drawerLabel }}
                @if ($activeCount > 0)
                    <span class="inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{{ $activeCount }}</span>
                @endif
            </button>

            <div class="property-filter-toolbar__mobile-quick items-center">
                @if ($submitFilters && $action)
                    <button
                        type="button"
                        data-property-save-filter
                        class="inline-flex min-h-[36px] items-center rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
                    >
                        <i class="fa-regular fa-bookmark mr-1.5" aria-hidden="true"></i>
                        Save filter
                    </button>
                @endif
                @if ($resetUrl)
                    <a
                        href="{{ $resetUrl }}"
                        @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                        class="inline-flex min-h-[36px] items-center rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >Clear</a>
                @endif
            </div>

            <div data-property-saved-filters hidden class="flex flex-wrap gap-1.5"></div>
        </div>
    @endif

    @if ($submitFilters && $action)
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
    @elseif ($hasFields)
        <div class="property-filter-toolbar__static hidden md:flex flex-row flex-wrap items-end gap-x-2 gap-y-2 w-full min-w-0">
            @include('components.property.partials.filter-toolbar-fields', ['layout' => 'desktop'])
            @if ($hasBulk)
                <div class="flex flex-wrap items-center gap-2 md:ml-auto" data-filter-bulk>
                    {{ $bulk }}
                </div>
            @endif
        </div>
    @endif

    @if ($hasFields && ($submitFilters || $hasPrimary))
        <template x-if="filterOpen">
            <div
                x-cloak
                class="fixed inset-0 z-[6500] md:hidden"
                role="dialog"
                aria-modal="true"
                aria-label="{{ $drawerLabel }}"
            >
                <div
                    x-show="filterOpen"
                    x-transition.opacity
                    class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]"
                    @click="filterOpen = false"
                    aria-hidden="true"
                ></div>
                <div
                    x-show="filterOpen"
                    x-transition:enter="transition ease-out duration-250"
                    x-transition:enter-start="translate-y-full"
                    x-transition:enter-end="translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-y-0"
                    x-transition:leave-end="translate-y-full"
                    class="absolute inset-x-0 bottom-0 max-h-[90vh] flex flex-col rounded-t-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-2xl"
                    @click.stop
                >
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $drawerLabel }}</h2>
                        <button
                            type="button"
                            @click="filterOpen = false"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                            aria-label="Close filters"
                        >
                            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 py-4 space-y-4">
                        @if ($submitFilters && $action)
                            <form
                                id="{{ $formId }}-mobile"
                                method="{{ $method }}"
                                action="{{ $action }}"
                                @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                                @if ($revenueDateFilter) data-revenue-date-filter="{{ $revenueDateFilter }}" @endif
                                class="space-y-4"
                            >
                                @include('components.property.partials.filter-toolbar-fields', ['layout' => 'mobile', 'fieldFormId' => $formId.'-mobile'])
                            </form>
                        @else
                            @include('components.property.partials.filter-toolbar-fields', ['layout' => 'mobile'])
                        @endif
                    </div>
                    <div class="shrink-0 border-t border-slate-200 dark:border-slate-700 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] space-y-2">
                        @if ($submitFilters && $action)
                            <button
                                type="submit"
                                form="{{ $formId }}-mobile"
                                class="w-full min-h-[44px] rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                            >Apply filters</button>
                            @if ($resetUrl)
                                <a
                                    href="{{ $resetUrl }}"
                                    @if ($turboFrame) data-turbo-frame="{{ $turboFrame }}" @endif
                                    class="flex w-full min-h-[44px] items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                >Reset</a>
                            @endif
                        @else
                            <button
                                type="button"
                                @click="filterOpen = false"
                                class="w-full min-h-[44px] rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                            >Done</button>
                        @endif
                    </div>
                </div>
            </div>
        </template>
    @endif

    @if ($hasBulk && $submitFilters)
        <div class="md:hidden w-full min-w-0 pt-1" data-filter-bulk-mobile>
            {{ $bulk }}
        </div>
    @endif

    @if ($chips->isNotEmpty())
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
