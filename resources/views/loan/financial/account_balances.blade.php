<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.teller_operations') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Teller ops
            </a>
            <a href="{{ route('loan.financial.accounts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add account
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Balances</h2>
                <p class="text-xs text-slate-500">{{ $accounts->total() }} account(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Account</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Currency</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($accounts as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->account_type }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $row->currency }}</td>
                                <td class="px-5 py-3 text-right font-medium text-slate-900 tabular-nums">{{ number_format((float) $row->balance, 2) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('loan.financial.accounts.edit', $row) }}" class="text-xs font-semibold text-indigo-600 hover:underline">Edit</a>
                                        <form method="post" action="{{ route('loan.financial.accounts.destroy', $row) }}" class="inline" data-swal-confirm="Delete this account?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                    No accounts yet. <a href="{{ route('loan.financial.accounts.create') }}" class="text-indigo-600 font-medium hover:underline">Add an account</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($accounts->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $accounts->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
