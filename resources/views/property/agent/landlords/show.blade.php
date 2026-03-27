<x-property.workspace
    :title="'Landlord: '.$landlord->name"
    :subtitle="'Single-landlord view with portfolio share, owner payout position, and your earnings. Period: '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[
        ['label' => 'Properties linked', 'value' => (string) ($totals['properties'] ?? 0), 'hint' => 'Current'],
        ['label' => 'Ownership total', 'value' => number_format((float) ($totals['ownership_sum'] ?? 0), 2).'%', 'hint' => 'Across linked properties'],
        ['label' => 'Owner share', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)), 'hint' => $periodLabel],
        ['label' => 'Pending share', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)), 'hint' => 'Receivables-based'],
        ['label' => 'Your earnings', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)), 'hint' => 'At '.number_format((float) ($commissionPct ?? 0), 2).'%'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlords.index', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Back to landlords
        </a>
        <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue, 'export' => 'csv'], false) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue, 'export' => 'pdf'], false) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PDF</a>
        <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue, 'export' => 'word'], false) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Word</a>
        <a href="{{ route('property.landlords.statement', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Statement</a>
        <a href="mailto:{{ $landlord->email }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Email landlord
        </a>
        <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Owner balances
        </a>
        <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Commission report
        </a>
    </x-slot>

    <x-slot name="above">
        <form method="get" action="{{ route('property.landlords.show', ['landlord' => $landlord->id]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-2 max-w-3xl">
            <div>
                <label class="block text-xs font-medium text-slate-600">Month</label>
                <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">FY</label>
                <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 w-28" />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply period</button>
            <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>

        <div class="mt-3 text-xs text-slate-500">Use tabs below to switch sections.</div>
    </x-slot>

    <div x-data="{ tab: 'profile' }" class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="tab='profile'" :class="tab==='profile' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-3 py-1.5 text-xs font-medium">Profile</button>
            <button type="button" @click="tab='breakdown'" :class="tab==='breakdown' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-3 py-1.5 text-xs font-medium">Portfolio & Shares</button>
            <button type="button" @click="tab='collections'" :class="tab==='collections' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-3 py-1.5 text-xs font-medium">Collections</button>
            <button type="button" @click="tab='directives'" :class="tab==='directives' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300'" class="rounded-lg border px-3 py-1.5 text-xs font-medium">Directives</button>
        </div>

    <div id="section-profile" x-show="tab==='profile'" class="grid grid-cols-1 lg:grid-cols-2 gap-4 scroll-mt-20">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Profile</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Name:</span> {{ $landlord->name }}</p>
                <p><span class="text-slate-500">Email:</span> {{ $landlord->email }}</p>
                <p><span class="text-slate-500">Role:</span> landlord</p>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Directives</h3>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Adjust property links</a>
                <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Owner statement lens</a>
                <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Earnings lens</a>
            </div>
        </div>
    </div>

    <div id="section-breakdown" x-show="tab==='breakdown'" class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto scroll-mt-20">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Property breakdown ({{ $periodLabel }})</h3>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Property</th>
                    <th class="px-4 py-3">Ownership %</th>
                    <th class="px-4 py-3">Owner share</th>
                    <th class="px-4 py-3">Pending share</th>
                    <th class="px-4 py-3">Your earnings</th>
                    <th class="px-4 py-3">Last collection</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($propertyBreakdown as $row)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $row['property_name'] }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ number_format((float) $row['ownership_percent'], 2) }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['owner_share']) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['pending_share']) }}</td>
                        <td class="px-4 py-3 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $row['agent_earning']) }}</td>
                        <td class="px-4 py-3">{{ !empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('property.properties.show', ['property' => $row['property_id']], false) }}" data-turbo-frame="_top" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">View property</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">This landlord has no linked properties yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="section-collections" x-show="tab==='collections'" class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto scroll-mt-20">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Recent collections touching this landlord's properties ({{ $periodLabel }})</h3>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Ref</th>
                    <th class="px-4 py-3">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentCollections as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $c->paid_at ? \Illuminate\Support\Carbon::parse((string) $c->paid_at)->format('Y-m-d H:i') : '—' }}</td>
                        <td class="px-4 py-3">{{ $c->tenant_name ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ $c->channel ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $c->external_ref ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($c->amount ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">No collections in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div id="section-directives" x-show="tab==='directives'" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm scroll-mt-20">
        <h3 class="text-sm font-semibold text-slate-900">Directives</h3>
        <p class="mt-1 text-sm text-slate-600">Quick actions focused on this landlord.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Adjust property links</a>
            <a href="{{ route('property.financials.owner_balances', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Owner statement lens</a>
            <a href="{{ route('property.financials.commission', ['month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Earnings lens</a>
            <a href="{{ route('property.landlords.statement', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50">Open printable statement</a>
        </div>
    </div>
    </div>
</x-property.workspace>

