<x-property-layout>
    <x-slot name="header">Financials</x-slot>

    <x-property.page
        title="Financials"
        subtitle="Clean reporting layer — separate from day-to-day Revenue operations (Yardi-style summaries)."
    >
        <x-property.module-status label="Financials" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.financials.income_expenses', 'title' => 'Income vs expenses', 'description' => 'Period P&amp;L style views.'],
            ['route' => 'property.financials.cash_flow', 'title' => 'Cash flow', 'description' => 'Operating liquidity picture.'],
            ['route' => 'property.financials.owner_balances', 'title' => 'Owner balances', 'description' => 'Trust and remittance positions.'],
            ['route' => 'property.financials.commission', 'title' => 'Commission tracking', 'description' => 'Accrual vs paid.'],
        ]" />
    </x-property.page>
</x-property-layout>
