@php
    $payoutTableRows = $rows->getCollection()->map(function ($payout) {
        $item = $payout->items->first();
        $landlord = $item?->landlord;
        $propertyLabel = $item?->property?->name
            ?? ($item?->description ? \Illuminate\Support\Str::before($item->description, ' (') : '—');
        $landlordProperty = trim(($landlord?->name ?? '—').' / '.($propertyLabel ?? '—'));

        $actions = '<div class="flex flex-wrap gap-2">';
        if ($payout->status === 'draft') {
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.approve', $payout)).'" class="inline">'
                .csrf_field()
                .'<button type="submit" class="text-emerald-700 hover:text-emerald-800">Approve</button></form>';
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.pay', $payout)).'" class="inline" onsubmit="return confirm(\'Mark this payout as paid and post to ledger?\')">'
                .csrf_field()
                .'<button type="submit" class="text-indigo-700 hover:text-indigo-800">Mark paid</button></form>';
        } elseif ($payout->status === 'approved') {
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.pay', $payout)).'" class="inline" onsubmit="return confirm(\'Mark this payout as paid and post to ledger?\')">'
                .csrf_field()
                .'<button type="submit" class="text-indigo-700 hover:text-indigo-800">Mark paid</button></form>';
        } else {
            $actions .= '<span class="text-slate-500">—</span>';
        }
        $actions .= '</div>';

        return [
            '#'.(string) $payout->id,
            optional($payout->created_at)->format('Y-m-d') ?? '—',
            $landlordProperty,
            \App\Services\Property\PropertyMoney::kes((float) $payout->total_amount),
            ucfirst((string) $payout->status),
            (string) ($payout->approved_by ?? '—'),
            optional($payout->paid_at)->format('Y-m-d H:i') ?? '—',
            new \Illuminate\Support\HtmlString($actions),
        ];
    })->all();
@endphp
<x-property.workspace
    title="Landlord payouts"
    subtitle="Payout lifecycle: draft, approved, paid, and ledger posting."
    back-route="property.accounting.index"
    :stats="[['label' => 'Payouts', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'All statuses']]"
    :columns="['Payout ID', 'Date', 'Landlord / Property', 'Total Amount', 'Status', 'Approved By', 'Paid At', 'Actions']"
    :table-rows="$payoutTableRows"
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.payables.landlord_settlements') }}" data-turbo-frame="property-main" class="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New from settlement</a>
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
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        Approve payouts after review, then mark paid once disbursed — this posts a landlord ledger debit and trust GL entry.
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>
