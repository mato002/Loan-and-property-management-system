<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_settings') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Daraja settings</a>
            <a href="{{ route('loan.payments.unposted') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Unposted payments</a>
        </x-slot>

        @unless ($c2bConfigured ?? false)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                C2B Validation/Confirmation URLs are not fully configured. Open Daraja settings to see missing <code class="font-mono text-xs">MPESA_C2B_*</code> variables, then register URLs with Safaricom.
            </div>
        @endunless

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Verify an M-Pesa receipt</p>
            <p class="mt-1 text-xs text-slate-600">Paste a Paybill/Till confirmation code. Safaricom confirms it, then we match it to a loan (or tenant) payment.</p>
            <form method="post" action="{{ route('loan.financial.mpesa_c2b.verify_receipt') }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Receipt code</label>
                    <input name="receipt" value="{{ old('receipt') }}" required maxlength="20" placeholder="QWERTY123" class="rounded-lg border-slate-200 text-sm font-mono uppercase" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Phone (optional)</label>
                    <input name="mpesa_phone" value="{{ old('mpesa_phone') }}" maxlength="32" placeholder="07… / 2547…" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Loan number / BillRef (optional)</label>
                    <input name="bill_ref" value="{{ old('bill_ref') }}" maxlength="40" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <button type="submit" class="inline-flex items-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]" @disabled(! ($statusQueryConfigured ?? false))>Verify with Safaricom</button>
            </form>
            @unless ($statusQueryConfigured ?? false)
                <p class="mt-2 text-xs text-amber-800">Missing: {{ implode('; ', $statusQueryMissing ?? []) }}. Set <code class="font-mono">MPESA_STATUS_RESULT_URL</code> (reuses B2C initiator).</p>
            @endunless
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">When</th>
                            <th class="px-4 py-3 text-left">Trans ID</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Notes</th>
                            <th class="px-4 py-3 text-left">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($transactions as $tx)
                            @php
                                $paymentId = (int) data_get($tx->meta, 'loan_book_payment_id', 0);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ optional($tx->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 font-mono text-slate-900">{{ $tx->transaction_id ?: $tx->reference }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-slate-900">{{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $tx->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($tx->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800') }}">{{ $tx->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 max-w-xs truncate">{{ $tx->notes ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($paymentId > 0)
                                        <a href="{{ route('loan.payments.show', $paymentId) }}" class="text-[#2f4f4f] font-semibold hover:underline">#{{ $paymentId }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No C2B confirmations yet. After registering URLs, Paybill payments appear here automatically.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">{{ $transactions->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
