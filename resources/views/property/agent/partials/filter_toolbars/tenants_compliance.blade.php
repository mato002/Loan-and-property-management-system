@php
    $profilesUrl = route('property.tenants.profiles', absolute: false);
    $drawerLabel = 'Compliance filters';
    $filterFormId = 'property-filter-form-'.substr(md5($profilesUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$profilesUrl"
    :reset-url="$profilesUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'risk' => 'Risk',
        'portal' => 'Portal login',
        'compliance' => 'Compliance',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search name, phone, email, ID…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="compliance"
            label="Compliance"
            empty-option="All profiles"
            :options="\App\Support\Property\TenantCompliancePresentation::gapFilterOptions()"
            :value="$filters['compliance'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="All statuses"
            :options="\App\Support\Property\TenantProfileStatus::filterOptions()"
            :value="$filters['status'] ?? ''"
        />
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
</x-property.filter-toolbar>
