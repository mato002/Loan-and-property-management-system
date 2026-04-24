<x-loan-layout>
    <x-loan.page
        title="Books of account"
        subtitle="Central hub for the general ledger, operational registers, payroll, budgets, and financial statements."
        :showQuickLinks="false"
    >
        @include('loan.accounting.partials.flash')

        <x-slot name="actions">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-800">
                    Quick Insights
                </button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    System Alerts
                </button>
            </div>
        </x-slot>

        @php
            $bankBalance = (float) data_get($booksHubMetrics ?? [], 'bank_balance', 0);
            $agedReceivables = (float) data_get($booksHubMetrics ?? [], 'aged_receivables', 0);
            $agedPayables = (float) data_get($booksHubMetrics ?? [], 'aged_payables', 0);
            $newEntries30d = (int) data_get($booksHubMetrics ?? [], 'new_entries_30d', 0);
            $pendingPayrollRuns = (int) data_get($booksHubMetrics ?? [], 'pending_payroll_runs', 0);
            $budgetLinesCount = (int) data_get($booksHubMetrics ?? [], 'budget_lines_count', 0);
            $expenseRecords30d = (int) data_get($booksHubMetrics ?? [], 'expense_records_30d', 0);
            $unpostedLoanGlItems = (int) data_get($booksHubMetrics ?? [], 'unposted_loan_gl_items', 0);
            $unpostedProcessedPayments = (int) data_get($booksHubMetrics ?? [], 'unposted_processed_payments', 0);
            $unpostedDisbursements = (int) data_get($booksHubMetrics ?? [], 'unposted_disbursements', 0);
            $isBankOverdrawn = $bankBalance < 0;
            $bankBalanceDisplay = number_format(abs($bankBalance), 2);
        @endphp

        <div class="space-y-6 bg-slate-50/60 rounded-2xl p-3 sm:p-4 lg:p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Quick-Look Dashboard</h2>
                </div>
                @if ($unpostedLoanGlItems > 0)
                    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">GL sync warning: {{ number_format($unpostedLoanGlItems) }} loan transactions are not journal-posted yet.</p>
                        <p class="mt-1">
                            Processed payments pending GL: {{ number_format($unpostedProcessedPayments) }}
                            &middot;
                            Disbursements pending GL: {{ number_format($unpostedDisbursements) }}.
                        </p>
                    </div>
                @endif
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-xl border p-4 {{ $isBankOverdrawn ? 'border-rose-100 bg-rose-50/70' : 'border-emerald-100 bg-emerald-50/70' }}">
                        <p class="text-sm font-medium text-slate-600">Total Bank Balance</p>
                        @if ($isBankOverdrawn)
                            <p class="mt-1 text-3xl font-semibold tracking-tight text-rose-700">Overdrawn: KSh {{ $bankBalanceDisplay }}</p>
                        @else
                            <p class="mt-1 text-3xl font-semibold tracking-tight text-emerald-700">KSh {{ $bankBalanceDisplay }}</p>
                        @endif
                        <svg class="mt-3 h-8 w-full {{ $isBankOverdrawn ? 'text-rose-500' : 'text-emerald-500' }}" viewBox="0 0 120 24" fill="none" aria-hidden="true">
                            <path d="M2 18C14 10 24 12 34 8C44 4 55 16 65 12C74 8 83 6 92 10C102 14 110 9 118 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </article>

                    <article class="rounded-xl border border-amber-100 bg-amber-50/70 p-4">
                        <p class="text-sm font-medium text-slate-600">Aged Receivables (30-60 Days)</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-amber-700">KSh {{ number_format($agedReceivables, 2) }}</p>
                        <svg class="mt-3 h-8 w-full text-amber-500" viewBox="0 0 120 24" fill="none" aria-hidden="true">
                            <path d="M2 8C12 6 22 10 32 14C41 18 50 20 60 15C70 10 79 7 90 9C101 11 109 14 118 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </article>

                    <article class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 sm:col-span-2 xl:col-span-1">
                        <p class="text-sm font-medium text-slate-600">Aged Payables (Current)</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-blue-700">KSh {{ number_format($agedPayables, 2) }}</p>
                        <svg class="mt-3 h-8 w-full text-blue-500" viewBox="0 0 120 24" fill="none" aria-hidden="true">
                            <path d="M2 12C12 6 22 7 33 11C45 15 54 20 66 15C78 10 88 4 99 6C108 8 114 10 118 11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </article>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-2">
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Daily Entries</h3>
                    <div class="grid gap-3">
                        <a href="{{ route('loan.accounting.journal.create') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">Create Journal Entry</h4>
                                <p class="mt-1 text-sm text-slate-500">Start a new, manual general ledger entry.</p>
                                <span class="mt-3 inline-flex items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Add Journal Entry</span>
                            </div>
                        </a>

                        <a href="{{ route('loan.accounting.company_expenses.index') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 6h5m-5 4h8"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">Company Expenses</h4>
                                <p class="mt-1 text-sm text-slate-500">Record, track, and approve corporate expenses.</p>
                                <p class="mt-1 text-xs font-medium text-slate-400">{{ number_format($expenseRecords30d) }} records captured in last 30 days</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Management Reporting</h3>
                    <div class="grid gap-3">
                        <a href="{{ route('loan.accounting.reports.hub') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M7 15V9m5 6V6m5 9v-3"/></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">Financial Statements and Reports</h4>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ number_format($newEntries30d) }} new entries (30d)</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">Analyze performance against branch &amp; company budgets.</p>
                            </div>
                        </a>

                        <a href="{{ route('loan.accounting.budget.report') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 5h6m-6 4h6m-6 4h4"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">Budget Reports</h4>
                                <p class="mt-1 text-sm text-slate-500">Track and analyze financial planning and allocations.</p>
                                <p class="mt-1 text-xs font-medium text-slate-400">{{ number_format($budgetLinesCount) }} configured budget lines</p>
                            </div>
                        </a>

                        <a href="{{ route('loan.accounting.journal.index') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.5-4.5M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">View Posted Entries</h4>
                                <p class="mt-1 text-sm text-slate-500">Search, filter, and review all finalized entries.</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">General Ledger &amp; Compliance</h3>
                    <div class="grid gap-3">
                        <a href="{{ route('loan.accounting.payroll.hub') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a4 4 0 1 1 8 0v2m-9 0h10a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1Z"/></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">Employee Payroll</h4>
                                    <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">{{ number_format($pendingPayrollRuns) }} payroll runs pending</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">Manage payroll entries, dues, and employee payout records.</p>
                            </div>
                        </a>

                        <a href="{{ route('loan.accounting.ledger') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h9"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">Journal Register &amp; Ledger Access</h4>
                                <p class="mt-1 text-sm text-slate-500">Review ledger postings and audit trails.</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Payroll</h3>
                    <div class="grid gap-3">
                        <a href="{{ route('loan.accounting.books.chart_rules') }}" class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2 4h8m-8 4h8m-8 4h5"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-slate-900">Chart of Accounts &amp; Rules</h4>
                                <p class="mt-1 text-sm text-slate-500">Define and manage company account structure and rules.</p>
                            </div>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
