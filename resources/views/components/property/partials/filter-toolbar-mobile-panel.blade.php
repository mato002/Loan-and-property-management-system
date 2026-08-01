@php
    $formId = $formId ?? '';
    $hasFields = (bool) ($hasFields ?? false);
    $submitFilters = (bool) ($submitFilters ?? true);
    $hasPrimary = (bool) ($hasPrimary ?? false);
@endphp

@if ($hasFields && ($submitFilters || $hasPrimary))
    <div @class([
        'w-full min-w-0 space-y-3',
        'md:hidden' => ($__propertyToolbarViewport ?? 'all') === 'all',
    ]) data-filter-toolbar-mobile-panel>
        @if (! empty($showSavedFiltersUi))
            <div class="property-filter-toolbar__mobile-quick flex flex-wrap items-center gap-2">
                @if ($submitFilters && ($action ?? null))
                    <button
                        type="button"
                        data-property-save-filter
                        class="inline-flex min-h-[36px] items-center rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
                    >
                        <i class="fa-regular fa-bookmark mr-1.5" aria-hidden="true"></i>
                        Save filter
                    </button>
                @endif
                @if (! empty($resetUrl))
                    <a
                        href="{{ $resetUrl }}"
                        @if (! empty($turboFrame)) data-turbo-frame="{{ $turboFrame }}" @endif
                        class="inline-flex min-h-[36px] items-center rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >Clear</a>
                @endif
            </div>
            <div data-property-saved-filters hidden class="flex flex-wrap gap-1.5"></div>
        @endif

        @if ($submitFilters && ($action ?? null))
            <form
                id="{{ $formId }}-mobile"
                method="{{ $method ?? 'get' }}"
                action="{{ $action }}"
                @if (! empty($turboFrame)) data-turbo-frame="{{ $turboFrame }}" @endif
                @if (! empty($revenueDateFilter)) data-revenue-date-filter="{{ $revenueDateFilter }}" @endif
                class="space-y-3"
            >
                @include('components.property.partials.filter-toolbar-fields', ['layout' => 'mobile', 'fieldFormId' => $formId.'-mobile'])
            </form>
            <div class="flex flex-col gap-2">
                <button
                    type="submit"
                    form="{{ $formId }}-mobile"
                    class="w-full min-h-[44px] rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                >Apply filters</button>
                @if (! empty($resetUrl))
                    <a
                        href="{{ $resetUrl }}"
                        @if (! empty($turboFrame)) data-turbo-frame="{{ $turboFrame }}" @endif
                        class="flex w-full min-h-[44px] items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                    >Reset</a>
                @endif
            </div>
        @else
            @include('components.property.partials.filter-toolbar-fields', ['layout' => 'mobile'])
        @endif
    </div>
@endif
