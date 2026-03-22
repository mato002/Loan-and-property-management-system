<x-property-layout>
    <x-slot name="header">Reports</x-slot>

    <x-property.page
        title="Reports"
        subtitle="Downloadable statements — income, expenses, and cash flow."
    >
        <x-property.module-status label="Reports" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.landlord.reports.income', 'title' => 'Income statements', 'description' => 'Period revenue and adjustments.'],
            ['route' => 'property.landlord.reports.expenses', 'title' => 'Expense reports', 'description' => 'Fees, maintenance, and capex.'],
            ['route' => 'property.landlord.reports.cash_flow', 'title' => 'Cash flow', 'description' => 'What moved in and out.'],
        ]" />
    </x-property.page>
</x-property-layout>
