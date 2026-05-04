<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.account_balances') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Client wallets</a>
        </x-slot>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pending as $req)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $req->loanClient?->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $req->loanClient?->client_number }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">KSh {{ number_format((float) $req->amount, 2) }}</td>
                            <td class="px-4 py-3 text-slate-600 text-xs">{{ \Illuminate\Support\Str::limit((string) ($req->notes ?? ''), 120) }}</td>
                            <td class="px-4 py-3 text-right align-top">
                                <form method="post" action="{{ route('loan.financial.wallet_refunds.approve', $req) }}" class="inline mr-2">@csrf
                                    <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">Approve &amp; post</button>
                                </form>
                                <form method="post" action="{{ route('loan.financial.wallet_refunds.reject', $req) }}" class="mt-2 space-y-1 text-left">
                                    @csrf
                                    <textarea name="rejection_reason" rows="2" class="w-full max-w-xs rounded border-slate-200 text-xs" placeholder="Reason if rejecting" required></textarea>
                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No pending refund requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-100 px-4 py-3">{{ $pending->links() }}</div>
        </div>
    </x-loan.page>
</x-loan-layout>
