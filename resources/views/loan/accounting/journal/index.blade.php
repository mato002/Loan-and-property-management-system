<x-loan-layout>
    <x-loan.page title="Posted journal entries" subtitle="Retrieve and manage posted double-entry vouchers.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books</a>
            @include('loan.accounting.partials.export_buttons')
            <a href="{{ route('loan.accounting.journal.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New entry</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Reference</label>
                    <input type="text" name="reference" value="{{ $reference ?? '' }}" placeholder="Search…" class="h-10 w-56 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 20)) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.journal.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <form method="post" action="{{ route('loan.accounting.journal.bulk') }}" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" data-swal-confirm="Apply bulk action to selected entries?">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.je-row').forEach(cb=>cb.checked=this.checked)"></th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3">Lines</th>
                            <th class="px-5 py-3">By</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($entries as $e)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3"><input type="checkbox" name="ids[]" value="{{ $e->id }}" class="je-row"></td>
                                <td class="px-5 py-3 text-slate-700 whitespace-nowrap">{{ $e->entry_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 font-mono text-xs">{{ $e->reference ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate">{{ $e->description ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $e->lines_count }}</td>
                                <td class="px-5 py-3 text-slate-600 text-xs">{{ $e->createdByUser?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.accounting.journal.show', $e) }}" class="text-indigo-600 font-medium text-sm mr-3">View</a>
                                    <form method="post" action="{{ route('loan.accounting.journal.destroy', $e) }}" class="inline" data-swal-confirm="Delete this journal entry and all lines?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No journal entries yet.</td>
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
                @if ($entries->hasPages())
                    <div>{{ $entries->links() }}</div>
                @endif
            </div>
        </form>
    </x-loan.page>
</x-loan-layout>
