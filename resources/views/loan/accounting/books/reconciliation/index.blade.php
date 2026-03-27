<x-loan-layout>
    <x-loan.page title="Accounts reconciliation" subtitle="Match bank/cash GL balances to statements.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Books hub</a>
            @include('loan.accounting.partials.export_buttons')
            <a href="{{ route('loan.accounting.reconciliation.create') }}" class="inline-flex rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">New reconciliation</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach(($statuses ?? []) as $s)
                            <option value="{{ $s }}" @selected(($status ?? '') === (string) $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Account</label>
                    <select name="accounting_chart_account_id" class="h-10 min-w-[18rem] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach(($cashAccounts ?? []) as $acc)
                            <option value="{{ $acc->id }}" @selected((int) ($accountId ?? 0) === (int) $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.reconciliation.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

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
