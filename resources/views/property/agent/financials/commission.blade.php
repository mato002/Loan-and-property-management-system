<x-property.workspace
    title="Commission tracking"
    subtitle="Accrued vs collected management fees, leasing bonuses, and overrides by deal."
    back-route="property.financials.index"
    :stats="[
        ['label' => 'Accrued (MTD)', 'value' => 'KES 0', 'hint' => 'From contracts'],
        ['label' => 'Invoiced', 'value' => 'KES 0', 'hint' => 'To owners'],
        ['label' => 'Paid', 'value' => 'KES 0', 'hint' => 'Cash received'],
        ['label' => 'Disputes', 'value' => '0', 'hint' => 'Open threads'],
    ]"
    :columns="['Period', 'Owner', 'Property', 'Base rent', 'Fee %', 'Accrued', 'Status', 'Actions']"
    empty-title="No commission lines"
    empty-hint="Support tiered fees and new-lease spikes; export for finance reconciliation."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'financials-invoice-commission') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Invoice owners</a>
    </x-slot>
</x-property.workspace>
