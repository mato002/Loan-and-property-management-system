@php
    $requestsUrl = route('property.maintenance.requests', absolute: false);
    $drawerLabel = 'Request filters';
@endphp

<x-property.filter-toolbar
    :action="$requestsUrl"
    :reset-url="$requestsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'urgency' => 'Urgency',
        'from' => 'From',
        'to' => 'To',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Category, description, unit…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="collect(['open', 'in_progress', 'done', 'closed'])->map(fn ($st) => [
                'value' => $st,
                'label' => ucfirst(str_replace('_', ' ', $st)),
            ])->all()"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="urgency"
            label="Urgency"
            empty-option="Priority: All"
            :options="collect(['normal', 'urgent', 'emergency'])->map(fn ($u) => [
                'value' => $u,
                'label' => ucfirst($u),
            ])->all()"
            :value="$filters['urgency'] ?? ''"
        />
        <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 20, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
            :value="(string) ($filters['per_page'] ?? 20)"
        />
    </x-slot>

    <x-slot name="export">
        <a
            href="{{ route('property.maintenance.requests.export', (array) ($filters ?? [])) }}"
            data-turbo="false"
            class="inline-flex min-h-[38px] items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 shrink-0"
        >Export CSV</a>
    </x-slot>
</x-property.filter-toolbar>
