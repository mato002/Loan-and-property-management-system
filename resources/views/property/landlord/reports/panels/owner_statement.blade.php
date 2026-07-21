<form method="get" action="{{ route('property.landlord.reports.index') }}" class="rounded-xl border border-slate-200 dark:border-slate-700 p-3 sm:p-4 grid grid-cols-2 sm:flex sm:flex-wrap items-end gap-2 sm:gap-3 mb-4 w-full min-w-0">
    <input type="hidden" name="panel" value="owner_statement" />
    <div class="col-span-1 min-w-0">
        <label class="block text-xs text-slate-500 mb-1">Month</label>
        <input type="month" name="month" value="{{ request('month', $month) }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm" />
    </div>
    <div class="col-span-1 min-w-0">
        <label class="block text-xs text-slate-500 mb-1">Financial year</label>
        <input type="number" name="fy" value="{{ request('fy', $fy) }}" min="2000" max="2100" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm sm:w-28" />
    </div>
    <button type="submit" class="col-span-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Apply</button>
    <a href="{{ route('property.landlord.reports.owner_statement.pdf', array_filter(['month' => request('month', $month), 'fy' => request('fy', $fy)])) }}" data-turbo="false" class="col-span-1 rounded-lg border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm text-center">Download PDF</a>
</form>

<x-property.landlord.kpi-grid class="mb-4">
    <x-property.landlord.kpi-card label="Period" :value="$snapshot['periodLabel']" />
    <x-property.landlord.kpi-card label="Owner share collected" :value="\App\Services\Property\PropertyMoney::kes((float) ($snapshot['totals']['owner_share'] ?? 0))" />
    <x-property.landlord.kpi-card label="Management fees" :value="\App\Services\Property\PropertyMoney::kes((float) ($snapshot['totals']['management_fees'] ?? 0))" />
    <x-property.landlord.kpi-card label="Net to owner" :value="\App\Services\Property\PropertyMoney::kes((float) ($snapshot['totals']['net_to_owner'] ?? 0))" emphasis />
    <x-property.landlord.kpi-card label="Pending tenant AR (your share)" :value="\App\Services\Property\PropertyMoney::kes((float) ($snapshot['totals']['pending_share'] ?? 0))" />
    <x-property.landlord.kpi-card label="Ledger balance" :value="$ledgerBalance" />
</x-property.landlord.kpi-grid>

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-x-auto mb-4">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Property</th>
                <th class="px-4 py-3">Ownership</th>
                <th class="px-4 py-3">Collected</th>
                <th class="px-4 py-3">Mgmt fee</th>
                <th class="px-4 py-3">Net</th>
                <th class="px-4 py-3">Pending AR</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($snapshot['propertyBreakdown'] as $row)
                <tr class="border-t border-slate-100 dark:border-slate-700/70">
                    <td class="px-4 py-2 font-medium">{{ $row['property_name'] }}</td>
                    <td class="px-4 py-2">{{ number_format((float) $row['ownership_percent'], 2) }}%</td>
                    <td class="px-4 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['owner_share']) }}</td>
                    <td class="px-4 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['management_fee']) }}</td>
                    <td class="px-4 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['net_to_owner']) }}</td>
                    <td class="px-4 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row['pending_share']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($snapshot['recentCollections']->isNotEmpty())
    <h3 class="text-sm font-semibold mb-2">Recent collections</h3>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-x-auto mb-4">
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-slate-500 bg-slate-50 dark:bg-slate-900/60"><tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Tenant</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2">Channel</th><th class="px-3 py-2">Amount</th></tr></thead>
            <tbody>
                @foreach ($snapshot['recentCollections'] as $c)
                    <tr class="border-t border-slate-100 dark:border-slate-700/70"><td class="px-3 py-2">{{ $c->paid_at ? \Illuminate\Support\Carbon::parse($c->paid_at)->format('Y-m-d') : '—' }}</td><td class="px-3 py-2">{{ $c->tenant_name ?? '—' }}</td><td class="px-3 py-2">{{ $c->property_name ?? '' }} / {{ $c->unit_label ?? '' }}</td><td class="px-3 py-2">{{ ucfirst($c->channel ?? '—') }}</td><td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($c->amount ?? 0)) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if (preg_match('/^\d{4}-\d{2}$/', (string) request('month', $month)))
    <form method="post" action="{{ route('property.landlord.reports.owner_statement.acknowledge') }}" class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-4" data-swal-confirm="Acknowledge this statement period?">
        @csrf
        <input type="hidden" name="month" value="{{ request('month', $month) }}" />
        <p class="text-sm text-slate-600 dark:text-slate-300 mb-2">Acknowledged: {{ $profile->last_acknowledged_statement_month ?: 'Never' }}</p>
        <button type="submit" class="rounded-lg bg-slate-800 text-white px-4 py-2 text-sm">Acknowledge {{ request('month', $month) }}</button>
    </form>
@endif
