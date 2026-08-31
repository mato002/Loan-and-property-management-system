@php
    $commissionUrl = route('property.financials.commission', absolute: false);
    $drawerLabel = 'Commission filters';
@endphp

<x-property.filter-toolbar
    :action="$commissionUrl"
    :reset-url="$commissionUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'landlord_id' => 'Landlord',
        'property_id' => 'Property',
        'month' => 'Month',
        'fy' => 'FY',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search owner or property…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field
            type="select"
            name="landlord_id"
            label="Landlord"
            empty-option="Landlord: All"
            :options="collect($landlords ?? [])->map(fn ($landlord) => ['value' => (string) $landlord->id, 'label' => $landlord->name])->all()"
            :value="(int) ($filters['landlord_id'] ?? 0) > 0 ? (string) ($filters['landlord_id'] ?? '') : ''"
        />
        <x-property.filter-field
            type="select"
            name="property_id"
            label="Property"
            empty-option="Property: All"
            :options="collect($properties ?? [])->map(fn ($property) => ['value' => (string) $property->id, 'label' => $property->name])->all()"
            :value="(int) ($filters['property_id'] ?? 0) > 0 ? (string) ($filters['property_id'] ?? '') : ''"
        />
        <x-property.filter-field type="month" name="month" label="Month" :value="$monthValue ?? ''" />
        <x-property.filter-field type="number" name="fy" label="FY" :value="$fyValue ?? now()->year" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.financials.commission', array_merge(request()->query(), ['export' => 'csv']), false),
            'pdfUrl' => route('property.financials.commission', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
