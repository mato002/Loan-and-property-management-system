@php
    $notificationsUrl = route('property.notifications', absolute: false);
    $drawerLabel = 'Notification filters';
@endphp

<x-property.filter-toolbar
    :action="$notificationsUrl"
    :reset-url="$notificationsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'read' => 'Read',
        'from' => 'From',
        'to' => 'To',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Subject, body, recipient, error…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="text" name="status" label="Status" placeholder="sent / failed / queued" :value="$filters['status'] ?? ''" />
        <x-property.filter-field type="select"
            name="read"
            label="Read"
            empty-option="All"
            :options="[
                ['value' => 'unread', 'label' => 'Unread'],
                ['value' => 'read', 'label' => 'Read'],
            ]"
            :value="$filters['read'] ?? ''"
        />
        <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 25, 50, 100])->map(fn ($n) => ['value' => (string) $n, 'label' => $n.' / page'])->all()"
            :value="(string) ($perPage ?? 25)"
        />
    </x-slot>

    @if ($canExportCommunications ?? false)
        <x-slot name="export">
            <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'csv']), absolute: false) }}" data-turbo="false" class="inline-flex min-h-[38px] items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 shrink-0">Export CSV</a>
            <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'xls']), absolute: false) }}" data-turbo="false" class="inline-flex min-h-[38px] items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 shrink-0">Export XLS</a>
            <a href="{{ route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'pdf']), absolute: false) }}" data-turbo="false" class="inline-flex min-h-[38px] items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 shrink-0">Export PDF</a>
        </x-slot>
    @endif
</x-property.filter-toolbar>
