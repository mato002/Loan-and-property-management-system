@php
    use App\Models\ClientWallet;
    $f = $filters ?? [];
@endphp
<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.control_accounts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Control accounts
            </a>
            @if(auth()->user()?->hasLoanPermission('wallets.refund_approve'))
                <a href="{{ route('loan.financial.wallet_refunds.index') }}" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 transition-colors">
                    Pending refunds
                </a>
            @endif
        </x-slot>

        @if(!empty($reconcile['message']))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Diagnostic:</strong> {{ $reconcile['message'] }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total wallet liability</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 tabular-nums">KSh {{ number_format($totalWalletLiability, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active wallets</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-800 tabular-nums">{{ number_format($activeWallets) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Frozen wallets</p>
                <p class="mt-2 text-2xl font-semibold text-slate-800 tabular-nums">{{ number_format($frozenWallets) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending refunds</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800 tabular-nums">{{ number_format($pendingRefunds) }}</p>
            </div>
        </div>

        <form method="get" action="{{ route('loan.financial.account_balances') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                <input type="text" name="q" value="{{ $f['q'] ?? '' }}" class="rounded-lg border-slate-200 text-sm w-48" placeholder="Name, phone, number…" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select name="status" class="rounded-lg border-slate-200 text-sm">
                    <option value="">All</option>
                    <option value="{{ ClientWallet::STATUS_ACTIVE }}" @selected(($f['status'] ?? '') === ClientWallet::STATUS_ACTIVE)>Active</option>
                    <option value="{{ ClientWallet::STATUS_FROZEN }}" @selected(($f['status'] ?? '') === ClientWallet::STATUS_FROZEN)>Frozen</option>
                    <option value="{{ ClientWallet::STATUS_CLOSED }}" @selected(($f['status'] ?? '') === ClientWallet::STATUS_CLOSED)>Closed</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Balance</label>
                <select name="balance" class="rounded-lg border-slate-200 text-sm">
                    <option value="">All</option>
                    <option value="positive" @selected(($f['balanceFilter'] ?? '') === 'positive')>Positive</option>
                    <option value="zero" @selected(($f['balanceFilter'] ?? '') === 'zero')>Zero</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $f['from'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $f['to'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Client #</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3 text-right">Wallet balance</th>
                            <th class="px-4 py-3">Last txn</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($wallets as $w)
                            @php $c = $w->loanClient; @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $c?->client_number ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $c?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $c?->phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">KSh {{ number_format((float) $w->balance, 2) }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">
                                    @php $cid = (int) ($c?->id ?? $w->loan_client_id); @endphp
                                    {{ $cid && isset($lastTxDates[$cid]) ? \Illuminate\Support\Carbon::parse($lastTxDates[$cid])->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $w->status }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('loan.clients.show', $c) }}#wallet" class="text-xs font-semibold text-teal-700 hover:underline">View</a>
                                        @if(auth()->user()?->hasLoanPermission('wallets.view'))
                                            <a href="{{ route('loan.clients.wallet.statement', $c) }}" class="text-xs font-semibold text-slate-700 hover:underline">Statement</a>
                                        @endif
                                        @if(auth()->user()?->hasLoanPermission('wallets.pay_loan') && (float) $w->balance > 0.01 && $w->status === ClientWallet::STATUS_ACTIVE)
                                            <a href="{{ route('loan.clients.wallet.pay_loan.create', $c) }}" class="text-xs font-semibold text-indigo-700 hover:underline">Pay loan</a>
                                        @endif
                                        @if(auth()->user()?->hasLoanPermission('wallets.refund_request') && (float) $w->balance > 0.01)
                                            <a href="{{ route('loan.clients.show', $c) }}?refund_request=1#wallet" class="text-xs font-semibold text-amber-800 hover:underline">Refund</a>
                                        @endif
                                        @if(auth()->user()?->hasLoanPermission('wallets.freeze'))
                                            @if($w->status === ClientWallet::STATUS_ACTIVE)
                                                <form method="post" action="{{ route('loan.clients.wallet.freeze', $c) }}" class="inline">@csrf
                                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Freeze</button>
                                                </form>
                                            @elseif($w->status === ClientWallet::STATUS_FROZEN)
                                                <form method="post" action="{{ route('loan.clients.wallet.unfreeze', $c) }}" class="inline">@csrf
                                                    <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">Unfreeze</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">No client wallets match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $wallets->links() }}</div>
        </div>
    </x-loan.page>
</x-loan-layout>
