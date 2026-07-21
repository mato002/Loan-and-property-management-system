<x-property.workspace
    title="Finance diagnostics"
    subtitle="Read-only firebreak visibility for allocation drift, carry-forward duplication, and stale balances."
    back-route="property.accounting.index"
    :stats="$stats"
>
    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="space-y-4">
        <form method="post" action="{{ route('property.accounting.finance_diagnostics.refresh_invoice_statuses') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-gray-800/80">
            @csrf
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Refresh invoice statuses</p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Manual admin action · recompute stale open invoice statuses (scheduler runs daily).</p>
            </div>
            <input type="hidden" name="limit" value="2000" />
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Run refresh</button>
        </form>

        <form method="get" action="{{ route('property.accounting.finance_diagnostics') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="tenant" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant ID filter</label>
                <input
                    id="tenant"
                    type="number"
                    min="0"
                    name="tenant"
                    value="{{ $tenantFilter ?? '' }}"
                    class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="All tenants"
                />
            </div>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply filter</button>
            <a href="{{ route('property.accounting.finance_diagnostics') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Clear</a>
            <a href="{{ route('property.accounting.reconciliation') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Accounting reconciliation</a>
        </form>

        @foreach ([
            'allocation_drift' => 'Invoices with allocation drift',
            'duplicated_carry_forward' => 'Duplicated carry-forward',
            'recreated_after_payment' => 'Paid carry-forward preserved (skipped recreation)',
            'stale_opening_arrears' => 'Stale tenant opening arrears',
            'partial_overdue' => 'Partial overdue invoices',
            'orphan_allocations' => 'Orphan allocations',
        ] as $key => $heading)
            @php($rows = $snapshot[$key] ?? collect())
            <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($rows->isNotEmpty()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $heading }}
                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $rows->count() }}</span>
                </summary>
                @if ($rows->isEmpty())
                    <p class="mt-3 text-sm text-emerald-700">No issues detected in this category.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    @foreach (array_keys($rows->first()) as $column)
                                        <th class="px-2 py-2 font-medium">{{ str_replace('_', ' ', $column) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                        @foreach ($row as $value)
                                            <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' ? '—' : $value) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        @endforeach
    </div>
</x-property.workspace>
