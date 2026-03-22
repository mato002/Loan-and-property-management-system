<x-loan-layout>
    <x-loan.page title="Posted journal entries" subtitle="Retrieve and manage posted double-entry vouchers.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books</a>
            <a href="{{ route('loan.accounting.journal.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New entry</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
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
            @if ($entries->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $entries->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
