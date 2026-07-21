<x-property-layout>
    <x-slot name="header">Collections</x-slot>

    <x-property.page
        title="Collections overview"
        subtitle="Rent roll, arrears, billing, utilities, payments, and bank matching — pick a lane below."
        workspace="collections"
    >
        <x-property.module-status label="Collections" class="mb-4" />

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-gray-800/80">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</p>
                    @if (! empty($stat['hint']))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $stat['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @php
            $canSettlePayments = auth()->user()?->hasPmPermission('payments.settle');
            $items = [
                ['route' => 'property.revenue.rent_roll', 'title' => 'Rent roll', 'description' => 'Who owes what, by unit and period.'],
                ['route' => 'property.revenue.arrears', 'title' => 'Arrears', 'description' => 'Aging buckets for overdue balances.'],
                ['route' => 'property.revenue.uninvoiced_leases', 'title' => 'Uninvoiced leases', 'description' => 'Active leases missing a rent bill for the month.'],
                ['route' => 'property.revenue.invoices', 'title' => 'Invoices & billing', 'description' => 'Rent and recurring charges.'],
                ['route' => 'property.revenue.payments', 'title' => 'Payments', 'description' => 'Record and allocate tenant payments.'],
                ['route' => 'property.revenue.utilities', 'title' => 'Utilities & charges', 'description' => 'Recoveries separate from core rent.'],
                ['route' => 'property.revenue.receipts', 'title' => 'Receipts', 'description' => 'Receipt register and downloads.'],
                ['route' => 'property.revenue.penalties', 'title' => 'Penalties', 'description' => 'Penalty rules and automation.'],
            ];
            if ($canSettlePayments) {
                $items[] = ['route' => 'property.equity.matched', 'title' => 'Matched payments', 'description' => 'Equity and M-Pesa payments matched to tenants.'];
                $items[] = ['route' => 'property.equity.unmatched', 'title' => 'Unmatched', 'description' => 'Bank lines waiting for assignment.'];
                $items[] = ['route' => 'property.revenue.tenant_credits', 'title' => 'Tenant credits', 'description' => 'Advance balances and auto-apply.'];
            }
        @endphp

        <x-property.hub-grid :items="$items" />
    </x-property.page>
</x-property-layout>
