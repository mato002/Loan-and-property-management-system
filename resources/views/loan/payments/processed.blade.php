<x-loan-layout>
    <x-loan.page
        title="Processed payments"
        subtitle="Posted collections and other payment lines."
    >
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
                <a href="{{ route('loan.payments.processed') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Processed</h2>
                <p class="text-xs text-slate-500">{{ $payments->total() }} row(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference / GL</th>
                            <th class="px-5 py-3">Loan</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Kind</th>
                            <th class="px-5 py-3">Transaction</th>
                            <th class="px-5 py-3">Posted</th>
                            <th class="px-5 py-3">Validated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">
                                    {{ $p->reference }}
                                    @if ($p->accounting_journal_entry_id)
                                        <a href="{{ route('loan.accounting.journal.show', $p->accounting_journal_entry_id) }}" class="block mt-1 text-indigo-600 font-semibold hover:underline">Journal #{{ $p->accounting_journal_entry_id }}</a>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loan_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->channel }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $p->payment_kind) }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $p->transaction_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 text-slate-600 text-xs">
                                    @if ($p->posted_at)
                                        {{ $p->posted_at->format('Y-m-d H:i') }}
                                        @if ($p->postedByUser)
                                            <span class="block text-slate-500">{{ $p->postedByUser->name }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs">
                                    @if ($p->validated_at)
                                        {{ $p->validated_at->format('Y-m-d H:i') }}
                                        @if ($p->validatedByUser)
                                            <span class="block text-slate-500">{{ $p->validatedByUser->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-amber-700">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No processed payments yet.</td>
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
