<x-property.workspace :compact-list="false"
    :title="'Tenant: '.$tenant->name"
    subtitle="360° tenant workspace — occupancy, billing, deposits, notices, and statement."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Status', 'value' => (string) (($profileStatus['label'] ?? '—')), 'hint' => (string) (($profileStatus['hint'] ?? 'Occupancy'))],
        ['label' => 'Account', 'value' => (string) ($tenant->account_number ?: '—'), 'hint' => $occupancyLabel ?? 'Unit'],
        ['label' => 'Monthly rent', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($monthlyRentTotal ?? 0)), 'hint' => ((int) ($activeLeaseCount ?? 0)).' active lease(s)'],
        ['label' => 'Total due', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totalDue['total_due'] ?? 0)), 'hint' => 'AR + uninvoiced CF − credit'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.tenants.directory', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Directory</a>
        <a href="{{ route('property.tenants.edit', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit tenant</a>
        <a href="{{ route('property.tenants.statement', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Statement</a>
    </x-slot>

    @include('property.agent.tenants.partials.hub')
</x-property.workspace>
