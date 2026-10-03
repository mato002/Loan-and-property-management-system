@php
    $occupancyUrl = route('property.properties.occupancy', absolute: false);
    $drawerLabel = 'Occupancy filters';
    $filters = $filters ?? [];
    $preset = (string) ($filters['preset'] ?? '');
@endphp

<x-property.filter-toolbar
    :action="$occupancyUrl"
    :reset-url="$occupancyUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'status' => 'Status',
        'age_bucket' => 'Vacancy age',
        'preset' => 'Preset',
    ]"
    :chip-ignore-values="[
        'property_id' => ['0', 0, ''],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search unit or property…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="property_id"
            label="Property"
            empty-option="All properties"
            :options="collect($propertyOptions ?? [])->map(fn ($p) => ['value' => (string) $p->id, 'label' => (string) $p->name])->all()"
            :value="(string) ($filters['property_id'] ?? '')"
        />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="All statuses"
            :options="collect(\App\Models\PropertyUnit::statusOptions())->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()->all()"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="age_bucket"
            label="Vacancy age"
            empty-option="Vacancy age: All"
            :options="[
                ['value' => '0_30', 'label' => '0-30 days'],
                ['value' => '31_60', 'label' => '31-60 days'],
                ['value' => '61_90', 'label' => '61-90 days'],
                ['value' => '90_plus', 'label' => '90+ days'],
            ]"
            :value="$filters['age_bucket'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="preset"
            label="Preset"
            empty-option="Presets"
            :options="[
                ['value' => 'vacant', 'label' => 'Vacant only'],
                ['value' => 'notice', 'label' => 'Notice only'],
                ['value' => 'long_vacant', 'label' => 'Long vacant 90+'],
            ]"
            :value="$preset"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'csv']), false),
            'pdfUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'pdf']), false),
            'wordUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'word']), false),
        ])
    </x-slot>
    <x-slot name="bulk">
        <form id="occupancy-bulk-form" method="post" action="{{ route('property.properties.occupancy.bulk', absolute: false) }}" class="flex flex-nowrap items-center gap-2">
            @csrf
            <select name="bulk_action" aria-label="Bulk action" class="min-h-[38px] max-w-[11rem] rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
                <option value="mark_vacant">Mark vacant</option>
                <option value="mark_occupied">Mark occupied</option>
                <option value="mark_notice">Mark notice</option>
                <option value="open_assign">Assign tenant</option>
                <option value="open_publish">Publish listing</option>
                <option value="open_property">Open property</option>
            </select>
            <button type="submit" title="Tick units in the table first." class="inline-flex min-h-[38px] shrink-0 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
                Run on selected
            </button>
        </form>
    </x-slot>
</x-property.filter-toolbar>
