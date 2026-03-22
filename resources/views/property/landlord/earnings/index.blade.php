<x-property-layout>
    <x-slot name="header">Earnings &amp; wallet</x-slot>

    <x-property.page
        title="Earnings &amp; wallet"
        subtitle="Structured carefully — available vs pending, withdrawals, auto-rules, and immutable history."
    >
        <x-property.module-status label="Earnings" class="mb-4" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800/60 bg-white dark:bg-gray-900/50 p-6 shadow-sm">
                <p class="text-xs uppercase text-slate-500 dark:text-slate-400 font-medium">Available balance</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">KES 0</p>
                <p class="text-xs text-slate-500 mt-2">Settled and cleared for payout.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-6 shadow-sm">
                <p class="text-xs uppercase text-slate-500 dark:text-slate-400 font-medium">Pending balance</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">KES 0</p>
                <p class="text-xs text-slate-500 mt-2">Collected but not yet settled to you.</p>
            </div>
        </div>

        <x-property.hub-grid :items="[
            ['route' => 'property.landlord.earnings.withdraw', 'title' => 'Withdraw funds', 'description' => 'Bank or M-Pesa with limits and fees.'],
            ['route' => 'property.landlord.earnings.history', 'title' => 'Transaction history', 'description' => 'Full ledger timeline (source of truth).'],
        ]" />

        <p class="text-xs text-slate-500 dark:text-slate-400">Auto-withdraw rules and approval thresholds will live here once treasury flows are implemented.</p>
    </x-property.page>
</x-property-layout>
