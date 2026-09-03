@php
    $vendorsUrl = route('property.vendors.directory', absolute: false);
    $drawerLabel = 'Vendor filters';
@endphp

<x-property.filter-toolbar
    :action="$vendorsUrl"
    :reset-url="$vendorsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'category' => 'Category',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search name, category, phone, email…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="[
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="text" name="category" label="Category" placeholder="Category" :value="$filters['category'] ?? ''" />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 20, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
            :value="(string) ($filters['per_page'] ?? 20)"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.table_export_dropdown', [
            'route' => 'property.vendors.directory.export',
            'query' => request()->query(),
        ])
    </x-slot>
</x-property.filter-toolbar>
