<x-property.workspace
    title="Maintenance"
    subtitle="Transparency on requests, quotes, and spend — approve high-cost jobs when your agreement requires it."
    back-route="property.landlord.portfolio"
    :stats="[
        ['label' => 'Awaiting your approval', 'value' => '0', 'hint' => 'Over threshold'],
        ['label' => 'Spend (YTD)', 'value' => 'KES 0', 'hint' => 'Your share'],
        ['label' => 'Open jobs', 'value' => '0', 'hint' => 'In progress'],
    ]"
    :columns="['Job', 'Property / unit', 'Vendor', 'Quote', 'Your approval', 'Status', 'Updated']"
    empty-title="No maintenance activity"
    empty-hint="Jobs initiated by tenants or inspections will surface here with documents and photo records."
>
    <x-slot name="footer">
        <p>Approval rules are configured per management agreement — e.g. auto-approve under KES 15,000; notify above.</p>
    </x-slot>
</x-property.workspace>
