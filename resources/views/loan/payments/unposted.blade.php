<x-loan-layout>
    <x-loan.page
        title="Unposted payments"
        subtitle="Queue of payments waiting to be posted to the loan book."
    >
        <x-slot name="actions">
            <form method="post" action="{{ route('loan.payments.unposted.auto_match') }}" class="inline-flex">
                @csrf
                <input type="hidden" name="q" value="{{ $q ?? '' }}">
                <input type="hidden" name="channel" value="{{ $channel ?? '' }}">
                <input type="hidden" name="from" value="{{ $from ?? '' }}">
                <input type="hidden" name="to" value="{{ $to ?? '' }}">
                <input type="hidden" name="per_page" value="{{ $perPage ?? 20 }}">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 transition-colors">Auto-match</button>
            </form>
            <a href="{{ route('loan.payments.merge') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Merge payments</a>
            <a href="{{ route('loan.payments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record payment</a>
        </x-slot>

        @include('loan.payments.partials.flash')

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, loan, client, receipt..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Channel</label>
                    <input type="text" name="channel" value="{{ $channel ?? '' }}" placeholder="cash, mpesa..." class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.payments.unposted') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Quick filters</span>
                <a
                    href="{{ route('loan.payments.unposted', array_merge(request()->except('page'), ['channel' => 'mpesa_sms_unmatched'])) }}"
                    class="rounded-full border px-3 py-1 text-xs font-semibold {{ ($channel ?? '') === 'mpesa_sms_unmatched' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >SMS unmatched repayments</a>
                <a
                    href="{{ route('loan.payments.unposted', array_merge(request()->except('page'), ['channel' => 'mpesa_sms_disbursement_unmatched'])) }}"
                    class="rounded-full border px-3 py-1 text-xs font-semibold {{ ($channel ?? '') === 'mpesa_sms_disbursement_unmatched' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >SMS unmatched disbursements</a>
                <a
                    href="{{ route('loan.payments.unposted', array_merge(request()->except('page'), ['channel' => ''])) }}"
                    class="rounded-full border px-3 py-1 text-xs font-semibold {{ ($channel ?? '') === '' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >All channels</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Unposted queue</h2>
                <p class="text-xs text-slate-500">{{ $payments->total() }} row(s)</p>
            </div>

            <div class="overflow-x-auto pb-2">
                <table class="min-w-[1400px] w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Loan</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Kind</th>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Payer number</th>
                            <th class="px-5 py-3">Receipt</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loan_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $p->channel }}</span>
                                        @if (str_starts_with((string) $p->channel, 'mpesa_sms_'))
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">SMS Forwarder</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $p->payment_kind) }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $p->transaction_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p->payer_msisdn ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p->mpesa_receipt_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <details class="relative inline-block text-left">
                                        <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                            Actions
                                        </summary>
                                        <div class="absolute right-0 z-10 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                            @if (in_array((string) $p->channel, ['mpesa_sms_unmatched', 'mpesa_sms_disbursement_unmatched'], true) && is_null($p->loan_book_loan_id))
                                                <form method="post" action="{{ route('loan.payments.assign_loan', $p) }}" class="mb-2 flex items-center gap-2">
                                                    @csrf
                                                    <input type="hidden" name="post_now" value="1">
                                                    <select name="loan_book_loan_id" required class="h-8 flex-1 rounded-lg border border-amber-300 bg-amber-50 px-2 text-xs text-slate-700">
                                                        <option value="">Assign loan...</option>
                                                        @foreach (($assignableLoanOptions ?? collect()) as $loanId => $loanLabel)
                                                            <option value="{{ $loanId }}" @selected((int) ($suggestedLoanByPayment[$p->id] ?? 0) === (int) $loanId)>{{ $loanLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="h-8 rounded-lg bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">Assign &amp; Post</button>
                                                </form>
                                            @endif
                                            @if (! is_null($p->loan_book_loan_id))
                                                <form method="post" action="{{ route('loan.payments.post', $p) }}" class="mb-1">
                                                    @csrf
                                                    <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-[#2f4f4f] hover:bg-slate-50">Post</button>
                                                </form>
                                            @endif
                                            @if ($p->canEdit())
                                                <a href="{{ route('loan.payments.edit', $p) }}" class="mb-1 block rounded-md px-2 py-1.5 text-left text-xs font-medium text-indigo-600 hover:bg-slate-50">Edit</a>
                                                <form method="post" action="{{ route('loan.payments.destroy', $p) }}" data-swal-confirm="Delete this unposted payment?">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-slate-50">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-12 text-center text-slate-500">
                                    No unposted payments. Use <span class="font-medium text-slate-700">Record payment</span> or <span class="font-medium text-slate-700">Merge payments</span>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
