<x-property.workspace
    title="Utility period closing"
    subtitle="Monthly billing periods, reconciliation gates, and financial lock control."
    back-route="property.revenue.utilities"
    :stats="$stats"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 hover:bg-teal-100">Reconciliation</a>
    </x-slot>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3 bg-slate-50">
            <h2 class="text-sm font-semibold text-slate-800">Billing periods (last 18 months)</h2>
            <p class="text-xs text-slate-500 mt-0.5">Closed periods block readings, invoice reversals, allocation changes, and penalty edits unless a supervisor override is approved.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Month</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Closed</th>
                        <th class="px-4 py-3 text-left">Closed by</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($periods as $period)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $period->billing_month }}</td>
                            <td class="px-4 py-3">
                                @if ($period->isClosed())
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">Closed</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Open</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $period->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $period->closedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('property.revenue.utilities.periods.show', ['billingMonth' => $period->billing_month], false) }}" class="text-teal-700 hover:underline font-medium">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No billing periods found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-property.workspace>
