<x-property.workspace
    title="Accounts payable"
    subtitle="Supplier invoice obligations and payment status."
    back-route="property.accounting.index"
    :stats="[['label' => 'Supplier invoices', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Payables ledger']]"
    :columns="['Supplier', 'Invoice No', 'Amount', 'Due Date', 'Status', 'Actions']"
    :table-rows="collect($rows->items())->map(fn($r) => [
        (string) ($r->supplier_name ?? '—'),
        (string) ($r->invoice_no ?? '—'),
        \App\Services\Property\PropertyMoney::kes((float) ($r->amount ?? 0)),
        (string) ($r->due_date ?? '—'),
        ucfirst((string) ($r->status ?? '—')),
        new \Illuminate\Support\HtmlString('<div class=\'flex gap-2\'><span class=\'text-emerald-700\'>Pay supplier</span><span class=\'text-indigo-700\'>View invoice</span><span class=\'text-slate-700\'>Export</span></div>'),
    ])->all()"
>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.accounts_payable') }}" class="flex gap-2">
            <input type="search" name="supplier" value="{{ $filters['supplier'] ?? '' }}" placeholder="Supplier…" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-44" />
            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Status: All</option>
                @foreach (['unpaid','partial','paid'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        What needs action: prioritize unpaid/overdue supplier invoices to avoid service disruption.
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>

