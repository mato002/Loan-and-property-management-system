@php
    $hubQuickActions = [];
    if ($canManage ?? false) {
        $hubQuickActions[] = ['label' => 'Edit officer', 'route' => 'property.field_officers.edit', 'params' => ['fieldOfficer' => $fieldOfficer->id], 'icon' => 'fa-pen-to-square'];
    }
@endphp

<x-property.workspace :compact-list="false"
    :title="'Field officer: '.$fieldOfficer->name"
    subtitle="Portfolio manager — assigned properties, units, tenants, and rent under this officer."
    back-route="property.field_officers.index"
    :stats="[
        ['label' => 'Properties', 'value' => (string) ($stats['properties'] ?? 0), 'hint' => 'Assigned'],
        ['label' => 'Landlords', 'value' => (string) ($stats['landlords'] ?? 0), 'hint' => 'Across portfolio'],
        ['label' => 'Units', 'value' => (string) ($stats['units'] ?? 0), 'hint' => 'Total units'],
        ['label' => 'Active tenants', 'value' => (string) ($stats['tenants'] ?? 0), 'hint' => 'On active leases'],
        ['label' => 'Rent portfolio', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($stats['rent_portfolio'] ?? 0)), 'hint' => 'Active lease rent'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        @if ($canManage ?? false)
            <a href="{{ route('property.field_officers.edit', $fieldOfficer, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Edit officer</a>
        @endif
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <x-property.entity-hub
        entity="field_officer"
        route-name="property.field_officers.show"
        :route-params="['fieldOfficer' => $fieldOfficer->id]"
        :active-tab="$activeTab"
        :quick-actions="$hubQuickActions"
    />

    <div class="space-y-4 sm:space-y-5 w-full min-w-0">
        @includeWhen($activeTab === 'overview', 'property.agent.field_officers.partials.tab-overview')
        @includeWhen($activeTab === 'properties', 'property.agent.field_officers.partials.tab-properties')
    </div>
</x-property.workspace>
