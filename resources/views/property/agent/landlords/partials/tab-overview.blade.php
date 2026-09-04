@php
    use App\Support\Property\ResponsiveTableColumns;
@endphp

@include('property.agent.landlords.partials.portal-credentials-banner', [
    'landlord' => $landlord,
    'portalCredentials' => $portalCredentials ?? null,
])

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3 w-full min-w-0">
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Active tenants</p>
        <p class="text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $totals['active_tenants'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Pending share</p>
        <p class="text-sm sm:text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Ledger payable</p>
        <p class="text-sm sm:text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($portal['ledger_payable'] ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Vacant units</p>
        <p class="text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $totals['units_vacant'] ?? 0 }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 col-span-2 sm:col-span-1">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Owner occupied</p>
        <p class="text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $totals['units_owner_occupied'] ?? 0 }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 w-full min-w-0">
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profile</h3>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Name</dt><dd class="text-slate-900 dark:text-white font-medium">{{ $landlord->name }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Email</dt><dd class="text-slate-900 dark:text-white break-all">{{ $landlord->email ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Phone</dt><dd class="text-slate-900 dark:text-white">{{ $landlord->phone ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Ownership total</dt><dd class="text-slate-900 dark:text-white tabular-nums">{{ number_format((float) ($totals['ownership_sum'] ?? 0), 2) }}%</dd></div>
        </dl>
    </div>
    <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">At a glance</h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use the workspace tabs above to drill into properties, units, commission, settlements, ledger, and printable statements — all scoped to this landlord.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'properties'] + array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Properties →</a>
            <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'units'] + array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Units →</a>
            <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'settlements'] + array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Settlements →</a>
        </div>
    </div>
</div>

@include('property.agent.landlords.partials.responsive-table-section', [
    'title' => 'Portfolio snapshot ('.$periodLabel.')',
    'columns' => $portfolioColumns,
    'rows' => array_slice($portfolioRows, 0, 5),
    'columnConfig' => ResponsiveTableColumns::landlordPortfolio(),
    'emptyTitle' => 'No linked properties',
    'emptyHint' => 'Attach this landlord from the property list.',
    'tableMinWidth' => '960px',
])

@if (count($portfolioRows) > 5)
    <p class="text-sm text-slate-600"><a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'properties'] + array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']), false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-700 hover:underline">View all {{ count($portfolioRows) }} properties →</a></p>
@endif
