<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @php
            $loan = $disbursement->loan;
            $currency = 'Ksh';
            $amount = (float) $disbursement->amount;
            $principal = (float) ($loan?->principal ?? 0);
            $totalDisbursedSoFar = $loan ? (float) $loan->disbursements()->sum('amount') : $amount;
            $remainingToDisburse = max(0, $principal - $totalDisbursedSoFar);
            $isPartial = $loan ? ($totalDisbursedSoFar + 0.01) < $principal : false;
            $disbursementType = $loan ? ($isPartial ? 'Partial / tranche' : 'Full') : 'Standalone';
            $payoutStatus = strtolower((string) ($disbursement->payout_status ?? 'completed'));
            $statusMeta = match ($payoutStatus) {
                'failed' => ['label' => 'Failed', 'icon' => '🔴', 'class' => 'bg-red-100 text-red-700'],
                'pending', 'queued', 'processing' => ['label' => 'Pending', 'icon' => '🟡', 'class' => 'bg-amber-100 text-amber-700'],
                default => ['label' => 'Completed', 'icon' => '🟢', 'class' => 'bg-emerald-100 text-emerald-700'],
            };
            $method = strtolower((string) ($disbursement->method ?? ''));
            $hasPayoutReference = filled($disbursement->payout_transaction_id) || filled($disbursement->reference);
            $postedToAccounting = (bool) $disbursement->accounting_journal_entry_id;
            $matchesLoanTerms = ! $loan || ($totalDisbursedSoFar <= ($principal + 0.01));
            $integrityChecks = [
                ['ok' => $postedToAccounting, 'pass' => 'Posted to accounting', 'fail' => 'Not posted to accounting'],
                ['ok' => $matchesLoanTerms, 'pass' => 'Matches loan terms', 'fail' => 'Disbursed amount exceeds principal'],
                ['ok' => $hasPayoutReference, 'pass' => 'Payout reference available', 'fail' => 'Missing payout reference'],
            ];
            $journalLines = collect($disbursement->accountingJournalEntry?->lines ?? [])->take(4);
            $activity = collect([
                [
                    'title' => 'Disbursement created',
                    'meta' => $currency . ' ' . number_format($amount, 2),
                    'when' => $disbursement->created_at,
                ],
                [
                    'title' => 'Payout requested',
                    'meta' => filled($disbursement->payout_provider) ? ('Provider: ' . $disbursement->payout_provider) : 'Payout channel initialized',
                    'when' => $disbursement->payout_requested_at,
                ],
                [
                    'title' => 'Payout completed',
                    'meta' => filled($disbursement->payout_transaction_id) ? ('Txn: ' . $disbursement->payout_transaction_id) : 'Provider callback received',
                    'when' => $disbursement->payout_completed_at,
                ],
                [
                    'title' => 'Posted to accounting',
                    'meta' => $postedToAccounting ? ('Journal #' . $disbursement->accounting_journal_entry_id) : 'Waiting for posting',
                    'when' => $disbursement->updated_at,
                ],
            ])->filter(fn ($row) => ! empty($row['when']))->sortByDesc('when')->values();
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            @if ($disbursement->loan)
                <a href="{{ route('loan.book.loans.show', $disbursement->loan) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Open loan</a>
            @endif
        </x-slot>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4 lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disbursed Amount</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $currency }} {{ number_format($amount, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">Reference: {{ $disbursement->reference }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-2 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['class'] }}">
                        {{ $statusMeta['icon'] }} {{ $statusMeta['label'] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ optional($disbursement->disbursed_at)->format('Y-m-d') ?: '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disbursement Type</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $disbursementType }}</p>
                    <p class="mt-1 text-xs text-slate-500">Method: {{ strtoupper((string) ($disbursement->method ?: '—')) }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Disbursement details</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                    <div><dt class="text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ optional($disbursement->disbursed_at)->format('Y-m-d') ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Method</dt><dd class="font-medium text-slate-900">{{ $disbursement->method }}</dd></div>
                    <div><dt class="text-slate-500">Payout channel</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_provider ?: strtoupper((string) ($disbursement->method ?: '—')) }}</dd></div>
                <div><dt class="text-slate-500">Reference</dt><dd class="font-medium text-slate-900">{{ $disbursement->reference }}</dd></div>
                    <div><dt class="text-slate-500">Loan #</dt><dd class="font-medium text-slate-900">{{ $loan?->loan_number ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $loan?->loanClient?->full_name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Payout account</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_phone ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Transaction ID</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_transaction_id ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Conversation ID</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_conversation_id ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Processed at</dt><dd class="font-medium text-slate-900">{{ optional($disbursement->created_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Approved by</dt><dd class="font-medium text-slate-900">System</dd></div>
                    <div><dt class="text-slate-500">Payout result</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_result_desc ?: '—' }}</dd></div>
                </dl>
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activity notes</p>
                    <p class="mt-1 rounded-lg bg-slate-50 p-3 text-slate-700">{{ $disbursement->notes ?: '—' }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Disbursement integrity check</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($integrityChecks as $check)
                        <div class="rounded-lg border px-3 py-2 text-sm {{ $check['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                            {{ $check['ok'] ? '✅' : '⚠' }} {{ $check['ok'] ? $check['pass'] : $check['fail'] }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 border-t border-slate-100 pt-4 space-y-2">
                    <button type="button" onclick="window.print()" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print voucher</button>
                    @if (! $postedToAccounting)
                        <form method="post" action="{{ route('loan.book.disbursements.destroy', $disbursement) }}" data-swal-confirm="Remove this disbursement line?">
                            @csrf
                            @method('delete')
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Remove disbursement</button>
                        </form>
                    @endif
                    @if ($method === 'mpesa' && $payoutStatus === 'failed' && ($b2cPayoutConfigured ?? false))
                        <form method="post" action="{{ route('loan.book.disbursements.retry_payout', $disbursement) }}" data-swal-confirm="Retry this M-Pesa payout request?">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">Retry M-Pesa payout</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Financial context</h2>
            <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
                <div><dt class="text-slate-500">Loan principal</dt><dd class="font-semibold tabular-nums text-slate-900">{{ number_format($principal, 2) }}</dd></div>
                <div><dt class="text-slate-500">Total disbursed so far</dt><dd class="font-semibold tabular-nums text-slate-900">{{ number_format($totalDisbursedSoFar, 2) }}</dd></div>
                <div><dt class="text-slate-500">Remaining to disburse</dt><dd class="font-semibold tabular-nums {{ $remainingToDisburse > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($remainingToDisburse, 2) }}</dd></div>
            </dl>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Accounting link</h2>
            @if ($postedToAccounting)
                <p class="mt-2 text-sm text-slate-700">This disbursement was posted to journal entry #{{ $disbursement->accounting_journal_entry_id }}.</p>
                <a href="{{ route('loan.accounting.journal.show', $disbursement->accounting_journal_entry_id) }}" class="mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open journal entry</a>
                @if ($journalLines->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Account</th>
                                    <th class="px-3 py-2 text-right">Debit</th>
                                    <th class="px-3 py-2 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($journalLines as $line)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-700">{{ $line->account?->name ?? 'Ledger account' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) ($line->debit ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ number_format((float) ($line->credit ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <p class="mt-2 text-sm text-slate-600">No journal entry is linked yet.</p>
            @endif
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Activity timeline</h2>
            <div class="mt-3 space-y-3">
                @forelse ($activity as $row)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">{{ $row['title'] }}</p>
                            <p class="text-xs text-slate-500">{{ optional($row['when'])->format('Y-m-d H:i') }}</p>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $row['meta'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity events yet.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Quick links</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All disbursements</a>
                <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">New disbursement</a>
                @if ($disbursement->loan)
                    <a href="{{ route('loan.book.loans.show', $disbursement->loan) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Loan profile</a>
                @endif
                <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Collection sheet</a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
