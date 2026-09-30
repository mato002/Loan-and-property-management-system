@php
    $depositSnapshot = $depositSnapshot ?? ['held' => 0.0, 'expected' => 0.0, 'lines' => []];
    $depositRefunds = $depositRefunds ?? collect();
    $inputClass = 'mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2';
@endphp
<div class="space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Deposits</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Held {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['held'] ?? 0)) }}
                    · expected on leases {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['expected'] ?? 0)) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-property-modal-open="showHubInvoiceForm" @click="showHubInvoiceForm = true">Invoice deposit charge</button>
                <a href="{{ route('property.reports.tenant.deposits', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline self-center">Deposit report</a>
            </div>
        </div>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($depositSnapshot['lines'] ?? []) as $line)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ $line['label'] ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ $line['source'] ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($line['status'] ?? '—')) }}</td>
                        <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['amount'] ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No rent or security deposits recorded for this tenant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4">
            <p class="text-xs uppercase tracking-wide text-indigo-900 font-semibold">Record deposit refund</p>
            <p class="mt-1 text-xs text-indigo-900/80">Pay a held rent/security deposit back to the tenant. Bank details stay on the tenant profile if they were blank.</p>
            <form method="post" action="{{ route('property.tenants.deposits.refund', $tenant, false) }}" class="mt-3 space-y-3" data-turbo-frame="property-main">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-600">Date</label>
                        <input type="date" name="refunded_at" value="{{ old('refunded_at', now()->toDateString()) }}" class="{{ $inputClass }}" />
                        @error('refunded_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Amount (KES)</label>
                        <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" class="{{ $inputClass }}" placeholder="0.00" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Bank</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $tenant->bank_name) }}" maxlength="120" class="{{ $inputClass }}" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Branch code</label>
                        <input type="text" name="bank_branch" value="{{ old('bank_branch', $tenant->bank_branch) }}" maxlength="80" class="{{ $inputClass }}" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Account name</label>
                        <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $tenant->bank_account_name) }}" maxlength="160" class="{{ $inputClass }}" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Account number</label>
                        <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $tenant->bank_account_number) }}" maxlength="64" class="{{ $inputClass }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs text-slate-600">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" class="{{ $inputClass }}" placeholder="Optional reference" />
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save refund</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Refund history</h3>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Bank / account</th>
                        <th class="px-3 py-2">Done by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($depositRefunds as $refund)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 whitespace-nowrap">{{ optional($refund->refunded_at)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $refund->amount) }}</td>
                            <td class="px-3 py-2">
                                {{ collect([$refund->bank_name, $refund->bank_account_name, $refund->bank_account_number])->filter()->implode(' · ') ?: '—' }}
                            </td>
                            <td class="px-3 py-2">{{ $refund->createdBy?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No deposit refunds recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
