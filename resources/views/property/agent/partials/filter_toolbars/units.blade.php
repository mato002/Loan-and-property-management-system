@php
    $unitsUrl = route('property.properties.units', absolute: false);
    $drawerLabel = 'Unit filters';
    $unitExportQuery = request()->query();
@endphp

<x-property.filter-toolbar
    :action="$unitsUrl"
    :reset-url="$unitsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'status' => 'Status',
        'unit_type' => 'Type',
        'rent_min' => 'Min rent',
        'rent_max' => 'Max rent',
        'include_archived' => 'Archived',
    ]"
    :chip-ignore-values="[
        'property_id' => ['0', 0, ''],
        'include_archived' => ['0', 0, '', null],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search unit, property, type…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="property_id"
            label="Property"
            empty-option="All properties"
            :options="collect($allProperties ?? [])->map(fn ($p) => ['value' => (string) $p->id, 'label' => (string) $p->name])->all()"
            :value="(string) ($filters['property_id'] ?? '')"
        />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="collect(\App\Models\PropertyUnit::statusOptions())->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()->all()"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="unit_type"
            label="Type"
            empty-option="Type: All"
            :options="collect($unitTypes ?? [])->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()->all()"
            :value="$filters['unit_type'] ?? ''"
        />
        <x-property.filter-field type="number" name="rent_min" placeholder="Min rent" :value="$filters['rent_min'] ?? ''" />
        <x-property.filter-field type="number" name="rent_max" placeholder="Max rent" :value="$filters['rent_max'] ?? ''" />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
            :value="(string) ($perPage ?? $filters['per_page'] ?? 30)"
        />
        <x-property.filter-field type="custom" label="Archived" :show-label="false">
            <label class="inline-flex min-h-[38px] items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                <input type="checkbox" name="include_archived" value="1" class="rounded border-slate-300" @checked(($filters['include_archived'] ?? '0') === '1') />
                Include archived
            </label>
        </x-property.filter-field>
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.properties.units.export', array_merge($unitExportQuery, ['export' => 'csv']), false),
            'pdfUrl' => route('property.properties.units.export', array_merge($unitExportQuery, ['export' => 'pdf']), false),
            'wordUrl' => route('property.properties.units.export', array_merge($unitExportQuery, ['export' => 'word']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
