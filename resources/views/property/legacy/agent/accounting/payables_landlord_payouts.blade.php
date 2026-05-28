<x-property.workspace
    title="Landlord payouts"
    subtitle="Payout lifecycle: draft, approved, paid, and reversal control."
    back-route="property.accounting.index"
    :stats="[['label' => 'Payouts', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'All statuses']]"
    :columns="['Payout ID', 'Date', 'Total Amount', 'Status', 'Approved By', 'Paid At', 'Actions']"
    :table-rows="$rows->getCollection()->map(fn($r) => [
        '#'.(string) $r->id,
        optional($r->created_at)->format('Y-m-d') ?? '—',
        \App\Services\Property\PropertyMoney::kes((float) $r->total_amount),
        ucfirst((string) $r->status),
        (string) ($r->approved_by ?? '—'),
        optional($r->paid_at)->format('Y-m-d H:i') ?? '—',
        new \Illuminate\Support\HtmlString('<div class=\'flex gap-2\'><span class=\'text-emerald-700\'>Approve payout</span><span class=\'text-indigo-700\'>Mark as paid</span><span class=\'text-rose-700\'>Reverse payout</span></div>'),
    ])->all()"
>
    <x-slot name="actions">
        <span class="inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-600">Create/Approve/Mark paid via workflow controls</span>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.landlord_payouts') }}" class="flex gap-2">
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status: All</option>
                @foreach (['draft','approved','paid'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        Needs action: Draft/approved payouts should be reviewed and moved to paid once disbursed.
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>

