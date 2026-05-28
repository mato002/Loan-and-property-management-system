<x-property.workspace
    title="Utility tenant ledgers"
    subtitle="Per-tenant utility AR balances with drill-down to chronological statements."
    back-route="property.revenue.utilities.reconciliation"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-100 min-h-[44px]">Reconciliation center</a>
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.utilities.ledger', array_merge(request()->query(), ['export' => 'csv']), false),
            'pdfUrl' => route('property.revenue.utilities.ledger', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="flex flex-wrap items-end gap-2 w-full" data-turbo-frame="property-main">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search tenant or phone…" class="flex-1 min-w-[140px] rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" />
            <select name="property" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px] min-w-[160px]">
                <option value="">All properties</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}" @selected(($filters['property'] ?? '') == (string) $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 min-h-[44px]">Apply</button>
            <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 min-h-[44px] inline-flex items-center justify-center">Reset</a>
        </form>
    </x-slot>

    <div class="space-y-4 w-full min-w-0">
        <div class="md:hidden space-y-2">
            @forelse ($summaries as $row)
                <article class="utility-reading-card border-slate-200">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $row['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $row['phone'] ?: 'No phone' }}</p>
                        </div>
                        <p class="text-sm font-bold tabular-nums shrink-0 {{ $row['utility_balance'] > 0 ? 'text-amber-800' : 'text-emerald-700' }}">
                            {{ $row['utility_balance_display'] }}
                        </p>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span class="text-slate-600">{{ $row['open_invoices'] }} open invoice(s)</span>
                        <a href="{{ route('property.tenants.utility.statement', $row['tenant_id'], false) }}" class="font-semibold text-indigo-600 hover:underline min-h-[44px] inline-flex items-center">Statement</a>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500 py-8 text-center">No tenants with utility billing history match your filters.</p>
            @endforelse
        </div>

        <x-property.responsive.table-wrapper class="hidden md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3 text-right">Open utility balance</th>
                        <th class="px-4 py-3 text-right">Open invoices</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['phone'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $row['utility_balance'] > 0 ? 'text-amber-800' : 'text-emerald-700' }}">
                                {{ $row['utility_balance_display'] }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['open_invoices'] }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('property.tenants.utility.statement', $row['tenant_id'], false) }}" class="text-indigo-600 hover:underline text-xs font-medium">Utility statement</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No tenants with utility billing history match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>

        @if ($summaries->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                <p>Showing {{ $summaries->firstItem() ?? 0 }}–{{ $summaries->lastItem() ?? 0 }} of {{ $summaries->total() }}</p>
                {{ $summaries->links() }}
            </div>
        @endif
    </div>
</x-property.workspace>
