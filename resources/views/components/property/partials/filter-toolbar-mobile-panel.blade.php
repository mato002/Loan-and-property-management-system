@php
    $formId = $formId ?? '';
    $hasFields = (bool) ($hasFields ?? false);
    $submitFilters = (bool) ($submitFilters ?? true);
    $hasPrimary = (bool) ($hasPrimary ?? false);
    $toolbarViewport = \App\Support\Property\FilterToolbarViewport::current();
    $embedded = $toolbarViewport === 'mobile';
    $drawerLabel = $drawerLabel ?? 'Filters';
    $activeFilterCount = (int) ($activeFilterCount ?? 0);
@endphp

@if ($hasFields && ($submitFilters || $hasPrimary))
    @once
        <style>
            @media (max-width: 767.98px) {
                [data-property-filter-form-desktop],
                .property-filter-toolbar__static {
                    display: none !important;
                }
            }
            .property-filter-mobile-toggle > [data-filter-disclosure-toggle] {
                width: 100%;
                margin: 0;
                border: 0;
                background: transparent;
                font: inherit;
                color: inherit;
                cursor: pointer;
                text-align: center;
                touch-action: manipulation;
            }
            [data-filter-toolbar-mobile-panel] select,
            .property-filter-mobile-toggle select,
            .property-searchable-select__trigger,
            .property-searchable-select__panel {
                touch-action: manipulation;
                pointer-events: auto;
            }
        </style>
    @endonce

    @php
        ob_start();
    @endphp
    @include('components.property.partials.filter-toolbar-mobile-fields')
    @php
        $mobileFieldHtml = (string) ob_get_clean();
        $mobileFieldHtml = (string) preg_replace('/\b(id|for)="(filter-field-[^"]+)"/', '$1="$2-m"', $mobileFieldHtml);
        $mobileFieldSplit = \App\Support\Ui\MobileFilterSearchSplit::split($mobileFieldHtml);
        $mobileFieldRest = trim($mobileFieldSplit['rest']);
        $mobileFieldHasRest = $mobileFieldRest !== '' && preg_match('/<(select|input|button|a|textarea)\b/i', $mobileFieldRest) === 1;
    @endphp

    <div @class([
        'w-full min-w-0 space-y-2',
        'md:hidden' => ! $embedded,
    ]) data-filter-toolbar-mobile-panel>
        @if ($mobileFieldSplit['search'] !== '')
            <div class="w-full min-w-0" data-mobile-filter-search>
                {!! $mobileFieldSplit['search'] !!}
            </div>
        @endif

        @if ($mobileFieldHasRest)
            @if ($embedded)
                <div class="w-full min-w-0 space-y-3">
                    {!! $mobileFieldRest !!}
                </div>
            @else
                <div class="property-filter-mobile-toggle md:hidden w-full min-w-0 rounded-xl border border-slate-300 bg-white shadow-sm dark:border-slate-600 dark:bg-gray-800" data-filter-disclosure>
                    <button
                        type="button"
                        data-filter-disclosure-toggle
                        aria-expanded="false"
                        class="flex min-h-[44px] cursor-pointer items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100"
                    >
                        <i class="fa-solid fa-sliders text-slate-500" aria-hidden="true"></i>
                        {{ $drawerLabel }}
                        @if ($activeFilterCount > 0)
                            <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <div class="space-y-3 border-t border-slate-200 px-3 py-3 dark:border-slate-700" data-filter-disclosure-panel hidden>
                        {!! $mobileFieldRest !!}
                    </div>
                </div>
            @endif
        @endif
    </div>
@endif
