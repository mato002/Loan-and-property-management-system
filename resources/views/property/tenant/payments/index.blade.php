<x-property-layout>
    <x-slot name="header">Payments</x-slot>

    <x-property.page
        title="Payments"
        subtitle="KejaPay-style simplicity — pay, history, eTIMS receipts."
    >
        <x-property.module-status label="Payments" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.tenant.payments.pay', 'title' => 'Pay rent', 'description' => 'M-Pesa STK push.'],
            ['route' => 'property.tenant.payments.history', 'title' => 'Payment history', 'description' => 'Attempts and settlements.'],
            ['route' => 'property.tenant.payments.receipts', 'title' => 'Receipts (eTIMS)', 'description' => 'Official tax receipts.'],
        ]" />
    </x-property.page>
</x-property-layout>
