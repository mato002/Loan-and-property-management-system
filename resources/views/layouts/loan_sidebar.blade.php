<!-- Mobile Sidebar Backdrop -->
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/80 backdrop-blur-sm md:hidden" @click="sidebarOpen = false" x-cloak></div>

<!-- Sidebar Elements -->
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full max-md:pointer-events-none'" class="fixed inset-y-0 left-0 z-50 w-72 bg-[#2f4f4f] border-r border-[#264040] flex flex-col min-h-0 transition-transform duration-300 md:relative md:translate-x-0 overflow-hidden flex-shrink-0 text-[#d4e4e3] shadow-2xl md:shadow-none">
    <!-- Header -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md">
        <a href="{{ route('dashboard') }}" class="text-xl font-bold text-white flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            Loan Manager
        </a>
        <button @click="sidebarOpen = false" class="md:hidden p-2 rounded-md text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation List -->
    <nav class="flex-1 min-h-0 overflow-y-scroll py-6 px-4 space-y-6 custom-scrollbar">

        <!-- Dashboard Link -->
        <div>
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-[#d4e4e3] hover:bg-white/10 hover:text-white transition-all font-medium group">
                <svg class="w-5 h-5 text-[#8db1af] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
        </div>

        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div>
                <a href="{{ route('superadmin.users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-[#d4e4e3] hover:bg-white/10 hover:text-white transition-all font-medium group">
                    <svg class="w-5 h-5 text-[#8db1af] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 10H7a2 2 0 01-2-2V6a2 2 0 012-2h6l4 4v12a2 2 0 01-2 2z" />
                    </svg>
                    Super Admin
                </a>
            </div>
        @endif

        @php
            $loanRole = auth()->user()?->loan_role;
            $isSuperAdmin = (bool) (auth()->user()?->is_super_admin ?? false);
            $isAccountantOnly = ! $isSuperAdmin && $loanRole === 'accountant';

            $menu = [
                'Employees' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>',
                    'links' => [
                        ['label' => 'Add Employee', 'route' => 'loan.employees.create'],
                        ['label' => 'Staff Leaves', 'route' => 'loan.employees.leaves'],
                        ['label' => 'Staff Groups', 'route' => 'loan.employees.groups'],
                        ['label' => 'Staff Portfolios', 'route' => 'loan.employees.portfolios'],
                        ['label' => 'Loan Applications', 'route' => 'loan.employees.loan_applications'],
                        ['label' => 'Staff Loans', 'route' => 'loan.employees.staff_loans'],
                        ['label' => 'Daily Workplan', 'route' => 'loan.employees.workplan'],
                        ['label' => 'View Employees', 'route' => 'loan.employees.index'],
                    ],
                ],
                'Accounting' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                    'items' => [
                        ['label' => 'Employee Payroll', 'route' => 'loan.accounting.payroll.hub', 'active_pattern' => 'loan.accounting.payroll.*'],
                        ['label' => 'Requisitions', 'route' => 'loan.accounting.requisitions.index', 'active_pattern' => 'loan.accounting.requisitions.*'],
                        ['label' => 'Utility Payments', 'route' => 'loan.accounting.utilities.index', 'active_pattern' => 'loan.accounting.utilities.*'],
                        ['label' => 'Petty Cashbook', 'route' => 'loan.accounting.petty.index', 'active_pattern' => 'loan.accounting.petty.*'],
                        ['label' => 'Books of Account', 'route' => 'loan.accounting.books', 'active_patterns' => [
                            'loan.accounting.books',
                            'loan.accounting.books.*',
                            'loan.accounting.chart.*',
                            'loan.accounting.journal.*',
                            'loan.accounting.ledger',
                            'loan.accounting.reports.*',
                            'loan.accounting.company_expenses.*',
                            'loan.accounting.company_assets.*',
                            'loan.accounting.payroll.*',
                            'loan.accounting.budget.*',
                            'loan.accounting.reconciliation.*',
                        ]],
                        ['label' => 'Salary Advances', 'route' => 'loan.accounting.advances.index', 'active_pattern' => 'loan.accounting.advances.*'],
                        ['label' => 'Expense Summary', 'route' => 'loan.accounting.expense_summary', 'active_pattern' => 'loan.accounting.expense_summary'],
                        ['label' => 'Cashflow', 'route' => 'loan.accounting.cashflow', 'active_pattern' => 'loan.accounting.cashflow'],
                    ],
                ],
                'Branches & Regions' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
                    'items' => [
                        ['label' => 'Create region', 'route' => 'loan.regions.create', 'active_patterns' => ['loan.regions.create']],
                        ['label' => 'View regions', 'route' => 'loan.regions.index', 'active_patterns' => ['loan.regions.index', 'loan.regions.edit', 'loan.regions.update', 'loan.regions.destroy']],
                        ['label' => 'Add branch', 'route' => 'loan.branches.create', 'active_patterns' => ['loan.branches.create']],
                        ['label' => 'View branches', 'route' => 'loan.branches.index', 'active_patterns' => ['loan.branches.index', 'loan.branches.edit', 'loan.branches.update', 'loan.branches.destroy']],
                        ['label' => 'Loan summary', 'route' => 'loan.branches.loan_summary', 'active_pattern' => 'loan.branches.loan_summary'],
                    ],
                ],
                'Business Analytics' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>',
                    'links' => [
                        ['label' => 'Loan Sizes', 'route' => 'loan.analytics.loan_sizes', 'active_pattern' => 'loan.analytics.loan_sizes*'],
                        ['label' => 'Targets & Accruals', 'route' => 'loan.analytics.targets', 'active_pattern' => 'loan.analytics.targets*'],
                        ['label' => 'Business Performance', 'route' => 'loan.analytics.performance', 'active_pattern' => 'loan.analytics.performance*'],
                    ],
                ],
                'Clients' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'links' => [
                        ['label' => 'Add Client', 'route' => 'loan.clients.create'],
                        ['label' => 'Create a Lead', 'route' => 'loan.clients.leads.create'],
                        ['label' => 'Transfer Clients', 'route' => 'loan.clients.transfer'],
                        ['label' => 'Default Groups', 'route' => 'loan.clients.default_groups'],
                        ['label' => 'Interactions', 'route' => 'loan.clients.interactions'],
                        ['label' => 'Client Leads', 'route' => 'loan.clients.leads'],
                        ['label' => 'View Clients', 'route' => 'loan.clients.index'],
                    ],
                ],
                'LoanBook' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                    'items' => [
                        ['label' => 'Create Application', 'route' => 'loan.book.applications.create', 'active_pattern' => 'loan.book.applications.create'],
                        ['label' => 'Loan Applications', 'route' => 'loan.book.applications.index', 'active_patterns' => ['loan.book.applications.index', 'loan.book.applications.edit']],
                        ['label' => 'Collection MTD', 'route' => 'loan.book.collection_mtd', 'active_pattern' => 'loan.book.collection_mtd'],
                        ['label' => 'Disbursements', 'route' => 'loan.book.disbursements.index', 'active_pattern' => 'loan.book.disbursements.*'],
                        ['label' => 'Collection sheet', 'route' => 'loan.book.collection_sheet.index', 'active_pattern' => 'loan.book.collection_sheet.*'],
                        ['label' => 'Collection Reports', 'route' => 'loan.book.collection_reports', 'active_pattern' => 'loan.book.collection_reports'],
                        ['label' => 'Collection Rates', 'route' => 'loan.book.collection_rates.index', 'active_pattern' => 'loan.book.collection_rates.*'],
                        ['label' => 'Collection Agents', 'route' => 'loan.book.collection_agents.index', 'active_pattern' => 'loan.book.collection_agents.*'],
                        ['label' => 'Loan Arrears', 'route' => 'loan.book.loan_arrears', 'active_pattern' => 'loan.book.loan_arrears'],
                        ['label' => 'App Loans Report', 'route' => 'loan.book.app_loans_report', 'active_pattern' => 'loan.book.app_loans_report'],
                        ['label' => 'Checkoff Loans', 'route' => 'loan.book.checkoff_loans', 'active_pattern' => 'loan.book.checkoff_loans'],
                        ['label' => 'View Loans', 'route' => 'loan.book.loans.index', 'active_patterns' => ['loan.book.loans.index', 'loan.book.loans.edit', 'loan.book.loans.create']],
                    ],
                ],
                'Payments' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'items' => [
                        ['label' => 'Unposted Payments', 'route' => 'loan.payments.unposted', 'active_patterns' => ['loan.payments.unposted', 'loan.payments.create', 'loan.payments.edit', 'loan.payments.store', 'loan.payments.update', 'loan.payments.destroy', 'loan.payments.post']],
                        ['label' => 'Processed Payments', 'route' => 'loan.payments.processed', 'active_pattern' => 'loan.payments.processed'],
                        ['label' => 'Prepayments', 'route' => 'loan.payments.prepayments', 'active_pattern' => 'loan.payments.prepayments'],
                        ['label' => 'Overpayments', 'route' => 'loan.payments.overpayments', 'active_pattern' => 'loan.payments.overpayments'],
                        ['label' => 'Merged Payments', 'route' => 'loan.payments.merged', 'active_patterns' => ['loan.payments.merged', 'loan.payments.merge', 'loan.payments.merge.store']],
                        ['label' => 'C2B Reversals', 'route' => 'loan.payments.c2b_reversals', 'active_patterns' => ['loan.payments.c2b_reversals', 'loan.payments.reversal.create', 'loan.payments.reversal.store']],
                        ['label' => 'Receipts', 'route' => 'loan.payments.receipts', 'active_pattern' => 'loan.payments.receipts'],
                        ['label' => 'Payin Summary', 'route' => 'loan.payments.payin_summary', 'active_pattern' => 'loan.payments.payin_summary'],
                        ['label' => 'Payments Report', 'route' => 'loan.payments.report', 'active_patterns' => ['loan.payments.report', 'loan.payments.report.export']],
                        ['label' => 'Validate Payment', 'route' => 'loan.payments.validate', 'active_patterns' => ['loan.payments.validate', 'loan.payments.validate.store']],
                    ],
                ],
                'Bulk SMS' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
                    'links' => [
                        ['label' => 'Send / schedule SMS', 'route' => 'loan.bulksms.compose'],
                        ['label' => 'Create SMS template', 'route' => 'loan.bulksms.templates.create'],
                        ['label' => 'SMS templates', 'route' => 'loan.bulksms.templates.index'],
                        ['label' => 'SMS logs', 'route' => 'loan.bulksms.logs'],
                        ['label' => 'Top up wallet', 'route' => 'loan.bulksms.wallet'],
                        ['label' => 'SMS schedules', 'route' => 'loan.bulksms.schedules'],
                    ],
                ],
                'Financial' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'links' => [
                        ['label' => 'M-Pesa platform', 'route' => 'loan.financial.mpesa_platform'],
                        ['label' => 'M-Pesa payouts', 'route' => 'loan.financial.mpesa_payouts'],
                        ['label' => 'Account balances', 'route' => 'loan.financial.account_balances'],
                        ['label' => 'Teller operations', 'route' => 'loan.financial.teller_operations'],
                        ['label' => 'Investment packages', 'route' => 'loan.financial.investment_packages'],
                        ['label' => 'Investors list', 'route' => 'loan.financial.investors_list'],
                        ['label' => 'Investors reports', 'route' => 'loan.financial.investors_reports'],
                    ],
                ],
                'Asset Financing' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
                    'items' => [
                        ['label' => 'Measurement Units', 'route' => 'loan.assets.units.index', 'active_patterns' => ['loan.assets.units.index', 'loan.assets.units.create', 'loan.assets.units.edit']],
                        ['label' => 'Asset Categories', 'route' => 'loan.assets.categories.index', 'active_patterns' => ['loan.assets.categories.index', 'loan.assets.categories.create', 'loan.assets.categories.edit']],
                        ['label' => 'Add Asset/Stock', 'route' => 'loan.assets.items.create', 'active_pattern' => 'loan.assets.items.create'],
                        ['label' => 'Asset List/Stock', 'route' => 'loan.assets.items.index', 'active_patterns' => ['loan.assets.items.index', 'loan.assets.items.edit']],
                    ],
                ],
                'My Account' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                    'items' => [
                        ['label' => 'View Details', 'route' => 'loan.account.show'],
                        ['label' => 'My Workplan', 'route' => 'loan.employees.workplan'],
                        ['label' => 'Salary Advance', 'route' => 'loan.account.salary_advance'],
                        ['label' => 'My Staff Loans', 'route' => 'loan.employees.staff_loans'],
                        ['label' => 'Approval Requests', 'route' => 'loan.account.approval_requests'],
                        ['label' => 'Update Details', 'route' => 'profile.edit', 'fragment' => 'update-profile'],
                    ],
                ],
                'System & Help' => [
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    'items' => [
                        ['label' => 'Create a Ticket', 'route' => 'loan.system.tickets.create', 'active_patterns' => ['loan.system.tickets.create']],
                        ['label' => 'Raised Tickets', 'route' => 'loan.system.tickets.index', 'active_patterns' => ['loan.system.tickets.index', 'loan.system.tickets.show', 'loan.system.tickets.edit']],
                        ['label' => 'System Setup', 'route' => 'loan.system.setup', 'active_patterns' => ['loan.system.setup', 'loan.system.setup.company', 'loan.system.setup.preferences', 'loan.system.form_setup.*']],
                        ['label' => 'Access Logs', 'route' => 'loan.system.access_logs.index', 'active_pattern' => 'loan.system.access_logs.index'],
                    ],
                ],
            ];
        @endphp

        <div class="space-y-3">
            @foreach($menu as $groupName => $data)
            @php
                if ($isAccountantOnly && ! in_array($groupName, ['Accounting', 'Financial', 'My Account', 'System & Help'], true)) {
                    continue;
                }
            @endphp
            <div x-data="{ open: {{ (
                ($groupName === 'Employees' && request()->routeIs('loan.employees.*')) ||
                ($groupName === 'Accounting' && request()->routeIs('loan.accounting.*')) ||
                ($groupName === 'Branches & Regions' && (request()->routeIs('loan.regions.*') || request()->routeIs('loan.branches.*'))) ||
                ($groupName === 'Business Analytics' && request()->routeIs('loan.analytics.*')) ||
                ($groupName === 'Clients' && request()->routeIs('loan.clients.*')) ||
                ($groupName === 'LoanBook' && request()->routeIs('loan.book.*')) ||
                ($groupName === 'Payments' && request()->routeIs('loan.payments.*')) ||
                ($groupName === 'Bulk SMS' && request()->routeIs('loan.bulksms.*')) ||
                ($groupName === 'Financial' && request()->routeIs('loan.financial.*')) ||
                ($groupName === 'Asset Financing' && request()->routeIs('loan.assets.*')) ||
                ($groupName === 'My Account' && (request()->routeIs('loan.account.*') || request()->routeIs('profile.*'))) ||
                ($groupName === 'System & Help' && request()->routeIs('loan.system.*'))
            ) ? 'true' : 'false' }} }">
                <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-[#d4e4e3] hover:bg-[#406866]/80 hover:text-white transition-all group">
                    <div class="flex items-center gap-3 w-4/5">
                        <svg class="w-5 h-5 flex-shrink-0 text-[#8db1af] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            {!! $data['icon'] !!}
                        </svg>
                        <span class="font-medium truncate text-left">{{ $groupName }}</span>
                    </div>
                    <svg :class="{'rotate-180 text-white': open}" class="w-4 h-4 text-[#8db1af] transition-transform duration-300 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak 
                     class="mt-1 space-y-1">
                    <div class="pl-11 pr-3 py-1 border-l border-[#406866]/60 ml-5 flex flex-col gap-1">
                        @forelse($data['items'] ?? $data['links'] ?? [] as $link)
                            @php
                                if (is_array($link)) {
                                    $href = isset($link['route']) ? route($link['route'], $link['route_params'] ?? []) : '#';
                                    if (! empty($link['fragment'])) {
                                        $href .= '#'.$link['fragment'];
                                    }
                                    $label = $link['label'] ?? '';
                                    $linkTitle = $label;
                                    if (! empty($link['active_patterns']) && is_array($link['active_patterns'])) {
                                        $isActive = false;
                                        foreach ($link['active_patterns'] as $p) {
                                            if (request()->routeIs($p)) {
                                                $isActive = true;
                                                break;
                                            }
                                        }
                                    } elseif (! empty($link['active_pattern'])) {
                                        $isActive = request()->routeIs($link['active_pattern']);
                                    } elseif (isset($link['route'])) {
                                        $isActive = request()->routeIs($link['route']);
                                    } else {
                                        $isActive = false;
                                    }
                                } else {
                                    $href = '#';
                                    $label = $link;
                                    $linkTitle = $link;
                                    $isActive = false;
                                }
                            @endphp
                            <a
                                href="{{ $href }}"
                                @if($isActive) aria-current="page" @endif
                                class="block py-1.5 text-sm hover:translate-x-1 transition-transform duration-200 truncate {{ $isActive ? 'text-white font-semibold' : 'text-[#8db1af] hover:text-white' }}"
                                title="{{ $linkTitle }}"
                            >
                                {{ $label }}
                            </a>
                        @empty
                            <span class="block py-1.5 text-[13px] text-[#6a8b89] italic pl-2">Pending items...</span>
                        @endforelse
                    </div>
                </div>
            </div>
            @endforeach
            
            <!-- Direct Logout Link per reference -->
            <div class="pt-4 mt-4 border-t border-[#406866]/40">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-all group">
                        <div class="flex items-center gap-3 w-4/5">
                            <svg class="w-5 h-5 flex-shrink-0 text-[#8db1af] group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span class="font-medium truncate text-left">Logout</span>
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- User Profile Snippet -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/30 flex-shrink-0">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-800 transition-colors">
            <div class="w-10 h-10 rounded-full bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold overflow-hidden flex-shrink-0">
                @if(Auth::check() && Auth::user()->name)
                    {{ substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="flex flex-col overflow-hidden min-w-0">
                <span class="text-sm font-medium text-white truncate">{{ Auth::user()->name ?? 'Administrator' }}</span>
                <span class="text-xs text-slate-400 truncate">{{ Auth::user()->email ?? 'admin@propertyloansystem' }}</span>
            </div>
        </a>
    </div>
</aside>
