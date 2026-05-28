@php
    $propertiesUrl = route('property.properties.list', absolute: false);
    $drawerLabel = 'Property filters';
@endphp

<x-property.filter-toolbar
    :action="$propertiesUrl"
    :reset-url="$propertiesUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'city' => 'City',
        'landlord' => 'Landlord',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search name, code, city…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="city"
            label="City"
            empty-option="All cities"
            :options="collect($cities ?? [])->map(fn ($city) => ['value' => (string) $city, 'label' => (string) $city])->all()"
            :value="$filters['city'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="landlord"
            label="Landlord"
            empty-option="Landlord: All"
            :options="[
                ['value' => 'linked', 'label' => 'Linked only'],
                ['value' => 'unlinked', 'label' => 'Unlinked only'],
            ]"
            :value="$filters['landlord'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'name', 'label' => 'Sort: Name'],
                ['value' => 'city', 'label' => 'Sort: City'],
                ['value' => 'units_count', 'label' => 'Sort: Units'],
                ['value' => 'created_at', 'label' => 'Sort: Newest'],
            ]"
            :value="$filters['sort'] ?? 'name'"
        />
        <x-property.filter-field type="hidden" name="dir" :value="($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc'" />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
            :value="(string) ($filters['per_page'] ?? 30)"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'csv']), false),
            'xlsUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'xls']), false),
            'pdfUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
