@php
    $lineTotal = (float) $voucher->lines->sum('line_total');
@endphp
<x-property.workspace
    :title="'Voucher '.$voucher->ezen_voucher_no"
    subtitle="{{ $voucher->displayExpenseGroup() }} · {{ $voucher->displayLinkStatus() }}"
    back-route="property.accounting.payables.payment_vouchers"
    :stats="[
        ['label' => 'Amount', 'value' => number_format((float) $voucher->amount, 2), 'hint' => 'Control amount'],
        ['label' => 'Tax', 'value' => number_format((float) $voucher->tax_amount, 2), 'hint' => 'On lines'],
        ['label' => 'Date', 'value' => $voucher->txn_date?->format('Y-m-d') ?? '—', 'hint' => 'Voucher date'],
        ['label' => 'Status', 'value' => $voucher->displayLinkStatus(), 'hint' => $voucher->source === 'recorded' ? 'Recorded here' : 'Imported'],
    ]"
    :columns="[]"
>
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 text-sm space-y-2">
            <p><span class="text-slate-500">Payee:</span> {{ $voucher->displayPayee() }}</p>
            <p><span class="text-slate-500">Paid from:</span> {{ $voucher->paid_from ?: '—' }}</p>
            <p><span class="text-slate-500">Method:</span> {{ $voucher->method ?: '—' }}</p>
            <p><span class="text-slate-500">Reference:</span> {{ $voucher->ref_no ?: '—' }}</p>
            <p><span class="text-slate-500">Cheque:</span> {{ $voucher->cheque_no ?: '—' }} {{ $voucher->cheque_date?->format('Y-m-d') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 text-sm space-y-2">
            <p><span class="text-slate-500">Expense group:</span> {{ $voucher->displayExpenseGroup() }}</p>
            <p><span class="text-slate-500">Narration:</span> {{ $voucher->narration ?: ($voucher->particulars ?: '—') }}</p>
            <p><span class="text-slate-500">Property:</span> {{ $voucher->property?->name ?: ($voucher->property_code ?: 'Unassigned') }}</p>
            <p><span class="text-slate-500">Unit:</span> {{ $voucher->unit?->label ?: ($voucher->property_id ? 'Whole property' : '—') }}</p>
            <div class="pt-2">
                @include('property.agent.partials.payment_voucher_property_fields', [
                    'voucher' => $voucher,
                    'properties' => $properties ?? collect(),
                    'units' => $units ?? collect(),
                    'selectedPropertyId' => (int) ($voucher->property_id ?? 0),
                ])
            </div>
            <p><span class="text-slate-500">Landlord:</span> {{ $voucher->landlord?->name ?: '—' }}</p>
            <p><span class="text-slate-500">Notes:</span> {{ $voucher->notes ?: '—' }}</p>
            @if ($voucher->pm_landlord_payout_id)
                <p><a href="{{ route('property.accounting.payables.landlord_payouts', ['q' => $voucher->pm_landlord_payout_id], false) }}" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">Open payout PAY-{{ $voucher->pm_landlord_payout_id }}</a></p>
            @endif
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Property</th>
                    <th class="px-3 py-2">Expense group</th>
                    <th class="px-3 py-2">Utility account</th>
                    <th class="px-3 py-2">Description</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2 text-right">Tax</th>
                    <th class="px-3 py-2 text-right">Line total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($voucher->lines as $line)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $line->property?->name ?: '—' }}</td>
                        <td class="px-3 py-2">{{ \App\Models\PmEzenPaymentVoucher::EXPENSE_GROUPS[$line->expense_group]['label'] ?? ($line->expense_group ?: '—') }}</td>
                        <td class="px-3 py-2">{{ $line->utility_account ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $line->description ?: '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->amount, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->tax_amount, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $line->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-4 text-slate-500">No line breakdown. This voucher was imported as a single amount.</td></tr>
                @endforelse
            </tbody>
            @if ($voucher->lines->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-slate-200 font-semibold">
                        <td colspan="6" class="px-3 py-2 text-right">Line totals</td>
                        <td class="px-3 py-2 text-right">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-property.workspace>
