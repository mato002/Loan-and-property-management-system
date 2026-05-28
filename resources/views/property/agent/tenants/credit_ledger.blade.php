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
    <x-slot name="actions">
        <form method="post" action="{{ route('property.tenants.credit.auto_apply', $tenant, false) }}" data-turbo-frame="property-main">
            @csrf
            <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Auto-apply to open invoices</button>
        </form>
    </x-slot>

    @if ($advanceCreditsEnabled ?? false)
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Record advance payment</p>
            <p class="mt-1 text-xs text-emerald-900/80">Receive prepayment for this tenant (no invoice required). Open invoices are paid first; the remainder stays as credit on this ledger.</p>
            <form method="post" action="{{ route('property.payments.store_advance', absolute: false) }}" data-turbo-frame="property-main" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="payment_form" value="advance" />
                <input type="hidden" name="return_to" value="credit_ledger" />
                <input type="hidden" name="pm_tenant_id" value="{{ $tenant->id }}" />
                <div>
                    <label class="text-xs text-slate-600">Channel</label>
                    <select name="channel" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('channel', 'mpesa') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-600">Amount (KES)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-600">Paid at</label>
                    <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-600">Reference</label>
                    <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="M-Pesa / bank ref" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs text-slate-600">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. Prepaid rent for next month" />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save advance payment</button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-wide text-emerald-800 font-semibold">Apply credit manually</p>
            <form method="post" action="{{ route('property.tenants.credit.apply', $tenant, false) }}" class="mt-3 space-y-3" data-turbo-frame="property-main">
                @csrf
                <div>
                    <label class="text-xs text-slate-600">Invoice</label>
                    <select id="credit-apply-invoice-select" name="pm_invoice_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
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
                    <label class="text-xs text-slate-600">Amount (KES)</label>
                    <input id="credit-apply-amount-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-600">Notes</label>
                    <input type="text" name="notes" class="mt-1 w-full rounded-lg border-slate-300 text-sm" maxlength="500">
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Apply credit</button>
            </form>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-wide text-amber-900 font-semibold">Refund unused credit</p>
            <form method="post" action="{{ route('property.tenants.credit.refund', $tenant, false) }}" class="mt-3 space-y-3" data-turbo-frame="property-main">
                @csrf
                <div>
                    <label class="text-xs text-slate-600">Amount (max {{ \App\Services\Property\PropertyMoney::kes((float) $balance) }})</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-600">Reference</label>
                    <input type="text" name="reference" class="mt-1 w-full rounded-lg border-slate-300 text-sm" maxlength="128">
                </div>
                <div>
                    <label class="text-xs text-slate-600">Notes</label>
                    <input type="text" name="notes" class="mt-1 w-full rounded-lg border-slate-300 text-sm" maxlength="500">
                </div>
                <button type="submit" class="rounded-lg bg-amber-700 px-3 py-2 text-sm font-medium text-white">Process refund</button>
            </form>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Transaction history</h3>
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
