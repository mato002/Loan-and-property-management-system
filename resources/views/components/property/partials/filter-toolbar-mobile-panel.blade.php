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
            details.property-filter-mobile-toggle > summary {
                list-style: none;
            }
            details.property-filter-mobile-toggle > summary::-webkit-details-marker {
                display: none;
            }
        </style>
    @endonce

    @php
        ob_start();
    @endphp
    @include('components.property.partials.filter-toolbar-mobile-fields')
    @php
        $mobileFieldHtml = (string) ob_get_clean();
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
                <details class="property-filter-mobile-toggle md:hidden w-full min-w-0 rounded-xl border border-slate-300 bg-white shadow-sm dark:border-slate-600 dark:bg-gray-800">
                    <summary class="flex min-h-[44px] cursor-pointer items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                        <i class="fa-solid fa-sliders text-slate-500" aria-hidden="true"></i>
                        {{ $drawerLabel }}
                        @if ($activeFilterCount > 0)
                            <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{{ $activeFilterCount }}</span>
                        @endif
                    </summary>
                    <div class="space-y-3 border-t border-slate-200 px-3 py-3 dark:border-slate-700">
                        {!! $mobileFieldRest !!}
                    </div>
                </details>
            @endif
        @endif
    </div>
@endif
