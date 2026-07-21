@php
    use App\Support\Property\ResponsiveTableColumns;
    use Illuminate\Support\HtmlString;

    $portal = $portalAccess ?? [];
    $hasPortal = (bool) ($portal['has_portal_role'] ?? false);
    $periodQuery = array_filter(['month' => $monthValue ?? '', 'fy' => $fyValue ?? '']);

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
@endphp

<x-property.workspace
    :title="'Landlord: '.$landlord->name"
    :subtitle="'360° landlord hub — portal access, portfolio, collections, and owner position. Period: '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[
        ['label' => 'Properties linked', 'value' => (string) ($totals['properties'] ?? 0), 'hint' => 'Current'],
        ['label' => 'Units', 'value' => (string) ($totals['units_total'] ?? 0), 'hint' => ($totals['units_occupied'] ?? 0).' occupied'],
        ['label' => 'Owner share', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)), 'hint' => $periodLabel],
        ['label' => 'Your earnings', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)), 'hint' => 'At '.number_format((float) ($commissionPct ?? 0), 2).'%'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlords.index', $periodQuery, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Back to landlords</a>
        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <a href="{{ route('property.landlords.edit', $landlord, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Edit landlord</a>
        @endif
        <a href="{{ route('property.landlords.statement', array_merge(['landlord' => $landlord->id], $periodQuery), false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Statement</a>
        @if (auth()->check() && auth()->user()?->hasPmPermission('users.impersonate') && $hasPortal)
            <form method="post" action="{{ route('property.landlords.impersonate', $landlord, false) }}" class="w-full sm:w-auto" data-swal-title="View as landlord?" data-swal-confirm="Open the landlord portal as {{ $landlord->name }}? Use Stop impersonating to return." data-swal-confirm-text="Yes, open portal">
                @csrf
                <button type="submit" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2.5 text-sm font-medium text-indigo-800 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-200">View portal as landlord</button>
            </form>
        @endif
        <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id], $periodQuery, ['export' => 'csv']), false) }}" data-turbo="false" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">CSV</a>
        <a href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id], $periodQuery, ['export' => 'pdf']), false) }}" data-turbo="false" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">PDF</a>
    </x-slot>

    <x-slot name="above">
        <form method="get" action="{{ route('property.landlords.show', ['landlord' => $landlord->id]) }}" data-turbo-frame="property-main" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 sm:p-4 shadow-sm w-full min-w-0">
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
                <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Reset</a>
            </div>
        </form>
    </x-slot>

    <div class="space-y-4 sm:space-y-5 w-full min-w-0">
        @include('property.agent.landlords.partials.portal-credentials-banner', [
            'landlord' => $landlord,
            'portalCredentials' => $portalCredentials ?? null,
        ])

        {{-- Secondary KPI strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3 w-full min-w-0">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-500">Active tenants</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $totals['active_tenants'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-500">Pending share</p>
                <p class="text-sm sm:text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 col-span-2 sm:col-span-1">
                <p class="text-[11px] uppercase tracking-wide text-slate-500">Ledger payable</p>
                <p class="text-sm sm:text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($portal['ledger_payable'] ?? 0)) }}</p>
            </div>
        </div>

        {{-- Portal access --}}
        <div id="landlord-portal-access" class="property-compact-panel rounded-xl sm:rounded-2xl border {{ $hasPortal ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20' }} p-4 shadow-sm w-full min-w-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Landlord portal access</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        @if ($hasPortal)
                            Credentials are sent by email and/or SMS when you onboard or use <strong>Reset &amp; send login</strong>. The new password is also shown on this page so you can copy it manually.
                        @else
                            This user is not set up as a landlord portal account.
                        @endif
                    </p>
                </div>
                @if ($hasPortal && auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                    @php $resendTarget = trim((string) ($landlord->email ?? '')) ?: trim((string) ($landlord->phone ?? '')); @endphp
                    <form method="post" action="{{ route('property.landlords.resend_portal_login', $landlord, false) }}" data-turbo-frame="_top" data-swal-title="Reset portal password?" data-swal-confirm="Generate a new temporary password and send it to {{ $resendTarget }}?" data-swal-confirm-text="Yes, reset &amp; send" class="w-full sm:w-auto shrink-0">
                        @csrf
                        <button type="submit" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Reset &amp; send login</button>
                    </form>
                @endif
            </div>
            @if ($hasPortal)
                @php
                    $freshCreds = $portalCredentials ?? null;
                    if (! is_array($freshCreds)) {
                        $freshCreds = session('landlord_portal_credentials');
                    }
                    if (! is_array($freshCreds)) {
                        $freshCreds = session('landlord_portal_credentials_pending_'.(int) $landlord->id);
                    }
                    $showFreshPassword = is_array($freshCreds)
                        && (int) ($freshCreds['landlord_id'] ?? 0) === (int) $landlord->id
                        && ! empty($freshCreds['temporary_password']);
                @endphp
                @if ($showFreshPassword)
                    <div
                        x-data="{
                            copied: false,
                            copyPassword() {
                                const value = @js($freshCreds['temporary_password']);
                                if (!value) return;
                                if (navigator.clipboard) {
                                    navigator.clipboard.writeText(value).catch(() => {});
                                }
                                this.copied = true;
                                setTimeout(() => { this.copied = false; }, 2000);
                            }
                        }"
                        class="mt-3 rounded-lg border border-emerald-400 bg-emerald-100/80 p-3 dark:border-emerald-700 dark:bg-emerald-900/30"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">New temporary password — copy now</p>
                        <p class="mt-1 text-xs text-emerald-900/80 dark:text-emerald-100/80">{{ $freshCreds['delivery_summary'] ?? 'Sent by email/SMS when delivery succeeded.' }}</p>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="text" readonly value="{{ $freshCreds['temporary_password'] }}" class="w-full rounded-lg border border-emerald-300 bg-white px-3 py-2.5 font-mono text-base font-bold tracking-wide text-slate-900 dark:border-emerald-700 dark:bg-gray-900 dark:text-white" />
                            <button type="button" @click="copyPassword()" class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                <span x-text="copied ? 'Copied!' : 'Copy password'"></span>
                            </button>
                        </div>
                    </div>
                @endif
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3"><dt class="text-xs font-medium text-slate-500 uppercase">Login email</dt><dd class="mt-1 text-slate-900 dark:text-white break-all">{{ $landlord->email ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3"><dt class="text-xs font-medium text-slate-500 uppercase">Login phone</dt><dd class="mt-1 text-slate-900 dark:text-white">{{ $landlord->phone ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-white/70 dark:bg-gray-900/40 p-3 sm:col-span-2"><dt class="text-xs font-medium text-slate-500 uppercase">Portal sign-in URL</dt><dd class="mt-1 break-all"><a href="{{ $portal['login_url'] ?? '#' }}" class="text-indigo-600 hover:underline dark:text-indigo-400" target="_blank" rel="noopener">{{ $portal['login_url'] ?? '—' }}</a></dd></div>
                </dl>
            @endif
        </div>

        {{-- Profile + quick actions --}}
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
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Quick actions</h3>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                        <a href="{{ route('property.landlords.edit', $landlord, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-800 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-200">Edit profile</a>
                    @endif
                    <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Link properties</a>
                    <a href="{{ route('property.financials.owner_balances', $periodQuery, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Owner balances</a>
                    <a href="{{ route('property.financials.commission', $periodQuery, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Commission</a>
                    @if (trim((string) ($landlord->email ?? '')) !== '')
                        <a href="mailto:{{ $landlord->email }}" data-turbo="false" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Email landlord</a>
                    @endif
                </div>
            </div>
        </div>

        @include('property.agent.landlords.partials.responsive-table-section', [
            'title' => 'Portfolio & shares ('.$periodLabel.')',
            'columns' => $portfolioColumns,
            'rows' => $portfolioRows,
            'columnConfig' => ResponsiveTableColumns::landlordPortfolio(),
            'emptyTitle' => 'No linked properties',
            'emptyHint' => 'Attach this landlord from the property list.',
            'tableMinWidth' => '960px',
        ])

        @include('property.agent.landlords.partials.responsive-table-section', [
            'title' => 'Recent collections ('.$periodLabel.')',
            'columns' => $collectionColumns,
            'rows' => $collectionRows,
            'columnConfig' => ResponsiveTableColumns::landlordCollections(),
            'emptyTitle' => 'No collections in this period',
            'emptyHint' => 'Collections will appear here once rent is received.',
            'tableMinWidth' => '640px',
        ])
    </div>
</x-property.workspace>
