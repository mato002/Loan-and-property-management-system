@php
    $creditBalance = (float) ($creditBalance ?? 0);
    $hubOpenInvoices = $hubOpenInvoices ?? collect();
    $creditTransactions = $creditTransactions ?? collect();
    $advanceCreditsEnabled = $advanceCreditsEnabled ?? false;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Tenant credit</h3>
            <p class="text-xs text-slate-500">Balance {{ \App\Services\Property\PropertyMoney::kes($creditBalance) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700" data-property-modal-open="showHubAdvanceForm" @click="showHubAdvanceForm = true">Record advance</button>
            <form method="post" action="{{ route('property.tenants.credit.auto_apply', $tenant, false) }}" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="return_to" value="tenant_show" />
                <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
                <input type="hidden" name="return_tab" value="credit" />
                <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Auto-apply to open invoices</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-wide text-emerald-800 font-semibold">Apply credit</p>
            @if ($hubOpenInvoices->isEmpty() || $creditBalance <= 0)
                <p class="mt-2 text-sm text-emerald-900/80">Need both credit balance and an open invoice to apply.</p>
            @else
                <form method="post" action="{{ route('property.tenants.credit.apply', $tenant, false) }}" class="mt-3 space-y-3" data-turbo-frame="property-main">
                    @csrf
                    <input type="hidden" name="return_to" value="tenant_show" />
                    <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
                    <input type="hidden" name="return_tab" value="credit" />
                    <div>
                        <label class="text-xs text-slate-600">Invoice</label>
                        <select name="pm_invoice_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required data-property-searchable="true">
                            @foreach ($hubOpenInvoices as $inv)
                                @php $openBal = max(0, (float) $inv->amount - (float) $inv->amount_paid); @endphp
                                <option value="{{ $inv->id }}">{{ $inv->invoice_no ?: '#'.$inv->id }} — due {{ \App\Services\Property\PropertyMoney::kes($openBal) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Amount (KES)</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $creditBalance }}" name="amount" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Notes</label>
                        <input type="text" name="notes" class="mt-1 w-full rounded-lg border-slate-300 text-sm" maxlength="500">
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Apply credit</button>
                </form>
            @endif
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-wide text-amber-900 font-semibold">Refund unused credit</p>
            @if ($creditBalance <= 0)
                <p class="mt-2 text-sm text-amber-900/80">No credit available to refund.</p>
            @else
                <form method="post" action="{{ route('property.tenants.credit.refund', $tenant, false) }}" class="mt-3 space-y-3" data-turbo-frame="property-main">
                    @csrf
                    <input type="hidden" name="return_to" value="tenant_show" />
                    <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
                    <input type="hidden" name="return_tab" value="credit" />
                    <div>
                        <label class="text-xs text-slate-600">Amount (max {{ \App\Services\Property\PropertyMoney::kes($creditBalance) }})</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $creditBalance }}" name="amount" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
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
            @endif
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Credit history</h3>
            <a href="{{ route('property.tenants.credit.ledger', $tenant, false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Full ledger</a>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($creditTransactions as $txn)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ $txn->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ method_exists($txn, 'typeLabel') ? $txn->typeLabel() : ($txn->type ?? '—') }}</td>
                        <td class="px-4 py-3 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $txn->amount) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $txn->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No credit movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
