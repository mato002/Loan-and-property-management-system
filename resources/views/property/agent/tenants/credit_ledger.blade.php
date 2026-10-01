@php
    $ledgerField = 'mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2';
    $ledgerModalDefaults = [
        'showLedgerAdvanceForm' => ($advanceCreditsEnabled ?? false)
            && (old('payment_form') === 'advance' || $errors->has('advance') || (old('return_to') === 'credit_ledger' && $errors->hasAny(['amount','channel','external_ref']))),
        'showLedgerCreditApplyForm' => old('credit_form') === 'apply' && $errors->hasAny(['pm_invoice_id','amount','notes']),
        'showLedgerCreditRefundForm' => old('credit_form') === 'refund' && $errors->hasAny(['amount','reference','notes']),
    ];
@endphp
<x-property.workspace
    :title="'Credit ledger — '.$tenant->name"
    subtitle="Advance rent balance, applications, and refunds."
    back-route="property.tenants.show"
    :back-params="['tenant' => $tenant->id]"
    :stats="[
        ['label' => 'Credit balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) $balance), 'hint' => 'Available advance'],
        ['label' => 'Open invoices', 'value' => (string) $openInvoices->count(), 'hint' => 'Can receive credit'],
    ]"
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from($ledgerModalDefaults) !!}"></x-slot>

    <x-slot name="actions">
        @if ($advanceCreditsEnabled ?? false)
            <button type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700" data-property-modal-open="showLedgerAdvanceForm" @click="showLedgerAdvanceForm = true">Record advance</button>
        @endif
        <button type="button" class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" data-property-modal-open="showLedgerCreditApplyForm" @click="showLedgerCreditApplyForm = true">Apply credit</button>
        <button type="button" class="rounded-xl bg-amber-700 px-3 py-2 text-sm font-medium text-white hover:bg-amber-800" data-property-modal-open="showLedgerCreditRefundForm" @click="showLedgerCreditRefundForm = true">Refund credit</button>
        <form method="post" action="{{ route('property.tenants.credit.auto_apply', $tenant, false) }}" data-turbo-frame="property-main">
            @csrf
            <button type="submit" class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">Auto-apply to open invoices</button>
        </form>
    </x-slot>

    <x-slot name="modals">
        @if ($advanceCreditsEnabled ?? false)
            <x-property.modal
                show="showLedgerAdvanceForm"
                close="showLedgerAdvanceForm = false"
                name="credit-ledger-advance"
                title="Record advance payment"
                max-width="2xl"
            >
                <p class="text-xs text-slate-500">Receive prepayment for this tenant (no invoice required). Open invoices are paid first; the remainder stays as credit on this ledger.</p>
                <form method="post" action="{{ route('property.payments.store_advance', absolute: false) }}" data-turbo-frame="property-main" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="payment_form" value="advance" />
                    <input type="hidden" name="return_to" value="credit_ledger" />
                    <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                        <select name="channel" required class="{{ $ledgerField }}">
                            @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('channel', 'mpesa') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                        <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="{{ $ledgerField }}" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Paid at</label>
                        <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="{{ $ledgerField }}" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference</label>
                        <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="{{ $ledgerField }}" placeholder="M-Pesa / bank ref" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="{{ $ledgerField }}" placeholder="e.g. Prepaid rent for next month" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save advance payment</button>
                    </div>
                </form>
            </x-property.modal>
        @endif

        <x-property.modal
            show="showLedgerCreditApplyForm"
            close="showLedgerCreditApplyForm = false"
            name="credit-ledger-apply"
            title="Apply credit"
            max-width="xl"
        >
            @if ($openInvoices->isEmpty() || (float) $balance <= 0)
                <p class="text-sm text-slate-600">Need both credit balance and an open invoice to apply. Current credit {{ \App\Services\Property\PropertyMoney::kes((float) $balance) }}.</p>
            @else
                <form method="post" action="{{ route('property.tenants.credit.apply', $tenant, false) }}" class="space-y-3" data-turbo-frame="property-main">
                    @csrf
                    <input type="hidden" name="credit_form" value="apply" />
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Invoice</label>
                        <select id="credit-apply-invoice-select" name="pm_invoice_id" class="{{ $ledgerField }}" required data-property-searchable="true">
                            @foreach ($openInvoices as $inv)
                                @php $openBal = max(0, (float) $inv->amount - (float) $inv->amount_paid); @endphp
                                <option
                                    value="{{ $inv->id }}"
                                    data-open-balance="{{ number_format($openBal, 2, '.', '') }}"
                                    @selected($loop->first || (string) old('pm_invoice_id') === (string) $inv->id)
                                >{{ $inv->invoice_no }} — due {{ \App\Services\Property\PropertyMoney::kes($openBal) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                        <input id="credit-apply-amount-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="{{ $ledgerField }}" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="{{ $ledgerField }}" maxlength="500">
                    </div>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply credit</button>
                </form>
            @endif
        </x-property.modal>

        <x-property.modal
            show="showLedgerCreditRefundForm"
            close="showLedgerCreditRefundForm = false"
            name="credit-ledger-refund"
            title="Refund unused credit"
            max-width="xl"
        >
            @if ((float) $balance <= 0)
                <p class="text-sm text-slate-600">No credit available to refund.</p>
            @else
                <form method="post" action="{{ route('property.tenants.credit.refund', $tenant, false) }}" class="space-y-3" data-turbo-frame="property-main">
                    @csrf
                    <input type="hidden" name="credit_form" value="refund" />
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (max {{ \App\Services\Property\PropertyMoney::kes((float) $balance) }})</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" value="{{ old('amount') }}" class="{{ $ledgerField }}" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" class="{{ $ledgerField }}" maxlength="128">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="{{ $ledgerField }}" maxlength="500">
                    </div>
                    <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Process refund</button>
                </form>
            @endif
        </x-property.modal>
    </x-slot>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Transaction history</h3>
                <p class="text-xs text-slate-500">Export includes every matching line, not only this page.</p>
            </div>
            <form method="get" action="{{ route('property.tenants.credit.ledger', $tenant, false) }}" class="flex flex-wrap items-end gap-2" data-turbo-frame="property-main">
                <div>
                    <label class="block text-xs font-medium text-slate-500">Type</label>
                    <select name="type" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="">All types</option>
                        @foreach (['credit_created' => 'Advance created', 'credit_applied' => 'Applied to invoice', 'credit_refunded' => 'Refunded', 'credit_reversed' => 'Reversed', 'manual_adjustment' => 'Manual adjustment'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.tenants.credit.ledger', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'csv']), false),
                    'xlsUrl' => route('property.tenants.credit.ledger', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'xls']), false),
                    'pdfUrl' => route('property.tenants.credit.ledger', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'pdf']), false),
                    'wordUrl' => route('property.tenants.credit.ledger', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'word']), false),
                ])
            </form>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Invoice / ref</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $txn)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ $txn->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                @if($txn->type === 'credit_created') bg-emerald-100 text-emerald-800
                                @elseif($txn->type === 'credit_applied') bg-blue-100 text-blue-800
                                @elseif($txn->type === 'credit_refunded') bg-amber-100 text-amber-800
                                @else bg-slate-100 text-slate-700 @endif">
                                {{ $txn->typeLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $txn->amount) }}</td>
                        <td class="px-4 py-3">{{ $txn->invoice?->invoice_no ?? ($txn->reference ?: '—') }}</td>
                        <td class="px-4 py-3 capitalize">{{ $txn->application_mode ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $txn->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No credit movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($transactions->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $transactions->links() }}</div>
        @endif
    </div>

    <script>
        (function () {
            const invoiceSelect = document.getElementById('credit-apply-invoice-select');
            const amountInput = document.getElementById('credit-apply-amount-input');
            if (!invoiceSelect || !amountInput) return;

            const prefill = () => {
                const opt = invoiceSelect.selectedOptions[0];
                if (!opt) return;
                const balance = opt.getAttribute('data-open-balance');
                if (balance !== null && balance !== '' && Number(balance) > 0 && !amountInput.value) {
                    amountInput.value = balance;
                }
            };

            invoiceSelect.addEventListener('change', () => {
                const opt = invoiceSelect.selectedOptions[0];
                const balance = opt?.getAttribute('data-open-balance');
                if (balance !== null && balance !== '' && Number(balance) > 0) {
                    amountInput.value = balance;
                }
            });
            prefill();
        })();
    </script>
</x-property.workspace>
