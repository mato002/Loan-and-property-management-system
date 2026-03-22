<x-loan-layout>
    <x-loan.page
        title="Books of account"
        subtitle="Central hub for the general ledger, operational registers, payroll, budgets, and financial statements."
    >
        @include('loan.accounting.partials.flash')

        @php
            $cards = [
                [
                    'href' => route('loan.accounting.journal.create'),
                    'title' => 'Create journal entry',
                    'desc' => 'Manual post an entry to the journal record.',
                    'icon' => 'plus',
                ],
                [
                    'href' => route('loan.accounting.books.chart_rules'),
                    'title' => 'Chart of accounts & rules',
                    'desc' => 'List of accounts used by the company & accounting rules.',
                    'icon' => 'chart-tree',
                ],
                [
                    'href' => route('loan.accounting.company_expenses.index'),
                    'title' => 'Company expenses',
                    'desc' => 'View and manage company expenses.',
                    'icon' => 'banknote',
                ],
                [
                    'href' => route('loan.accounting.ledger'),
                    'title' => 'General ledger',
                    'desc' => 'Description for the accounts activities.',
                    'icon' => 'list',
                ],
                [
                    'href' => route('loan.accounting.company_assets.index'),
                    'title' => 'Company assets',
                    'desc' => 'Register and manage company assets.',
                    'icon' => 'document',
                ],
                [
                    'href' => route('loan.accounting.journal.index'),
                    'title' => 'View posted entries',
                    'desc' => 'Retrieve and manage posted entries.',
                    'icon' => 'search',
                ],
                [
                    'href' => route('loan.accounting.reports.hub'),
                    'title' => 'Accruals & reports',
                    'desc' => 'Income statement, trial balance & balance sheet reports.',
                    'icon' => 'chart-bar',
                ],
                [
                    'href' => route('loan.accounting.payroll.hub'),
                    'title' => 'Employee payroll',
                    'desc' => 'Salary dues, payroll and payslips.',
                    'icon' => 'leaf',
                ],
                [
                    'href' => route('loan.accounting.budget.report'),
                    'title' => 'Budget reports',
                    'desc' => 'Budget analysis for branches & estimates.',
                    'icon' => 'sliders',
                ],
                [
                    'href' => route('loan.accounting.reconciliation.index'),
                    'title' => 'Accounts reconciliation',
                    'desc' => 'Reconcile operating financial accounts.',
                    'icon' => 'refresh',
                ],
            ];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 max-w-5xl">
            @foreach ($cards as $c)
                <a href="{{ $c['href'] }}" class="flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition-all group">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 group-hover:bg-blue-100 transition-colors">
                        @switch($c['icon'])
                            @case('plus')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                @break
                            @case('chart-tree')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                @break
                            @case('banknote')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @break
                            @case('list')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                @break
                            @case('document')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                @break
                            @case('search')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                @break
                            @case('chart-bar')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                @break
                            @case('leaf')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                @break
                            @case('sliders')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                @break
                            @case('refresh')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @break
                        @endswitch
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-slate-900 group-hover:text-blue-800 transition-colors">{{ $c['title'] }}</h2>
                        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">{{ $c['desc'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </x-loan.page>
</x-loan-layout>
