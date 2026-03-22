<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <form method="get" action="{{ route('loan.book.collection_sheet.index') }}" class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">Date</label>
                <input type="date" name="date" value="{{ $filterDate }}" class="rounded-lg border-slate-200 text-sm" />
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Go</button>
            </form>
            <a href="{{ route('loan.book.collection_mtd') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">MTD</a>
        </x-slot>

        @error('accounting')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Lines for {{ $filterDate }}</h2>
                    <p class="text-xs text-slate-500 mt-1">{{ $entries->total() }} receipt(s)</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3">Loan</th>
                                <th class="px-5 py-3">Client</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3">Channel</th>
                                <th class="px-5 py-3">By</th>
                                <th class="px-5 py-3">GL</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($entries as $row)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3 font-mono text-xs text-indigo-600">{{ $row->loan->loan_number }}</td>
                                    <td class="px-5 py-3 text-slate-800">{{ $row->loan->loanClient->full_name }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row->channel }}</td>
                                    <td class="px-5 py-3 text-slate-500 text-xs">{{ $row->collectedBy?->full_name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-xs">
                                        @if ($row->accounting_journal_entry_id)
                                            <a href="{{ route('loan.accounting.journal.show', $row->accounting_journal_entry_id) }}" class="text-indigo-600 font-semibold hover:underline">#{{ $row->accounting_journal_entry_id }}</a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="post" action="{{ route('loan.book.collection_sheet.destroy', $row) }}" class="inline" data-swal-confirm="Delete this collection line?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">No receipts this day.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($entries->hasPages())
                    <div class="px-5 py-3 border-t border-slate-100">{{ $entries->links() }}</div>
                @endif
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-800">Add receipt</h2>
                <form method="post" action="{{ route('loan.book.collection_sheet.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="collected_on" value="{{ $filterDate }}" />
                    <div>
                        <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan</label>
                        <select id="loan_book_loan_id" name="loan_book_loan_id" required class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select…</option>
                            @foreach ($loans as $l)
                                <option value="{{ $l->id }}" @selected(old('loan_book_loan_id') == $l->id)>{{ $l->loan_number }} · {{ $l->loanClient->full_name }}</option>
                            @endforeach
                        </select>
                        @error('loan_book_loan_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                        <select id="channel" name="channel" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank', 'checkoff' => 'Checkoff'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('channel') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="collected_by_employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Received by (optional)</label>
                        <select id="collected_by_employee_id" name="collected_by_employee_id" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">—</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected(old('collected_by_employee_id') == $e->id)>{{ $e->full_name }}</option>
                            @endforeach
                        </select>
                        @error('collected_by_employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-amber-50/80 px-3 py-2.5">
                        <label class="flex items-start gap-2 text-sm text-slate-800 cursor-pointer">
                            <input type="checkbox" name="sync_to_accounting" value="1" class="mt-0.5 rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('sync_to_accounting')) />
                            <span><span class="font-semibold">Also post to general ledger</span>
                                <span class="block text-xs text-slate-600 mt-0.5">Only tick this if you are <strong>not</strong> also recording the same cash as a processed pay-in, or the books will double-count.</span>
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Post to sheet</button>
                </form>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
