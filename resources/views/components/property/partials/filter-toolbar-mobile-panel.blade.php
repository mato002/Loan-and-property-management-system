@php
    $formId = $formId ?? '';
    $hasFields = (bool) ($hasFields ?? false);
    $submitFilters = (bool) ($submitFilters ?? true);
    $hasPrimary = (bool) ($hasPrimary ?? false);
    $toolbarViewport = \App\Support\Property\FilterToolbarViewport::current();
    $wrapInDrawer = $toolbarViewport === 'all';
    $drawerLabel = $drawerLabel ?? 'Filters';
    $activeFilterCount = (int) ($activeFilterCount ?? 0);
    $chips = $chips ?? collect();
@endphp

@if ($hasFields && ($submitFilters || $hasPrimary))
    @if ($wrapInDrawer)
        <x-property.responsive.mobile-filter-drawer
            :label="$drawerLabel"
            :active-count="$activeFilterCount"
            :form-id="$formId"
            :turbo-frame="$turboFrame ?? 'property-main'"
            class="md:hidden"
            data-filter-toolbar-mobile-panel
        >
            @if ($chips instanceof \Illuminate\Support\Collection && $chips->isNotEmpty())
                <x-slot name="chips">
                    @foreach ($chips as $chip)
                        <a
                            href="{{ $chip['removeUrl'] }}"
                            @if (! empty($turboFrame)) data-turbo-frame="{{ $turboFrame }}" @endif
                            class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-100"
                        >
                            <span>{{ $chip['label'] }}: {{ \Illuminate\Support\Str::limit($chip['value'], 24) }}</span>
                            <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                </x-slot>
            @endif
            <x-slot name="mobile">
                @include('components.property.partials.filter-toolbar-mobile-fields')
            </x-slot>
        </x-property.responsive.mobile-filter-drawer>
    @else
        <div class="w-full min-w-0 space-y-3" data-filter-toolbar-mobile-panel>
            @include('components.property.partials.filter-toolbar-mobile-fields')
        </div>
    @endif
@endif
