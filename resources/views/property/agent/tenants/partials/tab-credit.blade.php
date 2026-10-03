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
            <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" data-property-modal-open="showHubCreditApplyForm" @click="showHubCreditApplyForm = true">Apply credit</button>
            <button type="button" class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800" data-property-modal-open="showHubCreditRefundForm" @click="showHubCreditRefundForm = true">Refund credit</button>
            <form method="post" action="{{ route('property.tenants.credit.auto_apply', $tenant, false) }}" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="return_to" value="tenant_show" />
                <input type="hidden" name="return_tenant_id" value="{{ $tenant->id }}" />
                <input type="hidden" name="return_tab" value="credit" />
                <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Auto-apply to open invoices</button>
            </form>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Credit history</h3>
            <span class="flex items-center gap-3">
                <a href="{{ route('property.tenants.credit.ledger', array_merge(['tenant' => $tenant->id], ['export' => 'pdf']), false) }}" data-turbo="false" class="text-xs font-semibold text-slate-700 hover:underline">Export PDF</a>
                <a href="{{ route('property.tenants.credit.ledger', $tenant, false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Full ledger</a>
            </span>
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
