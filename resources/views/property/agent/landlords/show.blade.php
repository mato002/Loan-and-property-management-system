@php
    use App\Support\Property\ResponsiveTableColumns;
    use Illuminate\Support\HtmlString;

    $portal = $portalAccess ?? [];
    $hasPortal = (bool) ($portal['has_portal_role'] ?? false);
    $periodQuery = array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '', 'tab' => $activeTab ?? 'overview']);
    $activeTab = $activeTab ?? 'overview';

    $portfolioColumns = ['Property', 'Ownership', 'Commission', 'Units', 'Tenants', 'Owner share', 'Pending', 'Your earnings', 'Last collection', 'Actions'];
    $portfolioRows = [];
    foreach ($propertyBreakdown as $row) {
        $propertyUrl = route('property.properties.show', ['property' => $row['property_id']], false);
        $viewAction = new HtmlString(
            '<a href="'.e($propertyUrl).'" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">View</a>'
        );
        $portfolioRows[] = [
            new HtmlString('<a href="'.e($propertyUrl).'" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 break-words">'.e((string) $row['property_name']).'</a>'),
            number_format((float) $row['ownership_percent'], 2).'%',
            number_format((float) ($row['commission_percent'] ?? 0), 2).'%',
            ($row['units_occupied'] ?? 0).'/'.($row['units_total'] ?? 0).' occ.',
            (string) ($row['active_tenants'] ?? 0),
            \App\Services\Property\PropertyMoney::kes((float) $row['owner_share']),
            \App\Services\Property\PropertyMoney::kes((float) $row['pending_share']),
            new HtmlString('<span class="font-semibold">'.\App\Services\Property\PropertyMoney::kes((float) $row['agent_earning']).'</span>'),
            ! empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—',
            $viewAction,
        ];
    }

    $collectionColumns = ['Date', 'Tenant', 'Channel', 'Reference', 'Amount'];
    $collectionRows = [];
    foreach ($recentCollections as $c) {
        $collectionRows[] = [
            $c->paid_at ? \Illuminate\Support\Carbon::parse((string) $c->paid_at)->format('Y-m-d H:i') : '—',
            (string) ($c->tenant_name ?? '—'),
            ucfirst((string) ($c->channel ?? '—')),
            new HtmlString('<span class="font-mono text-xs break-all">'.e((string) ($c->external_ref ?? '—')).'</span>'),
            \App\Services\Property\PropertyMoney::kes((float) ($c->amount ?? 0)),
        ];
    }

    $hubQuickActions = [];
    if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage')) {
        $hubQuickActions[] = ['label' => 'Edit landlord', 'route' => 'property.landlords.edit', 'params' => ['landlord' => $landlord->id], 'icon' => 'fa-pen-to-square'];
    }
    $hubQuickActions[] = ['label' => 'Owner balances', 'route' => 'property.financials.owner_balances', 'params' => [], 'icon' => 'fa-wallet'];
@endphp

<x-property.workspace :compact-list="false"
    :title="'Landlord: '.$landlord->name"
    :subtitle="'360° landlord workspace — portfolio, units, commission, settlements, and statements. Period: '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[
        ['label' => 'Properties linked', 'value' => (string) ($totals['properties'] ?? 0), 'hint' => 'Current'],
        ['label' => 'Units', 'value' => (string) ($totals['units_total'] ?? 0), 'hint' => ($totals['units_occupied'] ?? 0).' occupied · '.($totals['units_owner_occupied'] ?? 0).' owner'],
        ['label' => 'Owner share', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)), 'hint' => $periodLabel],
        ['label' => 'Your earnings', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)), 'hint' => 'At '.number_format((float) ($commissionPct ?? 0), 2).'%'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlords.index', array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Back to landlords</a>
        <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id, 'tab' => 'statement'], array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? ''])), false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Statement</a>
        <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id], array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), ['export' => 'csv']), false) }}" data-turbo="false" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id], array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), ['export' => 'pdf']), false) }}" data-turbo="false" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
    </x-slot>

    <x-slot name="above">
        <form method="get" action="{{ route('property.landlords.show', ['landlord' => $landlord->id]) }}" data-turbo-frame="property-main" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 sm:p-4 shadow-sm w-full min-w-0">
            <input type="hidden" name="tab" value="{{ $activeTab }}" />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto] gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                    <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">FY</label>
                    <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <button type="submit" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Apply period</button>
                <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => $activeTab], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Reset</a>
            </div>
        </form>
    </x-slot>

    <x-property.entity-hub
        entity="landlord"
        route-name="property.landlords.show"
        :route-params="['landlord' => $landlord->id]"
        :active-tab="$activeTab"
        :preserve-query="array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? ''])"
        :quick-actions="$hubQuickActions"
    />

    <div class="space-y-4 sm:space-y-5 w-full min-w-0">
        @includeWhen($activeTab === 'overview', 'property.agent.landlords.partials.tab-overview')
        @includeWhen($activeTab === 'properties', 'property.agent.landlords.partials.tab-properties')
        @includeWhen($activeTab === 'units', 'property.agent.landlords.partials.tab-units')
        @includeWhen($activeTab === 'collections', 'property.agent.landlords.partials.tab-collections')
        @includeWhen($activeTab === 'commission', 'property.agent.landlords.partials.tab-commission')
        @includeWhen($activeTab === 'settlements', 'property.agent.landlords.partials.tab-settlements')
        @includeWhen($activeTab === 'ledger', 'property.agent.landlords.partials.tab-ledger')
        @includeWhen($activeTab === 'statement', 'property.agent.landlords.partials.tab-statement')
        @includeWhen($activeTab === 'portal', 'property.agent.landlords.partials.tab-portal')
    </div>
</x-property.workspace>
