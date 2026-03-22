<x-property-layout>
    <x-slot name="header">Revenue</x-slot>

    <x-property.page
        title="Revenue"
        subtitle="Core engine — rent roll and arrears first; billing, utilities, payments, and eTIMS in one lane."
    >
        <x-property.module-status label="Revenue" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.revenue.rent_roll', 'title' => 'Rent roll', 'description' => 'Who owes what, by unit and period.'],
            ['route' => 'property.revenue.arrears', 'title' => 'Arrears', 'description' => 'Aging buckets 7 / 14 / 30+ days.'],
            ['route' => 'property.revenue.invoices', 'title' => 'Invoices & billing', 'description' => 'Rent and recurring charges.'],
            ['route' => 'property.revenue.payments', 'title' => 'Payments', 'description' => 'M-Pesa, logs, reconciliation.'],
            ['route' => 'property.revenue.utilities', 'title' => 'Utilities & charges', 'description' => 'Recoveries separate from core rent.'],
            ['route' => 'property.revenue.receipts', 'title' => 'Receipts (eTIMS)', 'description' => 'Fiscal receipts when integrated.'],
        ]" />
    </x-property.page>
</x-property-layout>
