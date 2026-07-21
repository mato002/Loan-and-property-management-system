<x-property.workspace
    title="Accounting reconciliation"
    subtitle="Read-only visibility for missing GL issuance, landlord ledger gaps, suspense corruption risk, allocation/GL drift, and impossible accounting states."
    back-route="property.accounting.index"
    :stats="$stats"
>
    <div class="space-y-4">
        @if (($snapshot['ready'] ?? false) !== true)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                {{ $snapshot['message'] ?? 'Accounting journal tables are not available.' }}
            </div>
        @endif

        <form method="get" action="{{ route('property.accounting.reconciliation') }}" class="flex flex-wrap items-end gap-3">
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
            <a href="{{ route('property.accounting.reconciliation') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Clear</a>
            <a href="{{ route('property.accounting.finance_diagnostics') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Operational finance diagnostics</a>
            <a href="{{ route('property.accounting.financial_reconciliation') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Financial reconciliation engine</a>
        </form>

        <p class="text-xs text-slate-500">
            Detection only — no posting or chart-of-accounts changes. Run <code class="text-xs">php artisan finance:detect-accounting-drift --audit</code> and <code class="text-xs">php artisan finance:detect-reversal-drift --audit</code> to persist immutable audit logs.
            Carry-forward GL backfill: <code class="text-xs">php artisan finance:backfill-carry-forward-gl</code>.
            Landlord subledger backfill: <code class="text-xs">php artisan finance:backfill-landlord-subledger</code>.
        </p>

        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Accounting drift</h2>
        @foreach ($sections as $key => $heading)
            @php($rows = $snapshot[$key] ?? collect())
            <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($rows->isNotEmpty()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $heading }}
                    <span class="ml-2 rounded-full {{ $rows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $rows->count() }}</span>
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
                                            <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' || $value === null ? '—' : $value) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        @endforeach

        @if (! empty($reversalSections))
            <h2 class="pt-2 text-sm font-semibold text-slate-900 dark:text-white">Reversal integrity (Phase 16)</h2>
            @foreach ($reversalSections as $key => $heading)
                @php($rows = ($reversalSnapshot ?? [])[$key] ?? collect())
                <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($rows->isNotEmpty()) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                        {{ $heading }}
                        <span class="ml-2 rounded-full {{ $rows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $rows->count() }}</span>
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
                                                <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' || $value === null ? '—' : $value) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </details>
            @endforeach
        @endif

        @php($cfRows = $carryForwardArDrift ?? collect())
        <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($cfRows->isNotEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                Carry-forward operational AR vs Trust GL AR
                <span class="ml-2 rounded-full {{ $cfRows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $cfRows->count() }}</span>
            </summary>
            @if ($cfRows->isEmpty())
                <p class="mt-3 text-sm text-emerald-700">Carry-forward operational AR aligns with Trust GL issuance.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                @foreach (array_keys($cfRows->first()) as $column)
                                    <th class="px-2 py-2 font-medium">{{ str_replace('_', ' ', $column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cfRows as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                    @foreach ($row as $value)
                                        <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' || $value === null ? '—' : $value) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </details>

        @php($glDriftRows = $landlordGlDrift ?? collect())
        <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($glDriftRows->isNotEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                GL 2100 landlord payable vs subledger
                <span class="ml-2 rounded-full {{ $glDriftRows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $glDriftRows->count() }}</span>
            </summary>
            @if ($glDriftRows->isEmpty())
                <p class="mt-3 text-sm text-emerald-700">GL 2100 net aligns with landlord subledger totals.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                @foreach (array_keys($glDriftRows->first()) as $column)
                                    <th class="px-2 py-2 font-medium">{{ str_replace('_', ' ', $column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($glDriftRows as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                    @foreach ($row as $value)
                                        <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' || $value === null ? '—' : $value) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </details>

        @php($dupRows = $duplicateLandlordCredits ?? collect())
        <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($dupRows->isNotEmpty()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                Duplicate landlord owner credits
                <span class="ml-2 rounded-full {{ $dupRows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $dupRows->count() }}</span>
            </summary>
            @if ($dupRows->isEmpty())
                <p class="mt-3 text-sm text-emerald-700">No duplicate owner credits detected.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                @foreach (array_keys($dupRows->first()) as $column)
                                    <th class="px-2 py-2 font-medium">{{ str_replace('_', ' ', $column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dupRows as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                    @foreach ($row as $value)
                                        <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : ($value === '' || $value === null ? '—' : $value) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </details>

        <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                Recent accounting audit logs
                <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $auditLogs->count() }}</span>
            </summary>
            @if ($auditLogs->isEmpty())
                <p class="mt-3 text-sm text-slate-600">No accounting audit logs yet. Run the drift command with <code class="text-xs">--audit</code>.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-2 py-2 font-medium">occurred at</th>
                                <th class="px-2 py-2 font-medium">action</th>
                                <th class="px-2 py-2 font-medium">entity</th>
                                <th class="px-2 py-2 font-medium">summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditLogs as $log)
                                <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                    <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ optional($log->occurred_at)->toDateTimeString() }}</td>
                                    <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ $log->action }}</td>
                                    <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ $log->entity_type }} #{{ $log->entity_id ?? '—' }}</td>
                                    <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ $log->summary }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </details>
    </div>
</x-property.workspace>
