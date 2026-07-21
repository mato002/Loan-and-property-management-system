<x-property-layout>
    <x-slot name="header">Earnings &amp; wallet</x-slot>

    <x-property.page title="Earnings &amp; wallet">
        <x-property.landlord.kpi-grid>
            <x-property.landlord.kpi-card label="Ledger balance" :value="$ledgerBalance" emphasis />
            <x-property.landlord.kpi-card label="Available for instruction" :value="$available" />
            <x-property.landlord.kpi-card label="Pending remittances" :value="$pendingRemittances" />
            <x-property.landlord.kpi-card label="Tenant AR (portfolio)" :value="$tenantAr" />
        </x-property.landlord.kpi-grid>

        <p class="text-xs text-slate-500">Remittances are processed manually by your agency. This portal records instructions and ledger positions only.</p>

        <x-property.hub-grid :items="[
            ['route' => 'property.landlord.earnings.withdraw', 'title' => 'Request remittance'],
            ['route' => 'property.landlord.earnings.remittances', 'title' => 'Remittance status'],
            ['route' => 'property.landlord.earnings.history', 'title' => 'Transaction history'],
            ['route' => 'property.landlord.settings.index', 'route_params' => ['section' => 'payout'], 'title' => 'Payout preferences'],
            ['route' => 'property.landlord.reports.index', 'route_params' => ['panel' => 'owner_statement'], 'title' => 'Owner statement'],
        ]" />

        @if (! empty($payoutPrefs))
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                Preferred destination:
                <span class="font-semibold">{{ strtoupper((string) ($payoutPrefs['default_destination'] ?? 'bank')) }}</span>
                @if (! empty($payoutPrefs['destination_detail']))
                    · {{ $payoutPrefs['destination_detail'] }}
                @endif
            </div>
        @endif
    </x-property.page>
</x-property-layout>
