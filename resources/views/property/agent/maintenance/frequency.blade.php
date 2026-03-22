<x-property.workspace
    title="Issue frequency"
    subtitle="Recurring defects, repeat vendors, and assets driving churn — signals for capex vs patch."
    back-route="property.maintenance.index"
    :stats="[
        ['label' => 'Repeat issues', 'value' => '0', 'hint' => 'Same unit 90d'],
        ['label' => 'Top category', 'value' => '—', 'hint' => 'By count'],
        ['label' => 'Assets flagged', 'value' => '0', 'hint' => 'Replace candidates'],
    ]"
    :columns="['Unit', 'Category', 'Events (12m)', 'Last event', 'Vendor', 'Suggested action']"
    empty-title="No frequency analytics"
    empty-hint="Group by normalized category codes; exclude one-off storm damage if tagged."
>
    <x-slot name="footer">
        <p>Use this view in monthly ops review: decide preventive maintenance vs capital replacement.</p>
    </x-slot>
</x-property.workspace>
