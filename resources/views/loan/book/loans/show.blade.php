<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @php
            $paid = (float) $loan->payments->sum('amount');
            $remaining = max(0, (float) $loan->balance);
            $totalRepayable = $paid + $remaining;
            $progress = $totalRepayable > 0 ? min(100, max(0, ($paid / $totalRepayable) * 100)) : 0;
            $isFullyPaid = $remaining <= 0.01 || $loan->status === \App\Models\LoanBookLoan::STATUS_CLOSED;
            $principalDisbursed = (float) $loan->disbursements->sum('amount');
            $realizedProfit = $paid - $principalDisbursed;
            $estimatedTotalProfit = $totalRepayable - $principalDisbursed;
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            <form method="post" action="{{ route('loan.book.loans.rebuild_snapshot', $loan) }}" class="inline" data-swal-confirm="Rebuild this loan snapshot from disbursements and processed payments?">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm hover:bg-amber-100 transition-colors">Rebuild snapshot</button>
            </form>
            <a href="{{ route('loan.book.loans.edit', $loan) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Edit loan</a>
        </x-slot>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Repayment snapshot</h2>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4 text-sm">
                <div>
                    <p class="text-slate-500">Total due</p>
                    <p class="font-semibold tabular-nums text-slate-900">{{ number_format($totalRepayable, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Paid</p>
                    <p class="font-semibold tabular-nums text-emerald-700">{{ number_format($paid, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Remaining</p>
                    <p class="font-semibold tabular-nums text-slate-900">{{ number_format($remaining, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Completion</p>
                    <p class="font-semibold text-slate-900">{{ number_format($progress, 1) }}% {{ $isFullyPaid ? '· Fully paid' : '· In progress' }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Principal disbursed</p>
                    <p class="font-semibold tabular-nums text-slate-900">{{ number_format($principalDisbursed, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Realized profit</p>
                    <p class="font-semibold tabular-nums {{ $realizedProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($realizedProfit, 2) }}</p>
                </div>
                <div>
                    <p class="text-slate-500">Estimated total profit</p>
                    <p class="font-semibold tabular-nums {{ $estimatedTotalProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($estimatedTotalProfit, 2) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Loan profile</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">Loan #</dt><dd class="font-medium text-slate-900">{{ $loan->loan_number }}</dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-900">{{ str_replace('_', ' ', $loan->status) }}</dd></div>
                    <div><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $loan->loanClient->full_name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Client #</dt><dd class="font-medium text-slate-900">{{ $loan->loanClient->client_number ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Product</dt><dd class="font-medium text-slate-900">{{ $loan->product_name }}</dd></div>
                    <div><dt class="text-slate-500">Branch</dt><dd class="font-medium text-slate-900">{{ $loan->branch ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Principal</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $loan->principal, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Principal outstanding</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $loan->principal_outstanding, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Balance</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $loan->balance, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Interest rate</dt><dd class="font-medium text-slate-900">{{ number_format((float) $loan->interest_rate, 2) }}%</dd></div>
                    <div><dt class="text-slate-500">Interest outstanding</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $loan->interest_outstanding, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Rate period</dt><dd class="font-medium text-slate-900">{{ strtoupper((string) ($loan->interest_rate_period ?: ($loan->application?->interest_rate_period ?? 'annual'))) }}</dd></div>
                    <div><dt class="text-slate-500">Days past due</dt><dd class="font-medium text-slate-900">{{ $loan->dpd }}</dd></div>
                    <div><dt class="text-slate-500">Checkoff</dt><dd class="font-medium text-slate-900">{{ $loan->is_checkoff ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-slate-500">Disbursed at</dt><dd class="font-medium text-slate-900">{{ optional($loan->disbursed_at)->format('Y-m-d') ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Maturity date</dt><dd class="font-medium text-slate-900">{{ optional($loan->maturity_date)->format('Y-m-d') ?: '—' }}</dd></div>
                </dl>

                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</p>
                    <p class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $loan->notes ?: '—' }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Application link</h2>
                @if ($loan->application)
                    <p class="mt-3 text-sm text-slate-700">Booked from application {{ $loan->application->reference }}.</p>
                    <a href="{{ route('loan.book.applications.show', $loan->application) }}" class="mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open application</a>
                    <form method="post" action="{{ route('loan.book.loans.sync_schedule', $loan) }}" class="mt-2" data-swal-confirm="Sync term and rate period from linked application and recalculate this loan snapshot?">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                            Sync Schedule From Application
                        </button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-600">This loan was created directly (no linked application).</p>
                @endif

                <div class="mt-4 flex flex-col gap-2">
                    <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Record disbursement</a>
                    <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Post collection</a>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-700">Disbursements</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Method</th>
                                <th class="px-4 py-2 text-left">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($loan->disbursements as $disbursement)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ optional($disbursement->disbursed_at)->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $disbursement->amount, 2) }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ $disbursement->method }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ $disbursement->reference }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No disbursements yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-700">Recent collections</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Channel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse (($recentCollections ?? collect()) as $entry)
                                <tr>
                                    <td class="px-4 py-2 text-slate-700">{{ optional($entry->collected_on)->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) $entry->amount, 2) }}</td>
                                    <td class="px-4 py-2 text-slate-700">{{ $entry->channel }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No collections yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Quick links</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All loans</a>
                <a href="{{ route('loan.book.loans.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Create loan</a>
                <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Disbursements</a>
                <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Collection sheet</a>
                <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Loan arrears</a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
