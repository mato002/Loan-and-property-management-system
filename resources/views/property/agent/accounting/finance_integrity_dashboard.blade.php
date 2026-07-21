<x-property.workspace
    title="Finance integrity"
    subtitle="Continuous drift detection — allocation, suspense, GL AR, landlord ledger, tenant credits, penalties, orphan allocations, and stale carry-forward."
    back-route="property.accounting.index"
    :stats="$stats"
>
    <div class="space-y-4">
        <form method="get" action="{{ route('property.accounting.finance_integrity') }}" class="flex flex-wrap items-end gap-3">
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
            <a href="{{ route('property.accounting.finance_integrity') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Clear</a>
            <a href="{{ route('property.accounting.financial_reconciliation') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Financial reconciliation layers</a>
            <a href="{{ route('property.accounting.reconciliation') }}" data-turbo-frame="property-main" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:underline">Accounting firebreak</a>
        </form>

        <p class="text-xs text-slate-500">
            Severity:
            <span class="rounded bg-rose-200 px-1 text-rose-900">critical</span> drift &gt; KES 1,000 (or high carry-forward),
            <span class="rounded bg-amber-200 px-1 text-amber-900">warning</span> &gt; KES 100,
            <span class="rounded bg-sky-200 px-1 text-sky-900">info</span> above tolerance.
            Scheduled scans: hourly allocation &amp; suspense; daily AR, landlord, tenant credit, and penalty GL checks.
            Critical alerts via Slack/email when configured.
        </p>

        @if (($summary['total_issues'] ?? 0) === 0)
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
                No active finance drift detected across {{ count($report['categories'] ?? []) }} integrity categories.
            </div>
        @elseif (($summary['critical'] ?? 0) > 0)
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
                <strong>{{ (int) $summary['critical'] }} critical</strong> drift issue(s) require immediate review.
                Run <code class="text-xs">php artisan finance:reconcile --scope=all --audit --alert</code> or scoped hourly/daily jobs.
            </div>
        @endif

        @foreach ($report['categories'] ?? [] as $category)
            @php($rows = $category['rows'] ?? collect())
            @php($catSummary = $category['summary'] ?? [])
            <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4" @if($rows->isNotEmpty()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $category['label'] ?? $category['key'] ?? 'Category' }}
                    <span class="ml-2 rounded-full {{ $rows->isNotEmpty() ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-medium">{{ $rows->count() }}</span>
                    <span class="ml-1 text-xs font-normal text-slate-500">
                        (C:{{ $catSummary['critical'] ?? 0 }} W:{{ $catSummary['warning'] ?? 0 }} I:{{ $catSummary['info'] ?? 0 }}
                        · tenants:{{ $catSummary['affected_tenants'] ?? 0 }} invoices:{{ $catSummary['affected_invoices'] ?? 0 }})
                    </span>
                </summary>
                @if (! empty($category['repair_recommendation']))
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400"><strong>Repair recommendation:</strong> {{ $category['repair_recommendation'] }}</p>
                @endif
                @if ($rows->isEmpty())
                    <p class="mt-3 text-sm text-emerald-700">No active drift in this category.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-2 py-2 font-medium">severity</th>
                                    <th class="px-2 py-2 font-medium">tenant</th>
                                    <th class="px-2 py-2 font-medium">invoice</th>
                                    <th class="px-2 py-2 font-medium">entity</th>
                                    <th class="px-2 py-2 font-medium">drift</th>
                                    <th class="px-2 py-2 font-medium">message</th>
                                    <th class="px-2 py-2 font-medium">repair</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-slate-100 dark:border-slate-700/60">
                                        <td class="px-2 py-2 align-top">
                                            <span class="rounded px-1.5 py-0.5 text-xs font-medium {{ $severityColors[$row['severity'] ?? 'info'] ?? 'bg-slate-100 text-slate-700' }}">
                                                {{ $row['severity'] ?? 'info' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-2 align-top">{{ $row['tenant_id'] ?? '—' }}</td>
                                        <td class="px-2 py-2 align-top">{{ $row['invoice_id'] ?? '—' }}</td>
                                        <td class="px-2 py-2 align-top">{{ ($row['entity_type'] ?? '—').' #'.($row['entity_id'] ?? '—') }}</td>
                                        <td class="px-2 py-2 align-top tabular-nums">{{ isset($row['drift']) ? number_format((float) $row['drift'], 2) : '—' }}</td>
                                        <td class="px-2 py-2 align-top text-slate-800 dark:text-slate-200">{{ $row['message'] ?? '—' }}</td>
                                        <td class="px-2 py-2 align-top text-xs text-slate-600 dark:text-slate-400">{{ $row['repair_recommendation'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        @endforeach

        <details class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">
                Recent integrity audit logs
                <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $auditLogs->count() }}</span>
            </summary>
            @if ($auditLogs->isEmpty())
                <p class="mt-3 text-sm text-slate-600">No integrity audit logs yet. Run scheduled scans with <code class="text-xs">--audit</code>.</p>
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
