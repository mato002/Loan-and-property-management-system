<x-loan-layout>
    <x-loan.page title="Company expenses" subtitle="Operational spend recorded for management accounts.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books hub</a>
            <a href="{{ route('loan.accounting.company_expenses.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Add expense</a>
        </x-slot>
        @include('loan.accounting.partials.flash')
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Title</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-5 py-3 whitespace-nowrap">{{ $r->expense_date->format('Y-m-d') }}</td>
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $r->title }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $r->category ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $r->currency }} {{ number_format((float) $r->amount, 2) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('loan.accounting.company_expenses.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                <form method="post" action="{{ route('loan.accounting.company_expenses.destroy', $r) }}" class="inline" data-swal-confirm="Delete?">
                                    @csrf @method('delete')
                                    <button type="submit" class="text-red-600 font-medium text-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No expenses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($rows->hasPages())<div class="px-5 py-4 border-t">{{ $rows->links() }}</div>@endif
        </div>
    </x-loan.page>
</x-loan-layout>
