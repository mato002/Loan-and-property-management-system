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
        <a
            href="{{ route('property.vendors.directory.export', request()->query()) }}"
            data-turbo="false"
            class="inline-flex min-h-[38px] items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 shrink-0"
        >Export CSV</a>
    </x-slot>
</x-property.filter-toolbar>
