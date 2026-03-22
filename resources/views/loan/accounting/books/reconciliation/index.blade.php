<x-loan-layout>
    <x-loan.page title="Accounts reconciliation" subtitle="Match bank/cash GL balances to statements.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Books hub</a>
            <a href="{{ route('loan.accounting.reconciliation.create') }}" class="inline-flex rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">New reconciliation</a>
        </x-slot>
        @include('loan.accounting.partials.flash')
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3">Statement date</th>
                        <th class="px-5 py-3 text-right">Statement bal.</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-5 py-3"><span class="font-mono text-xs">{{ $r->account->code }}</span> {{ $r->account->name }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $r->statement_date->format('Y-m-d') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $r->statement_balance, 2) }}</td>
                            <td class="px-5 py-3 capitalize">{{ $r->status }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('loan.accounting.reconciliation.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                <form method="post" action="{{ route('loan.accounting.reconciliation.destroy', $r) }}" class="inline" data-swal-confirm="Delete?">@csrf @method('delete')<button type="submit" class="text-red-600 text-sm font-medium">Delete</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No reconciliations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($rows->hasPages())<div class="px-5 py-4 border-t">{{ $rows->links() }}</div>@endif
        </div>
    </x-loan.page>
</x-loan-layout>
