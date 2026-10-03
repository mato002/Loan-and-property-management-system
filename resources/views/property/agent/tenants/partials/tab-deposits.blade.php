@php
    $depositSnapshot = $depositSnapshot ?? ['held' => 0.0, 'expected' => 0.0, 'to_pay' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'lines' => []];
    $depositRefunds = $depositRefunds ?? collect();
@endphp
<div class="space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Deposits</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Required {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['to_pay'] ?? $depositSnapshot['expected'] ?? 0)) }}
                    · Paid {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['paid'] ?? $depositSnapshot['held'] ?? 0)) }}
                    · Balance due {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['due'] ?? 0)) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-property-modal-open="showHubInvoiceForm" @click="showHubInvoiceForm = true">Invoice deposit charge</button>
                <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" data-property-modal-open="showHubDepositRefundForm" @click="showHubDepositRefundForm = true">Record refund</button>
                <a href="{{ route('property.reports.tenant.deposits', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline self-center">Deposit report</a>
            </div>
        </div>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Required</th>
                    <th class="px-4 py-3 text-right">Paid / held</th>
                    <th class="px-4 py-3 text-right">Balance due</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($depositSnapshot['lines'] ?? []) as $line)
                    @php
                        $toPay = (float) ($line['to_pay'] ?? $line['amount'] ?? 0);
                        $paid = (float) ($line['paid'] ?? 0);
                        $due = (float) ($line['due'] ?? max(0, $toPay - $paid));
                        $progress = $toPay > 0.009 ? (int) min(100, round(($paid / $toPay) * 100)) : ($paid > 0.009 ? 100 : 0);
                        $statusKey = (string) ($line['status'] ?? ($due <= 0.009 ? 'paid' : ($paid > 0.009 ? 'partial' : 'unpaid')));
                        $statusLabel = (string) ($line['status_label'] ?? match ($statusKey) {
                            'paid' => 'Paid',
                            'partial' => 'Partially paid',
                            default => 'Unpaid',
                        });
                        $statusClass = match ($statusKey) {
                            'paid' => 'bg-emerald-100 text-emerald-800',
                            'partial' => 'bg-amber-100 text-amber-800',
                            default => 'bg-rose-100 text-rose-800',
                        };
                        $barClass = match ($statusKey) {
                            'paid' => 'bg-emerald-500',
                            'partial' => 'bg-amber-500',
                            default => 'bg-rose-500',
                        };
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $line['item'] ?? $line['label'] ?? 'Deposit' }}</div>
                            @if (! empty($line['subtitle']))
                                <div class="text-xs text-slate-500">{{ $line['subtitle'] }}</div>
                            @endif
                            @if (! empty($line['url']) && ! empty($line['invoice_no']))
                                <a href="{{ $line['url'] }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">{{ $line['invoice_no'] }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $line['source'] ?? 'Lease agreement' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            <div class="mt-2 h-1.5 w-28 overflow-hidden rounded-full bg-slate-200" title="{{ $progress }}% paid">
                                <div class="h-full {{ $barClass }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes($toPay) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes($paid) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $due > 0.009 ? 'text-rose-700' : 'text-slate-700' }}">{{ \App\Services\Property\PropertyMoney::kes($due) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No rent or security deposits recorded for this tenant.</td></tr>
                @endforelse
            </tbody>
        </table>
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
