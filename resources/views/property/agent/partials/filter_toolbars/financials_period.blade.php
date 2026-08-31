@php
    /** @var string $financialsRoute */
    $financialsUrl = route($financialsRoute, absolute: false);
    $drawerLabel = $drawerLabel ?? 'Period filters';
@endphp

<x-property.filter-toolbar
    :action="$financialsUrl"
    :reset-url="$financialsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="['month' => 'Month', 'fy' => 'FY', 'q' => 'Search']"
>
    <x-slot name="primary">
        @isset($filters['q'])
            <x-property.filter-field type="search" name="q" placeholder="Search…" :value="$filters['q'] ?? ''" wide />
        @endisset
        <x-property.filter-field type="month" name="month" label="Month" :value="$monthValue ?? ''" />
        <x-property.filter-field type="number" name="fy" label="FY" :value="$fyValue ?? now()->year" />
    </x-slot>

    @isset($exportRoute)
        <x-slot name="export">
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route($exportRoute, array_merge(request()->query(), ['export' => 'csv']), false),
                'pdfUrl' => route($exportRoute, array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </x-slot>
    @endisset
</x-property.filter-toolbar>
