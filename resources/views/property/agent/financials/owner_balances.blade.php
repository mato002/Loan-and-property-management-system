<x-property.workspace
    title="Owner balances"
    subtitle="Trust positions, amounts held for landlords, and pending remittances — ledger-backed only."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-footer-row="$tableFooterRow ?? null"
    :show-search="false"
    empty-title="No owner balance lines"
    empty-hint="Every movement posts a journal entry; landlords see read-only mirrors in their portal."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.financials_period', [
            'financialsRoute' => 'property.financials.owner_balances',
            'exportRoute' => 'property.financials.owner_balances',
            'drawerLabel' => 'Owner balance filters',
            'filters' => $filters ?? [],
        ])
    </x-slot>

    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'financials-remittance') }}"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
        >Run remittance</a>
    </x-slot>
</x-property.workspace>
