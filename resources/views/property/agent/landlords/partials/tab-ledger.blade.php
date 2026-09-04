<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Landlord ledger</h3>
        <p class="text-xs text-slate-500 mt-0.5">Credits from collections and debits from payouts / deposit refunds</p>
    </div>
    @if (($ledgerRows ?? collect())->isEmpty())
        <p class="p-6 text-sm text-slate-500">No ledger entries yet for this landlord.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($ledgerRows as $entry)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ optional($entry->occurred_at)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $entry->property?->name ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs truncate" title="{{ $entry->description }}">{{ $entry->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-700">
                                @if ($entry->direction === 'credit')
                                    {{ \App\Services\Property\PropertyMoney::kes((float) $entry->amount) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-rose-700">
                                @if ($entry->direction === 'debit')
                                    {{ \App\Services\Property\PropertyMoney::kes((float) $entry->amount) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $entry->balance_after) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
