@php
    $occupancyUrl = route('property.properties.occupancy', absolute: false);
    $drawerLabel = 'Occupancy filters';
    $filters = $filters ?? [];
    $preset = (string) ($filters['preset'] ?? '');
    $queryBase = request()->query();
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
        <input type="hidden" name="preset" value="{{ $filters['preset'] ?? '' }}" />
    </x-slot>

    <x-slot name="secondary">
        <x-property.filter-field type="custom" label="Presets" :show-label="false">
            <div class="flex flex-wrap items-center gap-1.5 min-h-[38px]">
                <a
                    href="{{ route('property.properties.occupancy', array_merge($queryBase, ['status' => 'vacant', 'preset' => 'vacant']), false) }}"
                    data-turbo-frame="property-main"
                    @class([
                        'inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold',
                        'border-amber-400 bg-amber-50 text-amber-800' => $preset === 'vacant',
                        'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $preset !== 'vacant',
                    ])
                >Vacant only</a>
                <a
                    href="{{ route('property.properties.occupancy', array_merge($queryBase, ['status' => 'notice', 'preset' => 'notice']), false) }}"
                    data-turbo-frame="property-main"
                    @class([
                        'inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold',
                        'border-orange-400 bg-orange-50 text-orange-800' => $preset === 'notice',
                        'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $preset !== 'notice',
                    ])
                >Notice only</a>
                <a
                    href="{{ route('property.properties.occupancy', array_merge($queryBase, ['status' => 'vacant', 'age_bucket' => '90_plus', 'preset' => 'long_vacant']), false) }}"
                    data-turbo-frame="property-main"
                    @class([
                        'inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold',
                        'border-rose-400 bg-rose-50 text-rose-800' => $preset === 'long_vacant',
                        'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $preset !== 'long_vacant',
                    ])
                >Long vacant 90+</a>
            </div>
        </x-property.filter-field>
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'csv']), false),
            'pdfUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'pdf']), false),
            'wordUrl' => route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'word']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>

<form id="occupancy-bulk-form" method="post" action="{{ route('property.properties.occupancy.bulk', absolute: false) }}" class="mt-2 flex flex-wrap items-center gap-2">
    @csrf
    <select name="bulk_action" class="min-h-[38px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
        <option value="mark_vacant">Bulk: Mark vacant</option>
        <option value="mark_occupied">Bulk: Mark occupied</option>
        <option value="mark_notice">Bulk: Mark notice</option>
        <option value="open_assign">Bulk: Open assign tenant</option>
        <option value="open_publish">Bulk: Open publish listing</option>
        <option value="open_property">Bulk: Open property</option>
    </select>
    <button type="submit" class="inline-flex min-h-[38px] items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
        Run on selected
    </button>
    <span class="text-xs text-slate-500">Tick units in the table first.</span>
</form>
