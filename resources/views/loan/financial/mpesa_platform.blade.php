<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                M-Pesa payouts
            </a>
        </x-slot>

        @php
            $mode = $mode ?? 'platform';
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">STK today (sum)</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">
                    @if ($mode === 'pm_payments')
                        {{ number_format((float) data_get($pmTotals ?? [], 'stk_today_sum', 0), 2) }}
                    @else
                        {{ number_format((float) $stkTodaySum, 2) }}
                    @endif
                </p>
                <p class="text-xs text-slate-500 mt-1">
                    @if ($mode === 'pm_payments')
                        Source: STK payments
                    @else
                        Channel: STK push
                    @endif
                </p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">C2B (24h sum)</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">
                    @if ($mode === 'pm_payments')
                        {{ number_format((float) data_get($pmTotals ?? [], 'stk_24h_sum', 0), 2) }}
                    @else
                        {{ number_format((float) $c2b24hSum, 2) }}
                    @endif
                </p>
                <p class="text-xs text-slate-500 mt-1">
                    @if ($mode === 'pm_payments')
                        STK (24h sum)
                    @else
                        Rolling window
                    @endif
                </p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Failed (all time)</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">
                    @if ($mode === 'pm_payments')
                        {{ (int) data_get($pmTotals ?? [], 'failed_count', 0) }}
                    @else
                        {{ $failedCount }}
                    @endif
                </p>
                <p class="text-xs text-slate-500 mt-1">By status = failed</p>
            </div>
        </div>

        @if ($mode !== 'pm_payments')
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Log transaction</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Creates a row you can reconcile with Daraja later.</p>
                </div>
                <form method="post" action="{{ route('loan.financial.mpesa_platform.transactions.store') }}" class="px-5 py-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">Reference</label>
                        <input id="reference" name="reference" value="{{ old('reference') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                        <select id="channel" name="channel" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['stk_push' => 'STK push', 'c2b' => 'C2B', 'b2c' => 'B2C'] as $val => $lab)
                                <option value="{{ $val }}" @selected(old('channel', 'stk_push') === $val)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'] as $val => $lab)
                                <option value="{{ $val }}" @selected(old('status', 'completed') === $val)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                        <input id="notes" name="notes" value="{{ old('notes') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors w-full md:w-auto">
                            Save transaction
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                Showing <span class="font-semibold">live STK payments</span> from <span class="font-mono">pm_payments</span>. The manual “Log transaction” table is hidden because it’s not your live source.
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">
                    @if ($mode === 'pm_payments')
                        Recent STK payments
                    @else
                        Recent transactions
                    @endif
                </h2>
                <p class="text-xs text-slate-500">
                    @if ($mode === 'pm_payments')
                        {{ is_iterable($pmPayments ?? []) ? count($pmPayments ?? []) : 0 }} recent
                    @else
                        {{ $transactions->total() }} total
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if ($mode === 'pm_payments')
                            @forelse (($pmPayments ?? []) as $p)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-5 py-3 font-medium text-slate-900">
                                        {{ $p->external_ref ?? ('PM-'.$p->id) }}
                                        @php
                                            $chk = data_get($p->meta ?? [], 'daraja.checkout_request_id');
                                        @endphp
                                        @if($chk)
                                            <span class="block text-[11px] text-slate-500 font-mono truncate max-w-md">{{ $chk }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 tabular-nums text-slate-700">{{ number_format((float) $p->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-slate-600">mpesa_stk</td>
                                    <td class="px-5 py-3">
                                        @if ($p->status === \App\Models\PmPayment::STATUS_COMPLETED)
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Completed</span>
                                        @elseif ($p->status === \App\Models\PmPayment::STATUS_FAILED)
                                            <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100">Failed</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 border border-amber-100">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ optional($p->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="text-xs text-slate-400">—</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                        No STK payments found.
                                    </td>
                                </tr>
                            @endforelse
                        @else
                            @forelse ($transactions as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->reference }}</td>
                                <td class="px-5 py-3 tabular-nums text-slate-700">{{ number_format((float) $row->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $row->channel) }}</td>
                                <td class="px-5 py-3">
                                    @if ($row->status === 'completed')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Completed</span>
                                    @elseif ($row->status === 'failed')
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100">Failed</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 border border-amber-100">Pending</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 tabular-nums text-xs">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="post" action="{{ route('loan.financial.mpesa_platform.transactions.destroy', $row) }}" class="inline" data-swal-confirm="Remove this transaction?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No transactions yet. Use the form above to add the first entry.
                                </td>
                            </tr>
                        @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($mode !== 'pm_payments' && $transactions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $transactions->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
