@php
    $directoryUrl = route('property.tenants.directory', absolute: false);
    $drawerLabel = 'Tenant filters';
    $filterFormId = 'property-filter-form-'.substr(md5($directoryUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$directoryUrl"
    :reset-url="$directoryUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'risk' => 'Risk',
        'portal' => 'Portal login',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search name, phone, email, ID…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="risk"
            label="Risk"
            empty-option="All risk"
            :options="[
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High'],
            ]"
            :value="$filters['risk'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="portal"
            label="Portal"
            empty-option="Portal login: all"
            :options="[
                ['value' => 'with', 'label' => 'With portal login'],
                ['value' => 'without', 'label' => 'Without portal login'],
            ]"
            :value="$filters['portal'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 20, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
            :value="(string) ($filters['per_page'] ?? 20)"
        />
    </x-slot>

    <x-slot name="export">
        <a
            href="{{ route('property.tenants.directory.export', request()->query()) }}"
            class="inline-flex min-h-[38px] items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 shrink-0"
        >Export CSV</a>
    </x-slot>
</x-property.filter-toolbar>
