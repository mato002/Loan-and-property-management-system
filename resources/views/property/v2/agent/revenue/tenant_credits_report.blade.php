<x-property.workspace
    title="Tenant advance credits"
    subtitle="Unapplied tenant funds held as credit liability (not suspense)."
    back-route="property.revenue.overview"
    :stats="[
        ['label' => 'Total unapplied', 'value' => \App\Services\Property\PropertyMoney::kes((float) $totalUnapplied), 'hint' => 'All tenants'],
        ['label' => 'Tenants with credit', 'value' => (string) $balances->total(), 'hint' => 'This page'],
    ]"
>

    <div class="mb-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-2">Record advance payment</h2>
        <p class="text-xs text-slate-600 mb-3">
            Use this when a tenant pays cash upfront with <span class="font-medium">no open invoice</span>. Credit auto-applies when invoices are raised.
        </p>
        @include('property.agent.revenue.partials.advance_payment_form', [
            'tenantsForAdvance' => $tenantsForAdvance ?? collect(),
            'advanceCreditsEnabled' => $advanceCreditsEnabled ?? false,
            'returnTo' => 'tenant_credits',
            'alwaysOpen' => true,
        ])
    </div>

    <form method="get" class="mb-4 flex flex-wrap gap-2 items-end" data-turbo-frame="property-main">
        <div>
            <label class="text-xs text-slate-500">Search tenant</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="mt-1 rounded-lg border-slate-300 text-sm" placeholder="Name or phone">
        </div>
        <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Credit balance</th>
                    <th class="px-4 py-3">Updated</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balances as $row)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium">{{ $row->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->tenant?->phone ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums font-semibold text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes((float) $row->balance) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $row->updated_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($row->tenant)
                                <a href="{{ route('property.tenants.credit.ledger', $row->tenant, false) }}" data-turbo-frame="property-main" class="text-indigo-600 font-medium hover:underline">Ledger</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No tenant credit balances.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($balances->hasPages())
            <div class="px-4 py-3 border-t">{{ $balances->links() }}</div>
        @endif
    </div>
</x-property.workspace>
