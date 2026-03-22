<x-property.workspace
    title="Penalties & auto rules"
    subtitle="Grace windows, flat vs percentage penalties, caps, and approval thresholds per portfolio or lease type."
    back-route="property.revenue.index"
    :stats="[
        ['label' => 'Active rules', 'value' => '0', 'hint' => 'Automation enabled'],
        ['label' => 'Applied (MTD)', 'value' => 'KES 0', 'hint' => 'Posted penalties'],
        ['label' => 'Waived (MTD)', 'value' => 'KES 0', 'hint' => 'Manager overrides'],
        ['label' => 'Pending review', 'value' => '0', 'hint' => 'Above threshold'],
    ]"
    :columns="['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status']"
    empty-title="No penalty rules"
    empty-hint="Define rules that post ledger entries linked to source invoices — never silent balance changes."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'revenue-penalty-rule') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >New rule</a>
    </x-slot>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search rules…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
    <x-slot name="footer">
        <p>Recommended: simulate rule against last 12 months before activation; log who approved high-impact rules.</p>
    </x-slot>
</x-property.workspace>
