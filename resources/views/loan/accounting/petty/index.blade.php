<x-loan-layout>
    <x-loan.page title="Petty cashbook" subtitle="Receipts increase the imprest; disbursements decrease it.">
        <x-slot name="actions">
            @include('loan.accounting.partials.export_buttons')
            <a href="{{ route('loan.accounting.petty.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New line</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Kind</label>
                    <select name="kind" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        <option value="receipt" @selected(($kind ?? '') === 'receipt')>receipt</option>
                        <option value="disbursement" @selected(($kind ?? '') === 'disbursement')>disbursement</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Search</label>
                    <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Payee/description…" class="h-10 w-64 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 25)) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.petty.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <div class="rounded-xl border border-slate-200 bg-white p-5 mb-6 shadow-sm">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Imprest balance (all time)</p>
            <p class="text-2xl font-semibold tabular-nums mt-1 {{ $balance >= 0 ? 'text-slate-900' : 'text-red-700' }}">KES {{ number_format($balance, 2) }}</p>
        </div>

        <form method="post" action="{{ route('loan.accounting.petty.bulk') }}" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" data-swal-confirm="Apply bulk action to selected petty cash lines?">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.petty-row').forEach(cb=>cb.checked=this.checked)"></th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Kind</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Payee / source</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3"><input type="checkbox" name="ids[]" value="{{ $r->id }}" class="petty-row"></td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $r->entry_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ $r->kind }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $r->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $r->payee_or_source ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate">{{ $r->description ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.accounting.petty.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                    <form method="post" action="{{ route('loan.accounting.petty.destroy', $r) }}" class="inline" data-swal-confirm="Delete this line?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No petty cash lines yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-5 py-3 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <select name="action" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-700">
                        <option value="">Bulk action</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="h-9 rounded-lg bg-red-600 px-3 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-red-700">Apply</button>
                </div>
                @if ($rows->hasPages())
                    <div>{{ $rows->links() }}</div>
                @endif
            </div>
        </form>
    </x-loan.page>
</x-loan-layout>
