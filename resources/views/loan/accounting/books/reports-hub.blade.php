<x-loan-layout>
    <x-loan.page
        title="Financial Statements and Reports"
        subtitle="Financial statements from the general ledger."
    >
        @php
            $fmt = fn (float|int $value) => 'KSh '.number_format((float) $value, 2);
            $todayLabel = now()->format('l, F j, Y');
            $periodLabel = $from->format('M j, Y').' - '.$to->format('M j, Y');
            $openPeriodLabel = $from->format('F Y').($to->isSameMonth($from) ? ' (Open)' : '');
            $surplusDelta = $surplusGrowthPct === null
                ? null
                : (($surplusGrowthPct >= 0 ? '+' : '').number_format($surplusGrowthPct, 1).'%');
        @endphp

        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                Books hub
            </a>
        </x-slot>

        <div id="fiControlTower" class="space-y-6 rounded-2xl bg-slate-100/60 p-4 sm:p-5 lg:p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-1">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Real-Time Financial Intelligence (Cash-Basis: Fortress Lenders)</h1>
                        <p class="text-sm text-slate-600">Real-time visibility into liquidity, performance, and compliance.</p>
                    </div>

                    <div class="w-full max-w-lg space-y-3 lg:text-right">
                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <p class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M3.5 9.5h17m-15 2.5h2.5m3 0h2.5m3 0h2.5M5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4Z" />
                                </svg>
                                {{ $todayLabel }}
                            </p>
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Period: {{ $openPeriodLabel }}</span>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-3 text-left">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Time Traveler</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button type="button" data-modal-open="timeTravelerModal" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition hover:border-blue-300 hover:text-blue-700">
                                    <svg class="h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-2.64-6.36" />
                                    </svg>
                                    {{ $periodLabel }}
                                </button>
                                <button type="button" data-modal-open="quickAccessModal" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" />
                                    </svg>
                                    Quick Access &amp; Templates
                                </button>
                                <button type="button" data-modal-open="quickAccessModal" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition hover:bg-blue-700" aria-label="Open quick template modal">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Real-time data synced to this range</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Tier 1 (Top): The Real-Time Profitability Dashboard</h2>
                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">
                        <input id="comparisonToggle" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Compare (Prev. Month/Year)
                    </label>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-2xl border border-teal-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-700">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 17.5V6.5a1.5 1.5 0 0 1 1.5-1.5h13A1.5 1.5 0 0 1 20 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5Zm4-7h8m-8 4h6" />
                                    </svg>
                                </span>
                                <h3 class="text-base font-semibold text-slate-900">Real-Time Income Statement</h3>
                            </div>
                            <button type="button" class="text-slate-400 transition hover:text-blue-600" title="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Net Cash Surplus</p>
                        <button type="button" data-peek-title="Net Cash Surplus Journal Triggers" data-peek-content="Mapped cash-basis postings from collected interest and processing fees less realized OPEX and payroll disbursements for the selected period." class="mt-1 text-left text-4xl font-semibold tracking-tight text-emerald-700">
                            {{ $fmt($netCashSurplus) }}
                        </button>
                        <p class="mt-1 text-xs font-medium text-emerald-700">
                            {{ $surplusDelta !== null ? $surplusDelta.' vs previous matched period' : 'No previous period baseline available' }}
                        </p>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <button type="button" data-peek-title="Total Cash Collected - Journal Entries" data-peek-content="Collections are sourced from posted cash receipts tagged to Interest Income and Processing Fee Income within the selected range." class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-left">
                                <p class="font-semibold text-slate-700">Total Cash Collected</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $fmt($totalCashCollected) }}</p>
                                <p class="text-slate-500">Interest: {{ $fmt($interestIncome) }}</p>
                                <p class="text-slate-500">Processing Fees: {{ $fmt($processingFees) }}</p>
                            </button>
                            <button type="button" data-peek-title="Total Cash Spent - Journal Entries" data-peek-content="Cash outflows include realized OPEX and payroll settlements posted against cash accounts in the same time window." class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-left">
                                <p class="font-semibold text-slate-700">Total Cash Spent</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $fmt($totalCashSpent) }}</p>
                                <p class="text-slate-500">OPEX: {{ $fmt($opexCash) }}</p>
                                <p class="text-slate-500">Salaries: {{ $fmt($salaryCash) }}</p>
                            </button>
                        </div>

                        <svg class="mt-4 h-16 w-full text-teal-600" viewBox="0 0 260 70" fill="none" aria-hidden="true">
                            <rect x="35" y="20" width="60" height="34" rx="4" fill="currentColor" fill-opacity=".20"/>
                            <rect x="145" y="27" width="60" height="27" rx="4" fill="currentColor" fill-opacity=".45"/>
                            <line x1="20" y1="54" x2="240" y2="54" stroke="currentColor" stroke-opacity=".40"/>
                            <text x="45" y="66" font-size="9" fill="currentColor">Cash Revenue</text>
                            <text x="157" y="66" font-size="9" fill="currentColor">Cash OPEX</text>
                        </svg>

                        <span class="mt-2 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">Cash-Basis: Realized Income Only</span>
                    </article>

                    <article class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 7v5.5c0 4 3.1 7.9 7.5 8.8 4.4-.9 7.5-4.8 7.5-8.8V7L12 3Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.5 11 15l4-5" />
                                    </svg>
                                </span>
                                <h3 class="text-base font-semibold text-slate-900">Liquidity &amp; Statutory Shield</h3>
                            </div>
                            <button type="button" class="text-slate-400 transition hover:text-blue-600" title="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Aggregate Cash Position</p>
                        <button type="button" data-peek-title="Aggregate Cash Position Sources" data-peek-content="Combined visibility across mapped bank ledgers and M-Pesa utility accounts with intraday refresh from posted books." class="mt-1 text-left text-4xl font-semibold tracking-tight text-blue-700">
                            {{ $fmt($aggregateCashPosition) }}
                        </button>

                        <div class="mt-4 space-y-2 text-sm">
                            <p class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-slate-700">
                                <span>Equity Bank (All Accounts)</span>
                                <span class="font-semibold">{{ $fmt($equityBankBalance) }}</span>
                            </p>
                            <p class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-slate-700">
                                <span>M-Pesa Utility (Till &amp; Float)</span>
                                <span class="font-semibold">{{ $fmt($mpesaBalance) }}</span>
                            </p>
                        </div>

                        <div class="mt-4 rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm">
                            <p class="font-semibold text-orange-700">Estimated KRA Liability <span class="float-right">Due in 10 Days</span></p>
                            <p class="mt-1 text-orange-700">PAYE: {{ $fmt($payeLiability) }} · VAT: {{ $fmt($vatLiability) }} · Corporate Tax (Est.): {{ $fmt($corporateTaxLiability) }}</p>
                        </div>

                        <svg class="mt-4 h-16 w-full" viewBox="0 0 260 70" fill="none" aria-hidden="true">
                            <path d="M30 52a100 100 0 0 1 200 0" stroke="#cbd5e1" stroke-width="14" stroke-linecap="round"/>
                            <path d="M30 52a100 100 0 0 1 135-90" stroke="#22c55e" stroke-width="14" stroke-linecap="round"/>
                            <path d="M165 8a100 100 0 0 1 65 44" stroke="#f59e0b" stroke-width="14" stroke-linecap="round"/>
                            <line x1="130" y1="52" x2="192" y2="24" stroke="#334155" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="130" cy="52" r="5" fill="#334155"/>
                        </svg>

                        <p class="mt-2 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700">
                            Approaching statutory deadline &amp; minimum cash floors
                        </p>
                    </article>

                    <article id="managementCard" class="rounded-2xl border border-orange-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-orange-100 text-orange-700">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 16.5 9 12l3 2.5L19 7.5M5 5v14h14" />
                                    </svg>
                                </span>
                                <h3 class="text-base font-semibold text-slate-900">Management Efficiency (Burn Rate)</h3>
                            </div>
                            <button type="button" class="text-slate-400 transition hover:text-blue-600" title="Audit history">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Operating Margin</p>
                        <button id="operatingMarginValue" type="button" data-peek-title="Operating Margin Drivers" data-peek-content="Margin tracks realized cash revenue against immediately realized OPEX. Comparison mode overlays prior period variance for early burn detection." class="mt-1 text-left text-4xl font-semibold tracking-tight text-orange-700">
                            {{ number_format($operatingMargin, 1) }}%
                        </button>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                Cost Per Client
                                <span class="mt-1 block text-xl font-semibold text-slate-900">{{ $fmt($costPerClient) }}</span>
                            </p>
                            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                Collection Efficiency
                                <span class="mt-1 block text-xl font-semibold text-slate-900">{{ number_format($collectionEfficiency, 1) }}%</span>
                            </p>
                        </div>

                        <svg class="mt-4 h-16 w-full text-orange-500" viewBox="0 0 260 70" fill="none" aria-hidden="true">
                            <polyline id="trendCurrentLine" points="20,48 70,44 120,40 170,35 220,31" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" />
                            <polyline id="trendOverlayLine" points="20,53 70,50 120,46 170,42 220,39" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4 4" fill="none" stroke-linecap="round" class="hidden" />
                            <line x1="20" y1="58" x2="230" y2="58" stroke="#cbd5e1"/>
                        </svg>
                        <p class="text-xs text-slate-500">Helps detect inefficiency even when profit remains high.</p>
                    </article>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Tier 2: The Statement Vault (2x3 Grid of Report Tiles)</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <a href="{{ route('loan.accounting.reports.income_statement') }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-blue-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 5h6m-6 4h6m-6 4h4"/></svg>
                            Income Statement (P&amp;L)
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Analyze performance with realized revenue streams (interest vs processing fees).</p>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 5 mins ago
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('loan.accounting.reports.balance_sheet') }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-blue-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>
                            Balance Sheet
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Real-time statement of financial position focused on net loan portfolio and equity.</p>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 10 mins ago
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('loan.accounting.reports.trial_balance') }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-blue-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M6 6h12M9 6v12m6-12v12"/></svg>
                            Trial Balance
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">The auditor's view: debit/credit integrity of all cash-basis accounts.</p>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 15 mins ago
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('loan.accounting.cashflow') }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Board &amp; KRA Pack</p>
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-blue-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2ZM9 12h6m-6 4h6"/></svg>
                            Cash Flow Statement
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Map cash movement between M-Pesa utility and main bank accounts.</p>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 30 mins ago
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('loan.accounting.payroll.settings.statutory') }}" class="group block rounded-2xl border border-orange-200 bg-white p-4 shadow-sm transition hover:border-orange-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Strategic &amp; Compliance Pack</p>
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-orange-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h8l5 5v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5M9 13h7M9 17h5"/></svg>
                            Tax / KRA Ledger
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Summarize PAYE, VAT, NSSF, NHIF, SHIF obligations for the period.</p>
                        <div class="mt-2 rounded-md border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">Next KRA Due: In 10 Days · May 5, 2026</div>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 40 mins ago
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('loan.accounting.expense_summary') }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <h3 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900 group-hover:text-blue-700">
                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M7 15V9m5 6V6m5 9v-3"/></svg>
                            Management Report
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Consolidated view of financial and operational signals (disbursement and client performance trends).</p>
                        <div class="mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m-4 8a9 9 0 1 1 9-9" /></svg>
                                Last generated: 1 hour ago
                            </span>
                        </div>
                    </a>
                </div>
            </section>
        </div>

        <aside id="peekPanel" class="pointer-events-none fixed right-4 top-24 z-50 w-full max-w-md translate-x-4 opacity-0 transition duration-200">
            <div class="pointer-events-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                <div class="flex items-center justify-between gap-3">
                    <h4 id="peekPanelTitle" class="text-sm font-semibold text-slate-900">Entry Drill-Down</h4>
                    <button type="button" data-peek-close class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close details panel">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <p id="peekPanelContent" class="mt-2 text-sm text-slate-600"></p>
            </div>
        </aside>

        <div id="timeTravelerModal" class="modal-shell fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/45 p-4">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Time Traveler</h3>
                    <button type="button" data-modal-close class="rounded-md p-1 text-slate-500 hover:bg-slate-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <p class="mt-2 text-sm text-slate-600">Synchronize every financial intelligence tile to a single reporting range.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-700">Start Date
                        <input type="date" value="{{ $from->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm text-slate-700">End Date
                        <input type="date" value="{{ $to->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                </div>
                <p class="mt-2 text-xs text-slate-500">Real-time data synced to this range</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Cancel</button>
                    <button type="button" data-modal-close class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Apply Range</button>
                </div>
            </div>
        </div>

        <div id="quickAccessModal" class="modal-shell fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/45 p-4">
            <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Quick Access &amp; Templates</h3>
                    <button type="button" data-modal-close class="rounded-md p-1 text-slate-500 hover:bg-slate-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <p class="mt-2 text-sm text-slate-600">Configure reusable accounting templates with governance and approval controls.</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-slate-700">Template Name
                        <input type="text" placeholder="e.g. Monthly Statutory Settlement" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm text-slate-700">COA Mapping (Debit/Credit)
                        <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option>Cash &rarr; Tax Liability</option>
                            <option>Expense &rarr; Cash Account</option>
                        </select>
                    </label>
                    <label class="text-sm text-slate-700">Governance Rules (Maker-Checker)
                        <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option>Required for all postings</option>
                            <option>Required above KSh 100,000</option>
                        </select>
                    </label>
                    <label class="text-sm text-slate-700">Amount Logic
                        <select class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option>Variable amount</option>
                            <option>Fixed amount</option>
                        </select>
                    </label>
                </div>

                <label class="mt-3 flex items-center justify-between rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-sm text-purple-700">
                    Approval Controls Enabled
                    <input type="checkbox" checked class="h-4 w-4 rounded border-purple-300 text-purple-600 focus:ring-purple-500">
                </label>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Dismiss</button>
                    <button type="button" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm">Create Template</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const modalOpenButtons = document.querySelectorAll('[data-modal-open]');
                const modalCloseButtons = document.querySelectorAll('[data-modal-close]');
                const modals = document.querySelectorAll('.modal-shell');
                const comparisonToggle = document.getElementById('comparisonToggle');
                const trendOverlayLine = document.getElementById('trendOverlayLine');
                const operatingMarginValue = document.getElementById('operatingMarginValue');
                const managementCard = document.getElementById('managementCard');
                const peekPanel = document.getElementById('peekPanel');
                const peekTitle = document.getElementById('peekPanelTitle');
                const peekContent = document.getElementById('peekPanelContent');
                const peekTargets = document.querySelectorAll('[data-peek-title]');
                const peekClose = document.querySelector('[data-peek-close]');

                modalOpenButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const modalId = button.getAttribute('data-modal-open');
                        const modal = document.getElementById(modalId);
                        if (modal) {
                            modal.classList.remove('hidden');
                            modal.classList.add('flex');
                        }
                    });
                });

                const closeModals = () => {
                    modals.forEach((modal) => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    });
                };

                modalCloseButtons.forEach((button) => button.addEventListener('click', closeModals));

                modals.forEach((modal) => {
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            closeModals();
                        }
                    });
                });

                if (comparisonToggle) {
                    const baseMarginLabel = operatingMarginValue ? operatingMarginValue.textContent : '';
                    comparisonToggle.addEventListener('change', () => {
                        const isOn = comparisonToggle.checked;
                        trendOverlayLine.classList.toggle('hidden', !isOn);
                        if (operatingMarginValue) {
                            operatingMarginValue.textContent = baseMarginLabel;
                        }
                        managementCard.classList.toggle('ring-2', isOn);
                        managementCard.classList.toggle('ring-orange-200', isOn);
                    });
                }

                peekTargets.forEach((target) => {
                    target.addEventListener('click', () => {
                        peekTitle.textContent = target.getAttribute('data-peek-title') || 'Entry Drill-Down';
                        peekContent.textContent = target.getAttribute('data-peek-content') || 'No detail available.';
                        peekPanel.classList.remove('opacity-0', 'translate-x-4', 'pointer-events-none');
                    });
                });

                if (peekClose) {
                    peekClose.addEventListener('click', () => {
                        peekPanel.classList.add('opacity-0', 'translate-x-4', 'pointer-events-none');
                    });
                }
            })();
        </script>
    </x-loan.page>
</x-loan-layout>
