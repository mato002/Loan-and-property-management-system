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
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'csv']), false),
                'xlsUrl' => route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'xls']), false),
                'pdfUrl' => route('property.notifications.export', array_merge((array) ($filters ?? []), ['format' => 'pdf']), false),
            ])
        </x-slot>
    @endif
</x-property.filter-toolbar>
