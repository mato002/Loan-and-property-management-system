<x-loan-layout>
    <style>
        .print-only-header { display: none; }
        @media print {
            .print-only-header { display: block !important; margin-bottom: 10px; border-bottom: 2px solid #1a5f7a; padding-bottom: 8px; }
            aside, nav, .sidebar, .navbar, .filter-section, .no-print, button, a[href*="reversal"], a[href*="journal"] { display: none !important; }
            main, .main-content, .content, .max-w-\[1600px\], .max-w-\[1800px\] { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .shadow-sm, .shadow, .shadow-md, .bg-slate-50, .bg-slate-100 { box-shadow: none !important; background: #fff !important; }
            table, th, td { color: #000 !important; font-size: 10pt !important; }
            thead { display: table-header-group !important; }
            tr { page-break-inside: avoid !important; break-inside: avoid !important; }
            @page { size: A4 landscape; margin: 12mm; }
        }
    </style>

    @php
        $pageItems = $payments->getCollection();
        $processedVisibleAmount = (float) $pageItems->sum(fn ($p) => (float) ($p->amount ?? 0));
        $processedTodayCount = (int) $pageItems->filter(fn ($p) => optional($p->transaction_at)?->isToday())->count();
        $reversalEligible = (int) $pageItems->filter(fn ($p) => $p->payment_kind !== \App\Models\LoanBookPayment::KIND_C2B_REVERSAL)->count();
        $withJournal = (int) $pageItems->filter(fn ($p) => ! is_null($p->accounting_journal_entry_id))->count();
        $autoPostedRatio = $pageItems->count() > 0 ? (int) round(($withJournal / $pageItems->count()) * 100) : 0;
        $autoGaugeOffset = 220 - (($autoPostedRatio / 100) * 220);
        $reviewCount = (int) $pageItems->filter(fn ($p) => blank($p->validatedByUser?->name) && blank($p->postedByUser?->name))->count();
    @endphp

    <x-loan.page
        title="PROCESSED PAYMENTS LEDGER"
        subtitle="System: Kenya · Branch: Nakuru · Last refresh: {{ now()->format('M j, Y g:i A') }}"
    >
        @include('loan.payments.partials.flash')

        <section class="space-y-5 bg-slate-50/70">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Processed (KES)</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">KES {{ number_format((float) ($totalAmount ?? $processedVisibleAmount), 2) }}</p>
                    <p class="mt-1 text-xs text-purple-700">{{ number_format($payments->total()) }} rows · {{ $processedTodayCount }} today</p>
                    <svg class="mt-2 h-5 w-full text-purple-500" viewBox="0 0 120 20" fill="none" aria-hidden="true">
                        <path d="M2 14c12-9 18-9 30 0s18 9 30 0 18-9 30 0 18 9 26 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    @php
                        $principalProcessed = 0.0;
                        $interestProcessed = 0.0;
                        $chargesProcessed = 0.0;
                        $totalProcessedForBreakdown = (float) ($totalAmount ?? $processedVisibleAmount);

                        foreach ($pageItems as $payment) {
                            $paymentAmount = (float) ($payment->amount ?? 0);
                            $lines = collect($payment->accountingJournalEntry?->lines ?? []);

                            if ($lines->isEmpty()) {
                                $principalProcessed += $paymentAmount;
                                continue;
                            }

                            $bucketPrincipal = 0.0;
                            $bucketInterest = 0.0;
                            $bucketCharges = 0.0;

                            foreach ($lines as $line) {
                                $lineAmount = (float) (($line->debit ?: $line->credit) ?: 0);
                                if ($lineAmount <= 0) {
                                    continue;
                                }

                                $accountName = strtolower((string) ($line->account?->name ?? ''));
                                if (str_contains($accountName, 'interest')) {
                                    $bucketInterest += $lineAmount;
                                } elseif (
                                    str_contains($accountName, 'fee') ||
                                    str_contains($accountName, 'penalt') ||
                                    str_contains($accountName, 'charge') ||
                                    str_contains($accountName, 'insurance') ||
                                    str_contains($accountName, 'levy')
                                ) {
                                    $bucketCharges += $lineAmount;
                                } else {
                                    $bucketPrincipal += $lineAmount;
                                }
                            }

                            $bucketTotal = $bucketPrincipal + $bucketInterest + $bucketCharges;
                            if ($bucketTotal <= 0.0) {
                                $principalProcessed += $paymentAmount;
                                continue;
                            }

                            $scale = $paymentAmount > 0 ? ($paymentAmount / $bucketTotal) : 0.0;
                            $principalProcessed += $bucketPrincipal * $scale;
                            $interestProcessed += $bucketInterest * $scale;
                            $chargesProcessed += $bucketCharges * $scale;
                        }

                        if ($totalProcessedForBreakdown <= 0) {
                            $totalProcessedForBreakdown = $principalProcessed + $interestProcessed + $chargesProcessed;
                        }
                        if ($totalProcessedForBreakdown <= 0) {
                            $totalProcessedForBreakdown = 1;
                        }

                        $principalPercent = max(0, min(100, ($principalProcessed / $totalProcessedForBreakdown) * 100));
                        $interestPercent = max(0, min(100, ($interestProcessed / $totalProcessedForBreakdown) * 100));
                        $chargesPercent = max(0, min(100, ($chargesProcessed / $totalProcessedForBreakdown) * 100));
                    @endphp
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Processed Breakdown (MTD)</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">KES {{ number_format($totalProcessedForBreakdown, 2) }}</p>
                    <div class="mt-3 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Principal Processed</span>
                            <span class="font-semibold text-slate-800">KES {{ number_format($principalProcessed, 2) }} <span class="font-normal text-slate-500">({{ number_format($principalPercent, 0) }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Interest Processed</span>
                            <span class="font-semibold text-slate-800">KES {{ number_format($interestProcessed, 2) }} <span class="font-normal text-slate-500">({{ number_format($interestPercent, 0) }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-slate-600">Charges Processed</span>
                            <span class="font-semibold text-slate-800">KES {{ number_format($chargesProcessed, 2) }} <span class="font-normal text-slate-500">({{ number_format($chargesPercent, 0) }}%)</span></span>
                        </div>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                        <div class="flex h-full w-full">
                            <span class="h-full bg-teal-700" style="width: {{ $principalPercent }}%"></span>
                            <span class="h-full bg-emerald-600" style="width: {{ $interestPercent }}%"></span>
                            <span class="h-full bg-amber-500" style="width: {{ $chargesPercent }}%"></span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Distribution of processed repayments (MTD) · {{ number_format($payments->total()) }} rows · {{ $processedTodayCount }} today</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reversal Eligible</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $reversalEligible }}</p>
                    <p class="mt-1 text-xs text-amber-700">Rows that can be reversed safely</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Needs Review</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $reviewCount }}</p>
                    <p class="mt-1 text-xs text-purple-700">Missing validator or posting actor metadata</p>
                </article>
            </div>

            <form method="get" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ advancedFilters:false }">
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                        <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                        <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Corporate</label>
                        <select name="corporate" class="h-10 min-w-44 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                            <option value="">All</option>
                            @foreach (($corporateOptions ?? collect()) as $option)
                                <option value="{{ $option }}" @selected(($corporate ?? '') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Pay Mode</label>
                        <select name="pay_mode" class="h-10 min-w-36 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                            <option value="">All</option>
                            @foreach (($payModeOptions ?? collect()) as $option)
                                <option value="{{ $option }}" @selected(($payMode ?? '') === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[260px] flex-1">
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, Loan #, Client, Phone..." class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Source</label>
                        <select name="source" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                            <option value="">All</option>
                            <option value="sms_forwarder" @selected(($source ?? '') === 'sms_forwarder')>SMS Forwarder</option>
                            <option value="manual" @selected(($source ?? '') === 'manual')>Manual/Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                        <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                            @foreach ([10, 20, 25, 50, 100, 200] as $size)
                                <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" @click="advancedFilters=!advancedFilters" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Advanced Filters</button>
                    <button type="submit" class="h-10 rounded-lg bg-teal-800 px-4 text-sm font-semibold text-white hover:bg-teal-900">Filter</button>
                    <a href="{{ route('loan.payments.processed') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <div class="ml-auto flex flex-wrap items-center gap-2 no-print">
                        <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
                        <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                        <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
                        <a href="{{ route('loan.payments.processed.print', request()->query()) }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print</a>
                    </div>
                </div>
                <div x-show="advancedFilters" x-cloak class="mt-3 grid grid-cols-1 gap-2 border-t border-slate-100 pt-3 md:grid-cols-4">
                    <input type="text" placeholder="Branch..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Destination account..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Approver..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Amount range..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm">
                <div class="print-only-header">
                    <div class="text-xl font-bold text-slate-900">Gaitho Loans</div>
                    <div class="text-sm font-semibold text-slate-800">PROCESSED PAYMENTS LEDGER</div>
                    <div class="text-xs text-slate-600">Branch: Nakuru · Generated: {{ now()->format('M j, Y g:i A') }} · User: {{ auth()->user()?->name ?? 'System' }}</div>
                </div>
                <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Processed pays for {{ \Carbon\Carbon::parse($displayDate)->format('d-m-Y') }}</h2>
                    <p class="text-xs text-slate-500">{{ $payments->total() }} row(s) · Ksh {{ number_format((float) ($totalAmount ?? 0), 2) }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[980px] w-full table-auto text-[11px]">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="border-b border-r border-slate-300 px-3 py-3">Transaction</th>
                            <th class="border-b border-r border-slate-300 px-3 py-3 text-right">Amount</th>
                            <th class="border-b border-r border-slate-300 px-3 py-3">Payment Details</th>
                            <th class="border-b border-r border-slate-300 px-3 py-3">Client</th>
                            <th class="border-b border-r border-slate-300 px-3 py-3">Message</th>
                            <th class="border-b border-r border-slate-300 px-3 py-3">Approval</th>
                            <th class="border-b border-slate-300 px-3 py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @forelse ($payments as $p)
                            @php
                                $processedRowUrl = $p->loan?->loanClient
                                    ? route('loan.clients.show', $p->loan->loanClient)
                                    : null;
                            @endphp
                            <tr
                                class="hover:bg-slate-50/80 {{ $processedRowUrl ? 'cursor-pointer' : '' }}"
                                @if ($processedRowUrl)
                                    role="link"
                                    tabindex="0"
                                    onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $processedRowUrl }}';"
                                    onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ $processedRowUrl }}'; }"
                                @endif
                            >
                                <td class="border-b border-r border-slate-200 px-4 py-3 font-mono text-xs text-blue-700">{{ $p->reference }}</td>
                                <td class="border-b border-r border-slate-200 px-4 py-3 text-right tabular-nums font-semibold text-emerald-700">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="border-b border-r border-slate-200 px-4 py-3 text-slate-600">
                                    @php
                                        $journalLines = collect($p->accountingJournalEntry?->lines ?? []);
                                        $principalAmount = 0.0;
                                        $interestAmount = 0.0;
                                        $chargesAmount = 0.0;

                                        foreach ($journalLines as $line) {
                                            $lineAmount = (float) (($line->debit ?: $line->credit) ?: 0);
                                            if ($lineAmount <= 0) {
                                                continue;
                                            }

                                            $accountName = strtolower((string) ($line->account?->name ?? ''));
                                            if (str_contains($accountName, 'interest')) {
                                                $interestAmount += $lineAmount;
                                            } elseif (
                                                str_contains($accountName, 'fee') ||
                                                str_contains($accountName, 'penalt') ||
                                                str_contains($accountName, 'charge') ||
                                                str_contains($accountName, 'insurance') ||
                                                str_contains($accountName, 'levy')
                                            ) {
                                                $chargesAmount += $lineAmount;
                                            } else {
                                                $principalAmount += $lineAmount;
                                            }
                                        }

                                        $breakdownTotal = $principalAmount + $interestAmount + $chargesAmount;
                                    @endphp
                                    <ul class="list-disc space-y-0.5 pl-4">
                                        @if ($breakdownTotal > 0)
                                            @if ($principalAmount > 0)
                                                <li>Principal - {{ number_format($principalAmount, 2) }}</li>
                                            @endif
                                            @if ($interestAmount > 0)
                                                <li>Interest - {{ number_format($interestAmount, 2) }}</li>
                                            @endif
                                            @if ($chargesAmount > 0)
                                                <li>Charges - {{ number_format($chargesAmount, 2) }}</li>
                                            @endif
                                        @else
                                            <li>{{ ucfirst(str_replace('_', ' ', (string) $p->payment_kind)) }} - {{ number_format((float) $p->amount, 2) }}</li>
                                        @endif
                                        @if ($p->mpesa_receipt_number)
                                            <li>Receipt - {{ $p->mpesa_receipt_number }}</li>
                                        @endif
                                    </ul>
                                </td>
                                <td class="border-b border-r border-slate-200 px-4 py-3 text-slate-600">
                                    @if ($p->loan?->loanClient)
                                        <a href="{{ route('loan.clients.show', $p->loan->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $p->loan->loanClient->full_name }}
                                        </a>
                                        <span class="block text-[11px] text-slate-500">{{ $p->loan->loanClient->id_number ?? '—' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="border-b border-r border-slate-200 px-4 py-3 text-slate-600 whitespace-pre-wrap">{{ $p->message ?? '—' }}</td>
                                <td class="border-b border-r border-slate-200 px-4 py-3 text-xs text-slate-600">
                                    <div class="space-y-1">
                                        <div>
                                            <span class="inline-flex rounded-full border {{ ($p->validatedByUser || $p->postedByUser) ? 'border-emerald-200 bg-emerald-100 text-emerald-700' : 'border-purple-200 bg-purple-100 text-purple-700' }} px-2 py-0.5 text-[11px] font-semibold">
                                                {{ ($p->validatedByUser || $p->postedByUser) ? 'Confirmed' : 'System Posted' }}
                                            </span>
                                        </div>
                                        <div>{{ $p->validatedByUser?->name ?? $p->postedByUser?->name ?? 'System' }}</div>
                                    </div>
                                </td>
                                <td class="border-b border-slate-200 px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    <span>{{ optional($p->transaction_at)->format('h:i a') ?? '—' }}</span>
                                    @if ($p->payment_kind !== \App\Models\LoanBookPayment::KIND_C2B_REVERSAL)
                                        <a href="{{ route('loan.payments.reversal.create', ['from' => $p->id]) }}" class="mt-1 block text-blue-600 hover:underline">
                                            Reverse
                                        </a>
                                    @endif
                                    @if ($p->accounting_journal_entry_id)
                                        <a href="{{ route('loan.accounting.journal.show', $p->accounting_journal_entry_id) }}" class="mt-1 block text-indigo-600 hover:underline">
                                            Journal
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No processed payments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $payments->links() }}
                </div>
            @endif
            </div>
        </section>
    </x-loan.page>
</x-loan-layout>
