<x-loan-layout>
    <x-loan.page title="Employee payroll" subtitle="Pay periods, lines per staff member, and payslips.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.payroll.hub') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payroll home</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Books hub</a>
            <a href="{{ route('loan.accounting.payroll.create') }}" class="inline-flex rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">New period</a>
        </x-slot>
        @include('loan.accounting.partials.flash')
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Period</th>
                        <th class="px-5 py-3">Label</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($periods as $p)
                        <tr>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $p->period_start->format('Y-m-d') }} → {{ $p->period_end->format('Y-m-d') }}</td>
                            <td class="px-5 py-3">{{ $p->label ?? '—' }}</td>
                            <td class="px-5 py-3 capitalize">{{ $p->status }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('loan.accounting.payroll.show', $p) }}" class="text-indigo-600 font-medium text-sm mr-2">Open</a>
                                <a href="{{ route('loan.accounting.payroll.edit', $p) }}" class="text-slate-600 font-medium text-sm mr-2">Edit</a>
                                <form method="post" action="{{ route('loan.accounting.payroll.destroy', $p) }}" class="inline" data-swal-confirm="Delete this period and all lines?">@csrf @method('delete')<button type="submit" class="text-red-600 text-sm font-medium">Delete</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No payroll periods.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($periods->hasPages())<div class="px-5 py-4 border-t">{{ $periods->links() }}</div>@endif
        </div>
    </x-loan.page>
</x-loan-layout>
