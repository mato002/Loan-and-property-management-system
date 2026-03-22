@php
    $fmt = fn (float|int $n) => 'Ksh ' . number_format((float) $n, 2);
    $fmtInt = fn (int $n) => number_format($n);
    $colMeta = $charts['collections']['meta'] ?? ['total' => 0, 'average' => 0, 'peak_month' => null, 'peak_value' => 0, 'is_empty' => true, 'payments_6mo' => 0, 'sheet_6mo' => 0];
    $disbMeta = $charts['disbursements']['meta'] ?? ['total' => 0, 'average' => 0, 'peak_month' => null, 'peak_value' => 0, 'is_empty' => true];
    $dpdVals = $charts['dpd']['values'] ?? [];
    $dpdTotalLoans = is_array($dpdVals) ? (int) array_sum($dpdVals) : 0;
    $loanStatusVals = $charts['loanStatus']['values'] ?? [];
    $loanStatusTotal = is_array($loanStatusVals) ? (int) array_sum($loanStatusVals) : 0;
    $appStageVals = $charts['applicationStages']['values'] ?? [];
    $appStageTotal = is_array($appStageVals) ? (int) array_sum($appStageVals) : 0;
@endphp

<x-loan-layout>
    <div class="max-w-[1600px] mx-auto w-full space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Operations dashboard</h1>
                <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                    Live metrics from LoanBook, payments, clients, and accounting. Charts use the last six months of activity where dates apply.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span class="font-medium text-slate-400 uppercase tracking-wide">{{ now()->format('l, M j, Y') }}</span>
                @if ($bookReady)
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-100 px-2.5 py-1 font-semibold">LoanBook connected</span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-900 border border-amber-100 px-2.5 py-1 font-semibold">LoanBook tables missing</span>
                @endif
            </div>
        </div>

        @if (! $bookReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950">
                <strong class="font-semibold">Database setup:</strong> Core loan tables were not found. Run <code class="rounded bg-white/80 px-1 py-0.5 text-xs">php artisan migrate</code> on this environment, then refresh.
            </div>
        @endif

        {{-- KPI row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-[#2f4f4f]/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-[#2f4f4f]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active loans</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1">{{ $fmtInt($kpis['active_loans']) }}</p>
                    <p class="text-xs mt-0.5 {{ $kpis['loan_delta'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-medium">
                        {{ $kpis['loan_delta'] >= 0 ? '+' : '' }}{{ $fmtInt($kpis['loan_delta']) }} new facilities vs last month
                    </p>
                    <a href="{{ route('loan.book.loans.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline mt-1 inline-block">View loans →</a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Outstanding book</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($kpis['outstanding']) }}">{{ $fmt($kpis['outstanding']) }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Active, restructured &amp; pending disbursement balances</p>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Application pipeline</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1">{{ $fmtInt($kpis['pipeline']) }}</p>
                    <p class="text-xs text-slate-600 mt-0.5">{{ $fmtInt($kpis['credit_review']) }} in credit review</p>
                    <a href="{{ route('loan.book.applications.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline mt-1 inline-block">Applications →</a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Collections (MTD)</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($kpis['mtd_collections']) }}">{{ $fmt($kpis['mtd_collections']) }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Processed pay-ins this month</p>
                    <a href="{{ route('loan.book.collection_mtd') }}" class="text-xs font-semibold text-indigo-600 hover:underline mt-1 inline-block">Collection MTD →</a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-violet-500/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Unposted pay-ins</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1">{{ $fmtInt($kpis['unposted_payments']) }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Awaiting posting / validation</p>
                    <a href="{{ route('loan.payments.unposted') }}" class="text-xs font-semibold text-indigo-600 hover:underline mt-1 inline-block">Open queue →</a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 flex gap-4">
                <div class="w-11 h-11 rounded-lg bg-slate-200/80 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Clients &amp; risk</p>
                    <p class="text-sm font-semibold text-slate-800 mt-1 tabular-nums">{{ $fmtInt($kpis['clients']) }} clients · {{ $fmtInt($kpis['leads']) }} leads</p>
                    <p class="text-xs text-rose-700 font-medium mt-0.5">{{ $fmtInt($kpis['npl_count']) }} NPL (31+ DPD)</p>
                    <p class="text-xs text-slate-500">{{ $fmtInt($kpis['open_tickets']) }} open tickets · {{ $fmtInt($kpis['pending_advances']) }} salary advances pending</p>
                    <a href="{{ route('loan.clients.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline mt-1 inline-block">Clients →</a>
                </div>
            </div>
        </div>

        {{-- Charts: trends --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-br from-[#2f4f4f]/[0.06] via-white to-indigo-50/40 px-5 py-5 border-b border-slate-100">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-slate-900 tracking-tight">Processed collections</h2>
                                <p class="text-sm text-slate-600 mt-1 max-w-xl leading-snug">
                                    Posted pay-ins plus <span class="font-medium text-slate-800">collection sheet</span> entries, grouped by calendar month (last 6 months).
                                </p>
                            </div>
                            <a href="{{ route('loan.payments.processed') }}" class="inline-flex items-center gap-1.5 shrink-0 rounded-lg bg-white border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50 transition-colors">
                                Processed payments
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">6-mo total</p>
                                <p class="text-lg font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($colMeta['total']) }}">{{ $fmt($colMeta['total']) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Monthly avg</p>
                                <p class="text-lg font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($colMeta['average']) }}">{{ $fmt($colMeta['average']) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Peak month</p>
                                @if (! empty($colMeta['peak_month']))
                                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ $colMeta['peak_month'] }}</p>
                                    <p class="text-xs text-slate-600 tabular-nums">{{ $fmt($colMeta['peak_value']) }}</p>
                                @else
                                    <p class="text-sm text-slate-500 mt-2">—</p>
                                @endif
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm col-span-2 sm:col-span-1">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Split (6 mo)</p>
                                <p class="text-xs text-slate-700 mt-1.5 leading-relaxed">
                                    <span class="font-semibold text-slate-900">{{ $fmt($colMeta['payments_6mo'] ?? 0) }}</span> pay-ins
                                    <span class="text-slate-400 mx-0.5">·</span>
                                    <span class="font-semibold text-slate-900">{{ $fmt($colMeta['sheet_6mo'] ?? 0) }}</span> sheet
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 pb-2 pt-1">
                    <div class="h-80 relative rounded-xl border border-emerald-100 bg-white p-3 shadow-sm">
                        <canvas id="chartCollections" aria-label="Collections bar and trend chart"></canvas>
                    </div>
                    @if ($colMeta['is_empty'] ?? true)
                        <p class="text-center text-xs text-slate-500 mt-3 px-2">
                            No amounts in the last six months yet.
                            <a href="{{ route('loan.payments.create') }}" class="font-semibold text-indigo-600 hover:underline">Record a pay-in</a>
                            or use the
                            <a href="{{ route('loan.book.collection_sheet.index') }}" class="font-semibold text-indigo-600 hover:underline">collection sheet</a>.
                        </p>
                    @endif
                </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-br from-indigo-50/50 via-white to-violet-50/30 px-5 py-5 border-b border-slate-100">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-slate-900 tracking-tight">Disbursements</h2>
                                <p class="text-sm text-slate-600 mt-1 max-w-xl leading-snug">
                                    Cash disbursed from LoanBook, by <span class="font-medium text-slate-800">disbursement date</span> (last 6 months).
                                </p>
                            </div>
                            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center gap-1.5 shrink-0 rounded-lg bg-white border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50 transition-colors">
                                All disbursements
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">6-mo total</p>
                                <p class="text-lg font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($disbMeta['total']) }}">{{ $fmt($disbMeta['total']) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Monthly avg</p>
                                <p class="text-lg font-bold text-slate-900 tabular-nums mt-1 truncate" title="{{ $fmt($disbMeta['average']) }}">{{ $fmt($disbMeta['average']) }}</p>
                            </div>
                            <div class="rounded-xl bg-white/90 border border-slate-200/80 px-3 py-3 shadow-sm col-span-2 sm:col-span-1">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Peak month</p>
                                @if (! empty($disbMeta['peak_month']))
                                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ $disbMeta['peak_month'] }}</p>
                                    <p class="text-xs text-slate-600 tabular-nums">{{ $fmt($disbMeta['peak_value']) }}</p>
                                @else
                                    <p class="text-sm text-slate-500 mt-2">—</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 pb-2 pt-1">
                    <div class="h-80 relative rounded-xl border border-violet-200 bg-white p-3 shadow-sm">
                        <canvas id="chartDisbursements" aria-label="Disbursements bar chart"></canvas>
                    </div>
                    @if ($disbMeta['is_empty'] ?? true)
                        <p class="text-center text-xs text-slate-500 mt-3 px-2">
                            Bars will fill as you post disbursements.
                            <a href="{{ route('loan.book.disbursements.create') }}" class="font-semibold text-indigo-600 hover:underline">New disbursement</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Charts: composition --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-50/80 to-white px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Active loans by DPD</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Days past due — active facilities only</p>
                    </div>
                    <span class="text-xs font-bold text-emerald-900 bg-emerald-100/90 border border-emerald-200/80 rounded-full px-3 py-1 tabular-nums">{{ $fmtInt($dpdTotalLoans) }} loans</span>
                </div>
                <div class="p-5">
                    <div class="h-64 relative max-w-md mx-auto rounded-xl border border-emerald-100 bg-white p-2 shadow-sm">
                        <canvas id="chartDpd" aria-label="DPD distribution"></canvas>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="{{ route('loan.book.loan_arrears') }}" class="text-sm font-semibold text-indigo-600 hover:underline">Loan arrears report →</a>
                    </div>
                </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-violet-50/80 to-white px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Loans by book status</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Full LoanBook portfolio mix</p>
                    </div>
                    <span class="text-xs font-bold text-violet-900 bg-violet-100/90 border border-violet-200/80 rounded-full px-3 py-1 tabular-nums">{{ $fmtInt($loanStatusTotal) }} total</span>
                </div>
                <div class="p-5">
                    <div class="h-64 relative max-w-md mx-auto rounded-xl border border-violet-100 bg-white p-2 shadow-sm">
                        <canvas id="chartLoanStatus" aria-label="Loan status mix"></canvas>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="{{ route('loan.analytics.performance') }}" class="text-sm font-semibold text-indigo-600 hover:underline">Business analytics →</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-[#2f4f4f]/10 to-indigo-50/40 px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Applications by stage</h2>
                    <p class="text-xs text-slate-600 mt-0.5">Origination funnel volume</p>
                </div>
                <span class="text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-full px-3 py-1 tabular-nums">{{ $fmtInt($appStageTotal) }} applications</span>
            </div>
            <div class="p-5">
                <div class="h-72 relative rounded-xl border border-cyan-100 bg-white p-3 shadow-sm">
                    <canvas id="chartAppStages" aria-label="Applications by stage"></canvas>
                </div>
            </div>
        </div>

        {{-- Lists --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col min-h-[280px]">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center gap-2">
                    <h2 class="text-lg font-medium text-slate-800">Largest arrears balances</h2>
                    @if ($kpis['arrears_accounts'] > 0)
                        <span class="text-xs font-semibold text-rose-800 bg-rose-50 border border-rose-100 rounded-full px-2.5 py-0.5 whitespace-nowrap">{{ $fmtInt($kpis['arrears_accounts']) }} accounts</span>
                    @endif
                </div>
                <div class="p-5 flex-1">
                    <div class="flex items-baseline gap-3 mb-4">
                        <span class="text-2xl font-bold text-slate-900 tabular-nums">{{ $fmt($kpis['arrears_total']) }}</span>
                        <span class="text-sm text-slate-500">total past due (active / restructured)</span>
                    </div>
                    @forelse ($topArrears as $loan)
                        <div class="flex justify-between items-center gap-3 bg-slate-50/80 p-3 rounded-lg border border-slate-100 mb-2 last:mb-0">
                            <a href="{{ route('loan.book.loans.edit', $loan) }}" class="text-indigo-600 font-medium hover:underline text-sm min-w-0 truncate" title="{{ $loan->loan_number }}">
                                {{ $loan->loan_number }} · {{ $loan->loanClient?->full_name ?? 'Client' }}
                                <span class="text-slate-500 font-normal"> · {{ $loan->dpd }} DPD</span>
                            </a>
                            <span class="text-slate-800 font-semibold text-sm tabular-nums flex-shrink-0">{{ $fmt((float) $loan->balance) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 py-6 text-center">No arrears — every active loan is at 0 DPD or the book is empty.</p>
                    @endforelse
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
                    <a href="{{ route('loan.book.loan_arrears') }}" class="text-indigo-600 font-semibold text-sm hover:underline">Full arrears list →</a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col min-h-[280px]" x-data="{ tab: 'open' }">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-slate-800">Applications</h2>
                </div>
                <div class="flex border-b border-slate-200 px-5 gap-1">
                    <button type="button" @click="tab = 'open'" :class="tab === 'open' ? 'border-b-[3px] border-[#2f4f4f] text-slate-900 font-semibold' : 'text-slate-500 font-medium hover:text-slate-700'" class="py-2.5 px-2 text-sm -mb-px transition-colors">In pipeline</button>
                    <button type="button" @click="tab = 'hint'" :class="tab === 'hint' ? 'border-b-[3px] border-[#2f4f4f] text-slate-900 font-semibold' : 'text-slate-500 font-medium hover:text-slate-700'" class="py-2.5 px-2 text-sm -mb-px transition-colors">Shortcuts</button>
                </div>
                <div class="p-5 flex-1 space-y-3 min-h-[200px]">
                    <div x-show="tab === 'open'" x-cloak class="space-y-3">
                        @forelse ($recentApplications as $app)
                            @php
                                $stageClass = match ($app->stage) {
                                    \App\Models\LoanBookApplication::STAGE_CREDIT_REVIEW => 'bg-amber-50 text-amber-900 border-amber-100',
                                    \App\Models\LoanBookApplication::STAGE_APPROVED => 'bg-emerald-50 text-emerald-900 border-emerald-100',
                                    default => 'bg-slate-50 text-slate-700 border-slate-100',
                                };
                            @endphp
                            <div class="pb-3 border-b border-slate-100 last:border-0 last:pb-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <a href="{{ route('loan.book.applications.edit', $app) }}" class="text-indigo-600 font-semibold hover:underline text-sm">{{ $fmt((float) $app->amount_requested) }}</a>
                                    <span class="text-xs font-medium rounded-full px-2 py-0.5 border {{ $stageClass }}">{{ str_replace('_', ' ', ucfirst($app->stage)) }}</span>
                                </div>
                                <p class="text-sm text-slate-800">{{ \Illuminate\Support\Str::limit($app->product_name, 48) }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $app->loanClient?->full_name ?? 'Client' }}
                                    @if ($app->submitted_at)
                                        · {{ $app->submitted_at->diffForHumans() }}
                                    @endif
                                    @if ($app->branch)
                                        · {{ $app->branch }}
                                    @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 py-4">No open applications — everything is disbursed or declined.</p>
                        @endforelse
                    </div>
                    <div x-show="tab === 'hint'" x-cloak class="text-sm text-slate-600 space-y-2">
                        <p>Use <strong class="text-slate-800">LoanBook → Applications</strong> to move stages, attach notes, and convert approvals into facilities.</p>
                        <p>Credit committee items usually sit in <strong class="text-slate-800">Credit review</strong> or <strong class="text-slate-800">Approved</strong> before disbursement.</p>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end mt-auto">
                    <a href="{{ route('loan.book.applications.index') }}" class="text-indigo-600 font-semibold text-sm hover:underline">All applications →</a>
                </div>
            </div>
        </div>

        {{-- Accounting / ops strip --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Requisitions</p>
                    <p class="text-xl font-bold text-slate-900 tabular-nums mt-0.5">{{ $fmtInt($opsStrip['pending_requisitions']) }}</p>
                    <p class="text-xs text-slate-500">Pending approval</p>
                </div>
                <a href="{{ route('loan.accounting.requisitions.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline shrink-0">Open →</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Journal</p>
                    <p class="text-xl font-bold text-slate-900 tabular-nums mt-0.5">{{ $fmtInt($opsStrip['journal_last_30']) }}</p>
                    <p class="text-xs text-slate-500">Entries last 30 days</p>
                </div>
                <a href="{{ route('loan.accounting.journal.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline shrink-0">Ledger →</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Books of account</p>
                    <p class="text-sm font-semibold text-slate-800 mt-1">Reports &amp; registers</p>
                    <p class="text-xs text-slate-500">COA, payroll, assets</p>
                </div>
                <a href="{{ route('loan.accounting.books') }}" class="text-xs font-semibold text-indigo-600 hover:underline shrink-0">Hub →</a>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm px-5 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Quick actions</h2>
                <p class="text-xs text-slate-500 mt-0.5">Jump into the workflows you use every day.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('loan.book.applications.create') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">New application</a>
                <a href="{{ route('loan.payments.create') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Record pay-in</a>
                <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Collection sheet</a>
                <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">New disbursement</a>
                <a href="{{ route('loan.accounting.chart.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors">Chart of accounts</a>
                <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#2f4f4f] bg-[#2f4f4f] px-3 py-2 text-xs font-semibold text-white hover:bg-[#264040] transition-colors">Loan arrears</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;

            const charts = @json($charts);

            const gridColor = 'rgba(15, 23, 42, 0.07)';
            const tickColor = '#475569';

            const moneyAxisTicks = {
                color: tickColor,
                callback: function (value) {
                    const n = Number(value);
                    if (!Number.isFinite(n)) return '';
                    if (n >= 1e6) return 'Ksh ' + (n / 1e6).toFixed(1) + 'M';
                    if (n >= 1e3) return 'Ksh ' + (n / 1e3).toFixed(0) + 'k';
                    return 'Ksh ' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
                },
            };

            const moneyTooltip = {
                callbacks: {
                    label: function (ctx) {
                        const v = ctx.parsed.y ?? ctx.parsed;
                        const n = typeof v === 'number' ? v : (v != null ? Number(v) : 0);
                        return ' Ksh ' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                },
            };

            function toNumbers(arr) {
                return (arr || []).map(function (v) { return Number(v) || 0; });
            }

            function yScaleMoney(maxVal) {
                const m = Math.max(0, maxVal);
                return {
                    beginAtZero: true,
                    grace: m > 0 ? '12%' : '0%',
                    suggestedMin: 0,
                    ticks: moneyAxisTicks,
                    border: { display: false },
                    grid: { color: gridColor, drawTicks: false },
                };
            }

            const colVals = toNumbers(charts.collections.values);
            const colMax = colVals.length ? Math.max.apply(null, colVals) : 0;
            const colEl = document.getElementById('chartCollections');
            if (colEl) {
                const barHi = '#22c55e';
                const barLo = '#86efac';
                const barBorder = '#15803d';
                const lineBright = '#f97316';
                const lineFill = 'rgba(249, 115, 22, 0.18)';
                const barColors = colVals.map(function (v) {
                    return Number(v) > 0 ? barHi : barLo;
                });
                new Chart(colEl, {
                    type: 'bar',
                    data: {
                        labels: charts.collections.labels || [],
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Monthly total',
                                data: colVals,
                                backgroundColor: barColors,
                                borderColor: barBorder,
                                borderWidth: 2,
                                borderRadius: 8,
                                minBarLength: 8,
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Trend line',
                                data: colVals,
                                borderColor: lineBright,
                                backgroundColor: lineFill,
                                tension: 0.35,
                                pointRadius: 6,
                                pointHoverRadius: 9,
                                pointBackgroundColor: '#fff',
                                pointBorderWidth: 3,
                                pointBorderColor: lineBright,
                                borderWidth: 3,
                                fill: true,
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    boxHeight: 12,
                                    padding: 18,
                                    usePointStyle: true,
                                    font: { size: 12, weight: '600' },
                                    color: '#0f172a',
                                },
                            },
                            tooltip: {
                                filter: function (item) {
                                    return item.datasetIndex === 0;
                                },
                                callbacks: moneyTooltip.callbacks,
                            },
                        },
                        scales: {
                            y: yScaleMoney(colMax),
                            x: {
                                grid: { display: false },
                                ticks: { maxRotation: 45, minRotation: 0, color: tickColor, font: { weight: '500' } },
                                border: { display: false },
                            },
                        },
                    },
                });
            }

            const disVals = toNumbers(charts.disbursements.values);
            const disMax = disVals.length ? Math.max.apply(null, disVals) : 0;
            const disBarColors = disVals.map(function (v) {
                return Number(v) > 0 ? 'rgba(79, 70, 229, 0.55)' : 'rgba(165, 180, 252, 0.45)';
            });
            const disEl = document.getElementById('chartDisbursements');
            if (disEl) {
                new Chart(disEl, {
                    type: 'bar',
                    data: {
                        labels: charts.disbursements.labels || [],
                        datasets: [{
                            label: 'Disbursed',
                            data: disVals,
                            backgroundColor: disBarColors,
                            borderColor: 'rgb(79, 70, 229)',
                            borderWidth: 1,
                            borderRadius: 8,
                            minBarLength: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: { legend: { display: false }, tooltip: moneyTooltip },
                        scales: {
                            y: yScaleMoney(disMax),
                            x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0 } },
                        },
                    },
                });
            }

            const dpdEl = document.getElementById('chartDpd');
            if (dpdEl) {
                const dpdLabels = charts.dpd.labels || [];
                const dpdValues = toNumbers(charts.dpd.values);
                const dpdSum = dpdValues.reduce(function (a, b) { return a + b; }, 0);
                let dL = dpdLabels;
                let dV = dpdValues;
                let dC = ['#22c55e', '#facc15', '#fb7185'];
                if (dpdSum === 0) {
                    dL = ['No active loans in buckets'];
                    dV = [1];
                    dC = ['#94a3b8'];
                }
                new Chart(dpdEl, {
                    type: 'doughnut',
                    data: {
                        labels: dL,
                        datasets: [{
                            data: dV,
                            backgroundColor: dC,
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 10,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '52%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 14,
                                    padding: 14,
                                    font: { size: 12, weight: '600' },
                                    color: '#0f172a',
                                },
                            },
                        },
                    },
                });
            }

            const statusColors = [
                '#14b8a6',
                '#8b5cf6',
                '#22c55e',
                '#f97316',
                '#ec4899',
                '#3b82f6',
            ];

            const loanStatusEl = document.getElementById('chartLoanStatus');
            if (loanStatusEl) {
                let lsLabels = charts.loanStatus.labels || [];
                let lsValues = toNumbers(charts.loanStatus.values);
                let lsColors;
                if (!lsLabels.length) {
                    lsLabels = ['No loans in LoanBook yet'];
                    lsValues = [1];
                    lsColors = ['#94a3b8'];
                } else {
                    lsColors = lsLabels.map(function (_, i) {
                        return statusColors[i % statusColors.length];
                    });
                }
                new Chart(loanStatusEl, {
                    type: 'doughnut',
                    data: {
                        labels: lsLabels,
                        datasets: [{
                            data: lsValues,
                            backgroundColor: lsColors,
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 10,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '52%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 14,
                                    padding: 12,
                                    font: { size: 12, weight: '600' },
                                    color: '#0f172a',
                                },
                            },
                        },
                    },
                });
            }

            const appStEl = document.getElementById('chartAppStages');
            if (appStEl) {
                let appLabels = charts.applicationStages.labels || [];
                let appValues = toNumbers(charts.applicationStages.values);
                let appPlaceholder = false;
                if (!appLabels.length) {
                    appLabels = ['No applications yet'];
                    appValues = [1];
                    appPlaceholder = true;
                }
                const appBarColors = ['#06b6d4', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];
                const appColors = appPlaceholder
                    ? ['#cbd5e1']
                    : appValues.map(function (_, i) {
                        return appBarColors[i % appBarColors.length];
                    });
                new Chart(appStEl, {
                    type: 'bar',
                    data: {
                        labels: appLabels,
                        datasets: [{
                            label: 'Applications',
                            data: appValues,
                            backgroundColor: appColors,
                            borderColor: appPlaceholder ? '#94a3b8' : '#1e293b',
                            borderWidth: 2,
                            borderRadius: 8,
                            minBarLength: 8,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, color: tickColor, font: { weight: '500' } },
                                grid: { color: gridColor },
                                border: { display: false },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { maxRotation: 40, minRotation: 0, color: tickColor, font: { weight: '500' } },
                                border: { display: false },
                            },
                        },
                    },
                });
            }
        });
    </script>
</x-loan-layout>
