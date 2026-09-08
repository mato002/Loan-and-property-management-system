@php
    $depositSnapshot = $depositSnapshot ?? ['held' => 0.0, 'expected' => 0.0, 'lines' => []];
@endphp
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Deposits</h3>
            <p class="text-xs text-slate-500 mt-0.5">
                Held {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['held'] ?? 0)) }}
                · expected on leases {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['expected'] ?? 0)) }}
            </p>
        </div>
        <a href="{{ route('property.reports.tenant.deposits', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Deposit report</a>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Item</th>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($depositSnapshot['lines'] ?? []) as $line)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-3">{{ $line['label'] ?? '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ $line['source'] ?? '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($line['status'] ?? '—')) }}</td>
                    <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['amount'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No rent or security deposits recorded for this tenant.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
