@php
    use App\Support\Property\ResponsiveTableColumns;
    use Illuminate\Support\HtmlString;

    $portal = $portalAccess ?? [];
    $hasPortal = (bool) ($portal['has_portal_role'] ?? false);
    $periodQuery = array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '', 'tab' => $activeTab ?? 'overview']);
    $activeTab = $activeTab ?? 'overview';

    $portfolioColumns = ['Property', 'Ownership', 'Agreed pay', 'Commission', 'Units', 'Tenants', 'Owner share', 'Pending', 'Your earnings', 'Last collection', 'Actions'];
    $portfolioRows = [];
    foreach ($propertyBreakdown as $row) {
        $propertyUrl = route('property.properties.show', ['property' => $row['property_id']], false);
        $viewAction = new HtmlString(
            '<a href="'.e($propertyUrl).'" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">View</a>'
        );
        $portfolioRows[] = [
            new HtmlString('<a href="'.e($propertyUrl).'" data-turbo-frame="property-main" class="font-medium text-slate-900 dark:text-white hover:text-blue-700 break-words">'.e((string) $row['property_name']).'</a>'),
            number_format((float) $row['ownership_percent'], 2).'%',
            ! empty($row['agreed_pay_day']) ? 'Day '.((int) $row['agreed_pay_day']) : '—',
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
        $hubQuickActions[] = ['label' => 'Link property', 'modal' => 'showHubLinkProperty', 'icon' => 'fa-link', 'tone' => 'primary'];
        $hubQuickActions[] = ['label' => 'Edit landlord', 'route' => 'property.landlords.edit', 'params' => ['landlord' => $landlord->id], 'icon' => 'fa-pen-to-square'];
    }
@endphp

@php
    $hubSummaryStats = [
        ['label' => 'Properties linked', 'value' => (string) ($totals['properties'] ?? 0), 'hint' => 'Current'],
        ['label' => 'Units', 'value' => (string) ($totals['units_total'] ?? 0), 'hint' => ($totals['units_occupied'] ?? 0).' occupied · '.($totals['units_owner_occupied'] ?? 0).' owner'],
        ['label' => 'Owner share', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)), 'hint' => $periodLabel],
        ['label' => 'Your earnings', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)), 'hint' => 'At '.number_format((float) ($commissionPct ?? 0), 2).'%'],
    ];
@endphp

<x-property.workspace :compact-list="true"
    :title="'Landlord: '.$landlord->name"
    :subtitle="'360° landlord workspace — '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[]"
    :columns="[]"
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from([
        'showHubLinkProperty' => $errors->hasAny(['property_id', 'ownership_percent']) && old('return_to') === 'landlord_show',
    ]) !!}"></x-slot>

    <x-slot name="actions">
        @include('property.agent.partials.hub_quick_actions', ['actions' => $hubQuickActions])
    </x-slot>

    <x-slot name="modals">
        @include('property.agent.landlords.partials.hub_modals')
    </x-slot>

    <x-slot name="above">
        <div class="grid gap-2 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-stretch">
            <form method="get" action="{{ route('property.landlords.show', ['landlord' => $landlord->id]) }}" data-turbo-frame="property-main" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-2.5 py-2 shadow-sm w-full min-w-0">
                <input type="hidden" name="tab" value="{{ $activeTab }}" />
                <div class="w-[8.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Month</label>
                    <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                </div>
                <div class="w-[5.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">FY</label>
                    <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                </div>
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => $activeTab], false) }}" data-turbo-frame="property-main" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Reset</a>
                <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id, 'tab' => $activeTab], array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), ['export' => 'csv', 'export_scope' => preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? 'statement' : 'monthly']), false) }}" data-turbo="false" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
                <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id, 'tab' => $activeTab], array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), ['export' => 'pdf', 'export_scope' => preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? 'statement' : 'monthly']), false) }}" data-turbo="false" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
            </form>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-2.5 py-2 shadow-sm min-w-0">
                <x-property.collapsible-stats storage-key="property.hub.landlordSummaryVisible">
                    <x-property.compact-stat-strip :stats="$hubSummaryStats" />
                </x-property.collapsible-stats>
            </div>
        </div>
    </x-slot>

    <x-property.entity-hub
        entity="landlord"
        route-name="property.landlords.show"
        :route-params="['landlord' => $landlord->id]"
        :active-tab="$activeTab"
        :preserve-query="array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? ''])"
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
