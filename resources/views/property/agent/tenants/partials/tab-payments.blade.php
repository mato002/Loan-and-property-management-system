<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Payments</h3>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700" data-property-modal-open="showHubPaymentForm" @click="showHubPaymentForm = true">Record payment</button>
            <button type="button" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-property-modal-open="showHubAdvanceForm" @click="showHubAdvanceForm = true">Record advance</button>
            <a href="{{ route('property.revenue.payments', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-slate-600 hover:underline self-center">Payment register</a>
            <a href="{{ route('property.tenants.statement', ['tenant' => $tenant->id, 'export' => 'pdf'], false) }}" data-turbo="false" class="text-xs font-semibold text-slate-700 hover:underline self-center">Statement PDF</a>
        </div>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Channel</th>
                <th class="px-4 py-3">Reference</th>
                <th class="px-4 py-3">Applied to</th>
                <th class="px-4 py-3">Receipt</th>
            </tr>
        </thead>
        <tbody>
            @php
                $creditHeldByPayment = app(\App\Services\Property\TenantCreditService::class)->openCreditByPaymentIds(
                    collect($recentPayments ?? [])->pluck('id')->all()
                );
            @endphp
            @forelse(($recentPayments ?? []) as $payment)
                @php
                    $statementLines = collect($statementApplications[(int) $payment->id] ?? [])
                        ->filter(fn ($line) => (float) ($line['amount'] ?? 0) > 0.009)
                        ->values();
                    $appliedRows = collect($payment->allocations ?? [])
                        ->filter(fn ($allocation) => ! ($allocation->is_reversed ?? false) && (float) $allocation->amount > 0.009);
                    $usesStatement = $statementLines->isNotEmpty();
                    $appliedSum = $usesStatement
                        ? round((float) $statementLines->sum('amount'), 2)
                        : round((float) $appliedRows->sum('amount'), 2);
                    $leftover = round(max(0, (float) $payment->amount - $appliedSum), 2);
                    $heldAsCredit = round(min($leftover, (float) ($creditHeldByPayment[(int) $payment->id] ?? 0)), 2);
                @endphp
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</td>
                    <td class="px-4 py-3 uppercase">{{ $payment->channel ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $payment->external_ref ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs">
                        @if($usesStatement)
                            <ul class="space-y-0.5">
                                @foreach($statementLines as $line)
                                    <li>
                                        <span class="font-medium text-slate-800">{{ $line['invoice_no'] }}</span>
                                        <span class="text-slate-500"> · {{ $line['label'] }}</span>
                                        <span class="tabular-nums text-slate-700"> · {{ \App\Services\Property\PropertyMoney::kes((float) $line['amount']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($appliedRows->isEmpty())
                            <span class="font-medium text-slate-800">Not applied to an invoice line</span>
                        @else
                            <ul class="space-y-0.5">
                                @foreach($appliedRows as $allocation)
                                    @php
                                        $invoice = $allocation->invoice;
                                        $typeLabel = $invoice?->chargeCategoryLabel() ?? 'Invoice';
                                        $invoiceNo = $invoice?->invoice_no ?: ($invoice ? '#'.$invoice->id : 'Missing invoice');
                                    @endphp
                                    <li>
                                        <span class="font-medium text-slate-800">{{ $invoiceNo }}</span>
                                        <span class="text-slate-500"> · {{ $typeLabel }}</span>
                                        <span class="tabular-nums text-slate-700"> · {{ \App\Services\Property\PropertyMoney::kes((float) $allocation->amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($heldAsCredit > 0.009)
                            <div class="mt-1 font-medium text-amber-800">Held as credit {{ \App\Services\Property\PropertyMoney::kes($heldAsCredit) }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('property.payments.receipt.show', $payment, false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>
                    </td>
                </tr>
            @empty
                @unless(($recentRegisterReceipts ?? collect())->isNotEmpty())
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No payments recorded yet.</td></tr>
                @endunless
            @endforelse
            @foreach (($recentRegisterReceipts ?? []) as $receipt)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">{{ optional($receipt->txn_date ?? $receipt->banking_date)->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $receipt->amount) }}</td>
                    <td class="px-4 py-3">{{ $receipt->displayPaymentMethod() }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $receipt->ezen_receipt_no ?: ($receipt->ref_no ?: '—') }}</td>
                    <td class="px-4 py-3 text-xs">
                        <span class="font-medium text-amber-800">Imported, not posted</span>
                        <div class="mt-0.5 text-[11px] text-amber-700">Does not count on rent collected until allocated</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">Imported receipt</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
