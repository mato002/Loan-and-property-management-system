@php
    $landlordsUrl = route('property.landlords.index', absolute: false);
    $drawerLabel = 'Landlord filters';
@endphp

<x-property.filter-toolbar
    :action="$landlordsUrl"
    :reset-url="$landlordsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'linked' => 'Link status',
        'property_id' => 'Property',
        'share_level' => 'Share level',
        'month' => 'Month',
        'fy' => 'FY',
    ]"
    :chip-ignore-values="[
        'property_id' => ['0', 0],
        'linked' => ['all'],
        'share_level' => ['all'],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Name, email, property…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="linked"
            label="Link status"
            empty-option="Link: All"
            :options="[
                ['value' => 'linked', 'label' => 'Linked only'],
                ['value' => 'unlinked', 'label' => 'Unlinked only'],
            ]"
            :value="($filters['linked'] ?? 'all') === 'all' ? '' : ($filters['linked'] ?? '')"
        />
        <x-property.filter-field type="select"
            name="property_id"
            label="Property"
            :options="collect([['value' => '0', 'label' => 'Property: All']])
                ->merge(collect($properties ?? [])->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name]))
                ->all()"
            :value="(string) ($filters['property_id'] ?? '0')"
        />
        <x-property.filter-field type="select"
            name="share_level"
            label="Share level"
            empty-option="Share: All"
            :options="[
                ['value' => 'high', 'label' => 'High (>=100%)'],
                ['value' => 'medium', 'label' => 'Medium (30-99%)'],
                ['value' => 'low', 'label' => 'Low (1-29%)'],
            ]"
            :value="($filters['share_level'] ?? 'all') === 'all' ? '' : ($filters['share_level'] ?? '')"
        />
    </x-slot>

    <x-slot name="dateRange">
        <x-property.filter-field type="month" name="month" label="Month" :value="$monthValue ?? ''" />
        <x-property.filter-field type="number" name="fy" label="FY" :value="$fyValue ?? now()->year" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.landlords.index', array_merge(request()->query(), ['export' => 'csv']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
