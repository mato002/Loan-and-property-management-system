@php
    $payoutTableRows = $rows->getCollection()->map(function ($payout) use ($b2cConfigured) {
        $item = $payout->items->first();
        $landlord = $item?->landlord;
        $propertyLabel = $item?->property?->name
            ?? ($item?->description ? \Illuminate\Support\Str::before($item->description, ' (') : '—');
        $landlordProperty = trim(($landlord?->name ?? '—').' / '.($propertyLabel ?? '—'));
        $defaultPhone = (string) ($payout->payout_phone ?: ($landlord?->phone ?? ''));
        $mpesaStatus = (string) ($payout->payout_status ?? '');

        $actions = '<div class="flex flex-col gap-2 min-w-[12rem]">';
        if ($payout->status === 'draft') {
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.approve', $payout)).'" class="inline">'
                .csrf_field()
                .'<button type="submit" class="text-emerald-700 hover:text-emerald-800">Approve</button></form>';
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.void', $payout)).'" class="inline" onsubmit="return confirm(\'Void this draft payout? This cannot be undone.\')">'
                .csrf_field()
                .'<button type="submit" class="text-rose-700 hover:text-rose-800">Void draft</button></form>';
        }
        if (in_array($payout->status, ['draft', 'approved'], true) && $mpesaStatus !== 'pending') {
            $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.pay', $payout)).'" class="inline" onsubmit="return confirm(\'Mark this payout as paid and post to ledger?\')">'
                .csrf_field()
                .'<button type="submit" class="text-indigo-700 hover:text-indigo-800">Mark paid (manual)</button></form>';
            if ($b2cConfigured ?? false) {
                $actions .= '<form method="post" action="'.e(route('property.accounting.payables.landlord_payouts.pay_mpesa', $payout)).'" class="space-y-1" onsubmit="return confirm(\'Send this payout via M-Pesa B2C?\')">'
                    .csrf_field()
                    .'<input type="text" name="mpesa_phone" value="'.e($defaultPhone).'" placeholder="07… / 2547…" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" required />'
                    .'<button type="submit" class="text-teal-700 hover:text-teal-800 font-semibold">Pay via M-Pesa B2C</button></form>';
            }
        } elseif ($mpesaStatus === 'pending') {
            $actions .= '<span class="text-amber-700 text-xs font-semibold">B2C pending…</span>';
        } elseif ($payout->status !== 'draft') {
            $actions .= '<span class="text-slate-500">—</span>';
        }
        if ($payout->payout_transaction_id) {
            $actions .= '<span class="text-xs text-slate-500 font-mono">Txn: '.e($payout->payout_transaction_id).'</span>';
        }
        $actions .= '</div>';

        $statusLabel = ucfirst((string) $payout->status);
        if ($mpesaStatus !== '') {
            $statusLabel .= ' / M-Pesa '.ucfirst($mpesaStatus);
        }

        return [
            '#'.(string) $payout->id,
            optional($payout->created_at)->format('Y-m-d') ?? '—',
            $landlordProperty,
            \App\Services\Property\PropertyMoney::kes((float) $payout->total_amount),
            $statusLabel,
            (string) ($payout->approved_by ?? '—'),
            optional($payout->paid_at)->format('Y-m-d H:i') ?? '—',
            new \Illuminate\Support\HtmlString($actions),
        ];
    })->all();
@endphp
<x-property.workspace
    title="Landlord payouts"
    subtitle="Payout lifecycle: draft, approved, paid — manual mark-paid or M-Pesa B2C."
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
                @foreach (['draft','approved','paid','pending'] as $st)
                    <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $st === 'pending' ? 'M-Pesa pending' : ucfirst($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        Approve payouts after review. Use <strong>Pay via M-Pesa B2C</strong> to send funds to the landlord phone (ledger posts on Safaricom callback), or <strong>Mark paid</strong> if you already disbursed outside the system.
        @unless ($b2cConfigured ?? false)
            <span class="block mt-1">B2C not configured — set <code class="font-mono text-xs">MPESA_B2C_*</code> in <code class="font-mono text-xs">.env</code>.</span>
        @endunless
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>
