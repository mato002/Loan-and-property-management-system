<x-property-layout>
    <x-slot name="header">Collections</x-slot>

    <x-property.page
        title="Collections"
        subtitle="Core engine — rent roll and arrears first; billing, utilities, payments, and eTIMS in one lane."
        workspace="collections"
    >
        <x-property.module-status label="Collections" class="mb-4" />

        @php
            $canSettlePayments = auth()->user()?->hasPmPermission('payments.settle');
            $items = [
                ['route' => 'property.revenue.uninvoiced_leases', 'title' => 'Uninvoiced leases', 'description' => 'Active leases missing a rent bill for the month.'],
            ];
            if ($canSettlePayments) {
                $items[] = ['route' => 'property.equity.matched', 'title' => 'Matched payments', 'description' => 'Equity and M-Pesa payments matched to tenants.'];
                $items[] = ['route' => 'property.revenue.tenant_credits', 'title' => 'Tenant credits', 'description' => 'Advance balances and auto-apply.'];
            }
        @endphp
        @if ($items !== [])
            <x-property.hub-grid :items="$items" />
        @endif
    </x-property.page>
</x-property-layout>
