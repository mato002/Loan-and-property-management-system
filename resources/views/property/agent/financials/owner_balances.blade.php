<x-property.workspace
    title="Owner balances"
    subtitle="Trust positions, amounts held for landlords, and pending remittances — ledger-backed only."
    back-route="property.financials.index"
    :stats="[
        ['label' => 'Held in trust', 'value' => 'KES 0', 'hint' => 'All owners'],
        ['label' => 'Pending remit', 'value' => 'KES 0', 'hint' => 'Scheduled'],
        ['label' => 'Owners', 'value' => '0', 'hint' => 'Active'],
    ]"
    :columns="['Owner', 'Property', 'Available', 'Pending', 'Last remittance', 'Next run', 'Statement']"
    empty-title="No owner balance lines"
    empty-hint="Every movement posts a journal entry; landlords see read-only mirrors in their portal."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'financials-remittance') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Run remittance</a>
    </x-slot>
</x-property.workspace>
