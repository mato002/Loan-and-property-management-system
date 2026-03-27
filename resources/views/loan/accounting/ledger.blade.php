<x-loan-layout>
    <x-loan.page title="General ledger" subtitle="Opening balance is from journal activity before the selected period.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books</a>
            @include('loan.accounting.partials.export_buttons')
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
            <form method="get" action="{{ route('loan.accounting.ledger') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="account_id" class="block text-xs font-semibold text-slate-600 mb-1">Account</label>
                    <select id="account_id" name="account_id" class="rounded-lg border-slate-200 text-sm min-w-[220px]">
                        <option value="">Select…</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input id="from" name="from" type="date" value="{{ $from }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label for="to" class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input id="to" name="to" type="date" value="{{ $to }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Run</button>
            </form>
        </div>

        @if ($account)
            <div class="grid gap-4 sm:grid-cols-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase">Opening</p>
                    <p class="text-lg font-semibold tabular-nums mt-1">{{ number_format($opening, 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase">Period change (Dr − Cr)</p>
                    <p class="text-lg font-semibold tabular-nums mt-1">{{ number_format((float) $lines->sum('debit') - (float) $lines->sum('credit'), 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase">Closing</p>
                    <p class="text-lg font-semibold tabular-nums mt-1">{{ number_format($closing, 2) }}</p>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700">{{ $account->code }} · {{ $account->name }}</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Jrnl #</th>
                            <th class="px-5 py-3">Ref</th>
                            <th class="px-5 py-3 text-right">Debit</th>
                            <th class="px-5 py-3 text-right">Credit</th>
                            <th class="px-5 py-3">Memo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($lines as $line)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 whitespace-nowrap">{{ $line->entry->entry_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $line->entry->id }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $line->entry->reference ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $line->memo ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500">No lines in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-slate-500">Choose an account and date range, then click Run.</p>
        @endif
    </x-loan.page>
</x-loan-layout>
