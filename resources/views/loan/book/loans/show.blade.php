<x-loan-layout>
    <style>
        .loan-table-scroll-5 {
            max-height: 15rem; /* roughly header + 5 body rows */
            overflow-y: auto;
        }

        .loan-table-scroll-5 thead th {
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .loan-timeline-scroll-5 {
            max-height: 20rem; /* about 5 timeline cards */
            overflow-y: auto;
            padding-right: 0.25rem;
        }
    </style>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @php
            $paid = (float) $loan->payments->sum('amount');
            $remaining = max(0, (float) $loan->balance);
            // Ledger sum (paid + balance) can exceed principal+interest when old postings applied cash to interest first
            // while principal_outstanding was unchanged — do not use this as "contract total due" on the UI.
            $ledgerLifeTotal = round($paid + $remaining, 2);
            $contractRepayable = round((float) ($contractRepayable ?? 0), 2);
            if ($contractRepayable <= 0.0) {
                $contractRepayable = max(0.01, $ledgerLifeTotal);
            }
            $progressDenominator = max($contractRepayable, $ledgerLifeTotal, 0.01);
            $progress = $progressDenominator > 0 ? min(100, max(0, ($paid / $progressDenominator) * 100)) : 0;
            $displayStatus = $loan->effectiveStatus();
            $isFullyPaid = $remaining <= 0.01 || $displayStatus === \App\Models\LoanBookLoan::STATUS_CLOSED;
            $principalDisbursed = (float) $loan->disbursements->sum('amount');
            $realizedProfit = $paid - $principalDisbursed;
            $estimatedTotalProfit = $ledgerLifeTotal - $principalDisbursed;
            $termValue = max(1, (int) ($loan->term_value ?: $loan->term_months ?: 1));
            $rawTermUnit = strtolower(trim((string) ($loan->term_unit ?: 'monthly')));
            $loanTermLabel = match ($rawTermUnit) {
                'daily', 'day' => $termValue === 1 ? '1 day' : $termValue.' days',
                'weekly', 'week', 'weeks' => $termValue === 1 ? '1 week' : $termValue.' weeks',
                'monthly', 'month', 'months' => $termValue === 1 ? '1 month' : $termValue.' months',
                'annual', 'year', 'yearly', 'years' => $termValue === 1 ? '1 year' : $termValue.' years',
                default => 'Term '.$termValue.' ('.($loan->term_unit ?: 'unspecified unit').')',
            };
            $interestRatePeriod = strtolower(trim((string) ($loan->interest_rate_period ?: ($loan->application?->interest_rate_period ?? 'term'))));
            $interestRatePeriodLabel = match ($interestRatePeriod) {
                'daily' => 'Per day',
                'weekly' => 'Per week',
                'monthly' => 'Per month',
                'annual' => 'Per year',
                'term' => 'Whole loan term',
                default => strtoupper($interestRatePeriod),
            };
            $scheduleUnit = str_contains($rawTermUnit, 'day')
                ? 'daily'
                : (str_contains($rawTermUnit, 'week')
                    ? 'weekly'
                    : (str_contains($rawTermUnit, 'year') ? 'annual' : 'monthly'));
            $addScheduleStep = function (\Carbon\Carbon $date, int $steps = 1) use ($scheduleUnit): \Carbon\Carbon {
                return match ($scheduleUnit) {
                    'daily' => $date->copy()->addDays($steps),
                    'weekly' => $date->copy()->addWeeks($steps),
                    'annual' => $date->copy()->addYearsNoOverflow($steps),
                    default => $date->copy()->addMonthsNoOverflow($steps),
                };
            };
            $scheduleFrequencyLabel = ucfirst($scheduleUnit);
            // Scheduled amount per period from the contract formula (not paid+balance, which can double-count mis-allocated interest).
            $baseInstallmentAmount = $termValue > 0 ? ($contractRepayable / $termValue) : $contractRepayable;
            $baseInstallmentAmount = max(0.01, (float) $baseInstallmentAmount);
            // Next payment to show: never more than what is left; for single-period loans this fixes "full contract / 1" after partial pay.
            $expectedInstallmentAmount = $remaining > 0.01 ? min($remaining, $baseInstallmentAmount) : 0.0;
            $installmentsCovered = (int) min($termValue, floor($paid / $baseInstallmentAmount));
            $nextInstallmentNumber = min($termValue, max(1, $installmentsCovered + 1));
            $nextDueDate = $loan->disbursed_at ? $addScheduleStep($loan->disbursed_at, $nextInstallmentNumber) : null;
            $daysToNextPayment = $nextDueDate ? now()->startOfDay()->diffInDays($nextDueDate->copy()->startOfDay(), false) : null;
            $dpd = (int) ($loan->dpd ?? 0);
            $healthKey = $isFullyPaid ? 'on_track' : ($dpd >= 30 ? 'delinquent' : ($dpd > 0 ? 'at_risk' : 'on_track'));
            $healthMeta = match ($healthKey) {
                'delinquent' => ['label' => 'Delinquent', 'icon' => '🔴', 'class' => 'bg-red-100 text-red-700'],
                'at_risk' => ['label' => 'At risk', 'icon' => '🟡', 'class' => 'bg-amber-100 text-amber-700'],
                default => ['label' => 'On track', 'icon' => '🟢', 'class' => 'bg-emerald-100 text-emerald-700'],
            };
            $arrearsLabel = $isFullyPaid ? 'Fully paid' : ($dpd > 0 ? 'Late' : 'On track');
            $arrearsClass = $isFullyPaid ? 'bg-emerald-100 text-emerald-700' : ($dpd > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700');
            $timeline = collect();
            $timeline->push([
                'when' => $loan->created_at,
                'title' => 'Loan created',
                'meta' => 'Loan #' . $loan->loan_number,
            ]);
            foreach ($loan->disbursements as $disbursement) {
                $timeline->push([
                    'when' => $disbursement->disbursed_at ?? $disbursement->created_at,
                    'title' => 'Disbursement recorded',
                    'meta' => number_format((float) $disbursement->amount, 2) . ' via ' . ($disbursement->method ?: '—'),
                ]);
            }
            foreach (($recentCollections ?? collect()) as $entry) {
                $timeline->push([
                    'when' => $entry->collected_on ?? $entry->created_at,
                    'title' => 'Collection posted',
                    'meta' => number_format((float) $entry->amount, 2) . ' via ' . ($entry->channel ?: '—'),
                ]);
            }
            if (filled($loan->notes)) {
                $timeline->push([
                    'when' => $loan->updated_at,
                    'title' => 'Notes updated',
                    'meta' => \Illuminate\Support\Str::limit((string) $loan->notes, 120),
                ]);
            }
            $timeline = $timeline
                ->filter(fn ($item) => ! empty($item['when']))
                ->sortByDesc(fn ($item) => $item['when'])
                ->values();
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Loan health overview</h2>
                    <p class="mt-1 text-xs text-slate-500">Decision-first metrics for repayment and risk tracking.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $healthMeta['class'] }}">
                    {{ $healthMeta['icon'] }} {{ $healthMeta['label'] }}
                </span>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 text-sm">
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-slate-500">Remaining balance</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ number_format($remaining, 2) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-slate-500">Next installment</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ number_format($expectedInstallmentAmount, 2) }}</p>
                    <p class="text-xs text-slate-500">{{ $nextDueDate ? $nextDueDate->format('d M Y') : 'No due date' }} · {{ $scheduleFrequencyLabel }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-slate-500">Arrears status</p>
                    <p class="mt-1 inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $arrearsClass }}">{{ $arrearsLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500">DPD: {{ $dpd }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-slate-500">Days to next payment</p>
                    <p class="mt-1 text-xl font-bold {{ is_null($daysToNextPayment) ? 'text-slate-900' : ($daysToNextPayment < 0 ? 'text-red-600' : ($daysToNextPayment <= 3 ? 'text-amber-700' : 'text-emerald-700')) }}">
                        {{ is_null($daysToNextPayment) ? '—' : $daysToNextPayment }}
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs">
                    <span class="font-semibold uppercase tracking-wide text-slate-500">Paid vs contract repayable</span>
                    <span class="font-semibold text-slate-700">{{ number_format($progress, 1) }}%</span>
                </div>
                <p class="mb-2 text-[11px] text-slate-500">Progress uses principal + interest + loan-stage fees from the current term and rate (not “paid + balance”, which can look inflated until snapshots are rebuilt).</p>
                <div class="h-2.5 w-full rounded-full bg-slate-200">
                    <div class="h-2.5 rounded-full bg-emerald-500 transition-all" style="width: {{ number_format($progress, 2, '.', '') }}%;"></div>
                </div>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs text-slate-600">
                    <p>Contract repayable: <span class="font-semibold tabular-nums text-slate-800">{{ number_format($contractRepayable, 2) }}</span></p>
                    <p>Paid: <span class="font-semibold tabular-nums text-emerald-700">{{ number_format($paid, 2) }}</span></p>
                    <p>Outstanding balance: <span class="font-semibold tabular-nums text-slate-800">{{ number_format($remaining, 2) }}</span></p>
                </div>
                @if (abs($ledgerLifeTotal - $contractRepayable) > 0.51)
                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50/90 px-2 py-1.5 text-[11px] text-amber-950">
                        Posted cash (paid + current balance) is <strong>{{ number_format($ledgerLifeTotal, 2) }}</strong>, above the contract line <strong>{{ number_format($contractRepayable, 2) }}</strong>.
                        After correcting repayment allocation, click <strong>Rebuild snapshot</strong> so principal outstanding and balance match posted payments.
                    </p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Actions</h2>
                <p class="mt-1 text-xs text-slate-500">Primary actions first, maintenance actions below.</p>
                <div class="mt-4 space-y-2">
                    <a href="{{ route('loan.payments.create', ['loan_book_loan_id' => $loan->id]) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Record repayment</a>
                    <a href="{{ route('loan.book.disbursements.create', ['loan_book_loan_id' => $loan->id]) }}" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Record disbursement</a>
                </div>
                <div class="mt-4 border-t border-slate-100 pt-4 space-y-2">
                    <a href="{{ route('loan.book.loans.edit', $loan) }}" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Edit loan</a>
                    @if ($loan->application)
                        <form method="post" action="{{ route('loan.book.loans.sync_schedule', $loan) }}" data-swal-confirm="Sync term and rate period from linked application and recalculate this loan snapshot?">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                                Sync schedule
                            </button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('loan.book.loans.rebuild_snapshot', $loan) }}" data-swal-confirm="Rebuild this loan snapshot from disbursements and processed payments?">
                        @csrf
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                            Rebuild snapshot
                        </button>
                    </form>
                </div>
                @if ($loan->application)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Application link</p>
                        <p class="mt-1 text-sm text-slate-700">Booked from {{ $loan->application?->reference ?? '—' }}</p>
                        <a href="{{ route('loan.book.applications.show', $loan->application) }}" class="mt-2 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open application</a>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Loan details</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loan summary</p>
                        <dl class="mt-2 space-y-2 text-sm">
                            <div><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $loan->loanClient?->full_name ?? '—' }}</dd></div>
                            <div><dt class="text-slate-500">Product</dt><dd class="font-medium text-slate-900">{{ $loan->product_name }}</dd></div>
                            <div><dt class="text-slate-500">Principal</dt><dd class="font-medium tabular-nums text-slate-900">{{ number_format((float) $loan->principal, 2) }}</dd></div>
                            <div><dt class="text-slate-500">Balance</dt><dd class="font-medium tabular-nums text-slate-900">{{ number_format((float) $loan->balance, 2) }}</dd></div>
                        </dl>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Terms</p>
                        <dl class="mt-2 space-y-2 text-sm">
                            <div><dt class="text-slate-500">Interest rate</dt><dd class="font-medium text-slate-900">{{ number_format((float) $loan->interest_rate, 2) }}%</dd></div>
                            <div><dt class="text-slate-500">Loan term</dt><dd class="font-medium text-slate-900">{{ $loanTermLabel }}</dd></div>
                            <div><dt class="text-slate-500">Interest applies</dt><dd class="font-medium text-slate-900">{{ $interestRatePeriodLabel }}</dd></div>
                            <div><dt class="text-slate-500">Disbursed at</dt><dd class="font-medium text-slate-900">{{ optional($loan->disbursed_at)->format('Y-m-d') ?: '—' }}</dd></div>
                            <div><dt class="text-slate-500">Maturity date</dt><dd class="font-medium text-slate-900">{{ optional($loan->maturity_date)->format('Y-m-d') ?: '—' }}</dd></div>
                        </dl>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Performance</p>
                        <dl class="mt-2 space-y-2 text-sm">
                            <div><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-900">{{ str_replace('_', ' ', $displayStatus) }}</dd></div>
                            <div><dt class="text-slate-500">Days past due</dt><dd class="font-medium {{ $dpd > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $dpd }}</dd></div>
                            <div><dt class="text-slate-500">Principal outstanding</dt><dd class="font-medium tabular-nums text-slate-900">{{ number_format((float) $loan->principal_outstanding, 2) }}</dd></div>
                            <div><dt class="text-slate-500">Interest outstanding</dt><dd class="font-medium tabular-nums text-slate-900">{{ number_format((float) $loan->interest_outstanding, 2) }}</dd></div>
                            <div><dt class="text-slate-500">Fees &amp; penalties outstanding</dt><dd class="font-medium tabular-nums text-slate-900">{{ number_format((float) $loan->fees_outstanding, 2) }}</dd></div>
                            <div><dt class="text-slate-500">Checkoff</dt><dd class="font-medium text-slate-900">{{ $loan->is_checkoff ? 'Yes' : 'No' }}</dd></div>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
                    <div><p class="text-slate-500">Principal disbursed</p><p class="font-semibold tabular-nums text-slate-900">{{ number_format($principalDisbursed, 2) }}</p></div>
                    <div><p class="text-slate-500">Realized profit</p><p class="font-semibold tabular-nums {{ $realizedProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($realizedProfit, 2) }}</p></div>
                    <div><p class="text-slate-500">Estimated total profit</p><p class="font-semibold tabular-nums {{ $estimatedTotalProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($estimatedTotalProfit, 2) }}</p></div>
                </div>
                @if (isset($nonPrincipalDrift) && abs((float) $nonPrincipalDrift) > 0.51)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/90 p-3 text-sm text-amber-950">
                        <p class="font-semibold text-amber-900">Possible mismatch vs current term / rate / fees</p>
                        <p class="mt-1 text-amber-950/95">
                            Paid plus remaining balance minus booked principal implies
                            <strong>{{ number_format((float) ($impliedContractNonPrincipal ?? 0), 2) }}</strong>
                            in interest and loan-stage fees so far.
                            Using this loan’s current term, rate, and active product charges, the system would estimate about
                            <strong>{{ number_format((float) ($bookingInterestEstimate ?? 0), 2) }}</strong> interest
                            and <strong>{{ number_format((float) ($bookingFeesEstimate ?? 0), 2) }}</strong> in fees at draw.
                            <strong>Difference: {{ number_format((float) $nonPrincipalDrift, 2) }}.</strong>
                            If you are sure there are no fees, the gap is usually from an older interest/term snapshot or a past schedule sync; use <strong>Rebuild snapshot</strong> after fixing the loan, or check whether the stored term unit (for example days vs months) matches what you expect.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-700">Repayment schedule</h3>
                <p class="mt-1 text-xs text-slate-500">Installment-by-installment expected repayment and current status ({{ strtolower($scheduleFrequencyLabel) }} frequency).</p>
            </div>
            <div class="overflow-x-auto loan-table-scroll-5">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Installment #</th>
                            <th class="px-4 py-2 text-left">Due date</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                            <th class="px-4 py-2 text-right">Paid</th>
                            <th class="px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @for ($i = 1; $i <= $termValue; $i++)
                            @php
                                $dueDate = $loan->disbursed_at ? $addScheduleStep($loan->disbursed_at, $i) : null;
                                $coveredByPaid = $paid >= ($baseInstallmentAmount * $i);
                                $paidThisInstallment = $coveredByPaid
                                    ? $baseInstallmentAmount
                                    : max(0, min($baseInstallmentAmount, $paid - ($baseInstallmentAmount * ($i - 1))));
                                $isLateInstallment = ! $coveredByPaid && $dueDate && $dueDate->isPast();
                                $scheduleStatus = $coveredByPaid ? 'Paid' : ($isLateInstallment ? 'Late' : 'Pending');
                                $scheduleClass = $coveredByPaid ? 'bg-emerald-100 text-emerald-700' : ($isLateInstallment ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                            @endphp
                            <tr>
                                <td class="px-4 py-2 tabular-nums text-slate-700">{{ $i }}</td>
                                <td class="px-4 py-2 text-slate-700">{{ $dueDate ? $dueDate->format('Y-m-d') : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format($baseInstallmentAmount, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format($paidThisInstallment, 2) }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $scheduleClass }}">{{ $scheduleStatus }}</span>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-700">Disbursements</h3>
                </div>
                <div class="overflow-x-auto loan-table-scroll-5">
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
                    <h3 class="text-sm font-semibold text-slate-700">Collections</h3>
                </div>
                <div class="overflow-x-auto loan-table-scroll-5">
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
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-slate-500">
                                        No collections yet.
                                        <a href="{{ route('loan.payments.create', ['loan_book_loan_id' => $loan->id]) }}" class="ml-1 font-semibold text-indigo-600 hover:underline">Record first payment</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Activity timeline</h2>
            <div class="mt-3 space-y-3 loan-timeline-scroll-5">
                @forelse ($timeline->take(10) as $item)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">{{ $item['title'] }}</p>
                            <p class="text-xs text-slate-500">{{ optional($item['when'])->format('Y-m-d H:i') }}</p>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $item['meta'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity entries available.</p>
                @endforelse
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
