@php
    $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '');
    $navActive = function ($patterns): bool {
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        foreach ($patterns as $p) {
            if ($p && request()->routeIs($p)) {
                return true;
            }
        }

        return false;
    };

    $sectionAnyActive = function (array $items) use ($navActive): bool {
        foreach ($items as $it) {
            if ($navActive($it['active'])) {
                return true;
            }
        }

        return false;
    };

    $sections = [
        [
            'heading' => '',
            'icon' => 'fa-gauge-high',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'sublabel' => 'Alerts · risks · KPIs',
                    'route' => 'property.dashboard',
                    'active' => ['property.dashboard'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Properties',
            'icon' => 'fa-building',
            'kicker' => 'Clean · structural — no financials',
            'items' => [
                [
                    'label' => 'Manage properties',
                    'sublabel' => 'Add · edit · link landlords',
                    'route' => 'property.properties.list',
                    'active' => [
                        'property.properties.list',
                        'property.properties.store',
                        'property.properties.landlords.attach',
                        'property.properties.landlords.detach',
                        'property.properties.landlords.ownership',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Manage landlords',
                    'sublabel' => 'Owners linked to properties',
                    'route' => 'property.landlords.index',
                    'active' => ['property.landlords.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Manage units',
                    'sublabel' => 'Add · assign · status',
                    'route' => 'property.properties.units',
                    'active' => ['property.properties.units', 'property.units.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'View occupancy',
                    'sublabel' => 'Vacant vs occupied',
                    'route' => 'property.properties.occupancy',
                    'active' => ['property.properties.occupancy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Unit performance',
                    'sublabel' => 'Rent vs lease',
                    'route' => 'property.properties.performance',
                    'active' => ['property.properties.performance'],
                    'badge' => null,
                ],
                [
                    'label' => 'Manage amenities',
                    'sublabel' => 'Property features',
                    'route' => 'property.properties.amenities',
                    'active' => [
                        'property.properties.amenities',
                        'property.properties.amenities.store',
                        'property.properties.amenities.attach',
                        'property.properties.amenities.detach',
                        'property.properties.amenities.destroy',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Listings',
            'icon' => 'fa-sign-hanging',
            'kicker' => 'Lean',
            'items' => [
                [
                    'label' => 'Create listing',
                    'sublabel' => 'Start a new listing',
                    'route' => 'property.listings.create',
                    'active' => ['property.listings.create'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vacant listings',
                    'sublabel' => 'Units available now',
                    'route' => 'property.listings.vacant',
                    'active' => [
                        'property.listings.vacant',
                        'property.listings.vacant.public.edit',
                        'property.listings.vacant.public.update',
                        'property.listings.vacant.public.photos.store',
                        'property.listings.vacant.public.photos.destroy',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Published on website',
                    'sublabel' => 'What the public sees',
                    'route' => 'property.listings.ads',
                    'active' => ['property.listings.ads'],
                    'badge' => null,
                ],
                [
                    'label' => 'Enquiries (leads)',
                    'sublabel' => 'Calls · SMS · walk-ins',
                    'route' => 'property.listings.leads',
                    'active' => ['property.listings.leads', 'property.listings.leads.store', 'property.listings.leads.update'],
                    'badge' => null,
                ],
                [
                    'label' => 'Rental applications',
                    'sublabel' => 'Applicants pipeline',
                    'route' => 'property.listings.applications',
                    'active' => [
                        'property.listings.applications',
                        'property.listings.applications.store',
                        'property.listings.applications.update',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Tenants',
            'icon' => 'fa-users',
            'kicker' => 'People-focused · leases live here',
            'items' => [
                [
                    'label' => 'Manage tenants',
                    'sublabel' => 'Add · edit · assign',
                    'route' => 'property.tenants.directory',
                    'active' => ['property.tenants.directory', 'property.tenants.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Tenant profiles',
                    'sublabel' => 'Details & history',
                    'route' => 'property.tenants.profiles',
                    'active' => ['property.tenants.profiles'],
                    'badge' => null,
                ],
                [
                    'label' => 'Import tenants (CSV)',
                    'sublabel' => 'Bulk upload',
                    'route' => 'property.tenants.import',
                    'active' => ['property.tenants.import', 'property.tenants.import.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Manage leases',
                    'sublabel' => 'Create · renew · update',
                    'route' => 'property.tenants.leases',
                    'active' => ['property.tenants.leases', 'property.leases.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Lease expiries',
                    'sublabel' => 'Next 90 days',
                    'route' => 'property.tenants.expiry',
                    'active' => ['property.tenants.expiry'],
                    'badge' => null,
                ],
                [
                    'label' => 'Move-ins & move-outs',
                    'sublabel' => 'Track unit movements',
                    'route' => 'property.tenants.movements',
                    'active' => ['property.tenants.movements', 'property.tenants.movements.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Tenant notices',
                    'sublabel' => 'Vacate · eviction',
                    'route' => 'property.tenants.notices',
                    'active' => ['property.tenants.notices', 'property.tenants.notices.store'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Revenue',
            'icon' => 'fa-sack-dollar',
            'kicker' => 'Most used — keep high',
            'items' => [
                [
                    'label' => 'View rent roll',
                    'sublabel' => 'Who owes what',
                    'route' => 'property.revenue.rent_roll',
                    'active' => ['property.revenue.rent_roll'],
                    'badge' => 'Top',
                ],
                [
                    'label' => 'View arrears',
                    'sublabel' => 'Aging 7 / 14 / 30+',
                    'route' => 'property.revenue.arrears',
                    'active' => ['property.revenue.arrears'],
                    'badge' => 'Top',
                ],
                [
                    'label' => 'Invoices & billing',
                    'sublabel' => 'Create & manage invoices',
                    'route' => 'property.revenue.invoices',
                    'active' => ['property.revenue.invoices'],
                    'badge' => null,
                ],
                [
                    'label' => 'Payments & reconciliation',
                    'sublabel' => 'M-Pesa · logs · matching',
                    'route' => 'property.revenue.payments',
                    'active' => ['property.revenue.payments'],
                    'badge' => null,
                ],
                [
                    'label' => 'Utilities & water billing',
                    'sublabel' => 'Meters · invoices · penalties',
                    'route' => 'property.revenue.utilities',
                    'active' => [
                        'property.revenue.utilities',
                        'property.revenue.utilities.store',
                        'property.revenue.utilities.destroy',
                        'property.revenue.utilities.water_readings.store',
                        'property.revenue.utilities.water_invoices.generate',
                        'property.revenue.utilities.water_penalties.apply',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Penalty rules',
                    'sublabel' => 'Late fees automation',
                    'route' => 'property.revenue.penalties',
                    'active' => ['property.revenue.penalties', 'property.revenue.penalties.store', 'property.revenue.penalties.destroy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Receipts (eTIMS)',
                    'sublabel' => 'View generated receipts',
                    'route' => 'property.revenue.receipts',
                    'active' => ['property.revenue.receipts'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Maintenance',
            'icon' => 'fa-screwdriver-wrench',
            'kicker' => 'Action-heavy',
            'items' => [
                [
                    'label' => 'Maintenance requests',
                    'sublabel' => 'Tickets & issues',
                    'route' => 'property.maintenance.requests',
                    'active' => ['property.maintenance.requests', 'property.maintenance.requests.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance jobs',
                    'sublabel' => 'Work in progress',
                    'route' => 'property.maintenance.jobs',
                    'active' => ['property.maintenance.jobs', 'property.maintenance.jobs.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance history',
                    'sublabel' => 'Closed jobs',
                    'route' => 'property.maintenance.history',
                    'active' => ['property.maintenance.history'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance costs',
                    'sublabel' => 'Track spend',
                    'route' => 'property.maintenance.costs',
                    'active' => ['property.maintenance.costs'],
                    'badge' => null,
                ],
                [
                    'label' => 'Issue frequency report',
                    'sublabel' => 'Common problems',
                    'route' => 'property.maintenance.frequency',
                    'active' => ['property.maintenance.frequency'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Vendors',
            'icon' => 'fa-truck-field',
            'kicker' => 'Separate module',
            'items' => [
                [
                    'label' => 'Manage vendors',
                    'sublabel' => 'Directory',
                    'route' => 'property.vendors.directory',
                    'active' => ['property.vendors.directory', 'property.vendors.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'RFQs & bidding',
                    'sublabel' => 'Request quotes',
                    'route' => 'property.vendors.bidding',
                    'active' => [
                        'property.vendors.bidding',
                        'property.vendors.bidding.create',
                        'property.vendors.bidding.store',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Vendor quotes',
                    'sublabel' => 'Review & award',
                    'route' => 'property.vendors.quotes',
                    'active' => ['property.vendors.quotes'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vendor performance',
                    'sublabel' => 'Ratings & KPIs',
                    'route' => 'property.vendors.performance',
                    'active' => ['property.vendors.performance'],
                    'badge' => null,
                ],
                [
                    'label' => 'Work records',
                    'sublabel' => 'Completed work',
                    'route' => 'property.vendors.work_records',
                    'active' => ['property.vendors.work_records'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Analytics',
            'icon' => 'fa-chart-line',
            'kicker' => 'Not daily ops — avoid clutter',
            'items' => [
                [
                    'label' => 'Collection rate',
                    'sublabel' => 'Rent collected vs billed',
                    'route' => 'property.performance.collection_rate',
                    'active' => ['property.performance.collection_rate'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vacancy trends',
                    'sublabel' => 'Vacant vs occupied over time',
                    'route' => 'property.performance.vacancy',
                    'active' => ['property.performance.vacancy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Arrears trends',
                    'sublabel' => 'Debt over time',
                    'route' => 'property.performance.arrears_trends',
                    'active' => ['property.performance.arrears_trends'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance cost trends',
                    'sublabel' => 'Spend over time',
                    'route' => 'property.performance.maintenance_trends',
                    'active' => ['property.performance.maintenance_trends'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Reports',
            'icon' => 'fa-file-lines',
            'kicker' => 'Tenant, landlord, expense, maintenance, and financial reporting',
            'items' => [
                [
                    'label' => 'Tenant reports',
                    'sublabel' => 'Profiles, lease activity, and movements',
                    'route' => 'property.reports.tenant',
                    'active' => [
                        'property.reports.tenant',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Landlord reports',
                    'sublabel' => 'Ownership, collections, and payouts context',
                    'route' => 'property.reports.landlord',
                    'active' => [
                        'property.reports.landlord',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Expense reports',
                    'sublabel' => 'Income vs expenses and spend tracking',
                    'route' => 'property.reports.expense',
                    'active' => [
                        'property.reports.expense',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance reports',
                    'sublabel' => 'History, costs, and issue frequency',
                    'route' => 'property.reports.maintenance',
                    'active' => [
                        'property.reports.maintenance',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Financial reports',
                    'sublabel' => 'Cash flow, commissions, balances, statements',
                    'route' => 'property.reports.financial',
                    'active' => [
                        'property.reports.financial',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Financials',
            'icon' => 'fa-coins',
            'kicker' => 'Owner-facing money views',
            'items' => [
                [
                    'label' => 'Owner overview',
                    'sublabel' => 'Summary',
                    'route' => 'property.financials.index',
                    'active' => ['property.financials.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Income & expenses',
                    'sublabel' => 'Profit & loss view',
                    'route' => 'property.financials.income_expenses',
                    'active' => ['property.financials.income_expenses'],
                    'badge' => null,
                ],
                [
                    'label' => 'Cash flow',
                    'sublabel' => 'Inflows & outflows',
                    'route' => 'property.financials.cash_flow',
                    'active' => ['property.financials.cash_flow'],
                    'badge' => null,
                ],
                [
                    'label' => 'Owner balances',
                    'sublabel' => 'Who is owed what',
                    'route' => 'property.financials.owner_balances',
                    'active' => ['property.financials.owner_balances'],
                    'badge' => null,
                ],
                [
                    'label' => 'Commission report',
                    'sublabel' => 'Fees & splits',
                    'route' => 'property.financials.commission',
                    'active' => ['property.financials.commission'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Accounting',
            'icon' => 'fa-book',
            'kicker' => 'Books of accounts',
            'items' => [
                [
                    'label' => 'Accounting dashboard',
                    'sublabel' => 'Books overview',
                    'route' => 'property.accounting.index',
                    'active' => ['property.accounting.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Journal entries',
                    'sublabel' => 'Post & reverse',
                    'route' => 'property.accounting.entries',
                    'active' => ['property.accounting.entries', 'property.accounting.entries.store', 'property.accounting.entries.reverse', 'property.accounting.entries.export'],
                    'badge' => null,
                ],
                [
                    'label' => 'Run payroll',
                    'sublabel' => 'Payslips & settings',
                    'route' => 'property.accounting.payroll',
                    'active' => [
                        'property.accounting.payroll',
                        'property.accounting.payroll.store',
                        'property.accounting.payroll.employee.store',
                        'property.accounting.payroll.payslips',
                        'property.accounting.payroll.payslips.show',
                        'property.accounting.payroll.settings',
                        'property.accounting.payroll.settings.save',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Accounting audit trail',
                    'sublabel' => 'Logs & exports',
                    'route' => 'property.accounting.audit_trail',
                    'active' => ['property.accounting.audit_trail', 'property.accounting.audit_trail.export'],
                    'badge' => null,
                ],
                [
                    'label' => 'Trial balance',
                    'sublabel' => null,
                    'route' => 'property.accounting.reports.trial_balance',
                    'active' => ['property.accounting.reports.trial_balance'],
                    'badge' => null,
                ],
                [
                    'label' => 'Income statement',
                    'sublabel' => null,
                    'route' => 'property.accounting.reports.income_statement',
                    'active' => ['property.accounting.reports.income_statement'],
                    'badge' => null,
                ],
                [
                    'label' => 'Cash book',
                    'sublabel' => null,
                    'route' => 'property.accounting.reports.cash_book',
                    'active' => ['property.accounting.reports.cash_book'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Communications',
            'icon' => 'fa-comments',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'Send SMS / email',
                    'sublabel' => 'Single message',
                    'route' => 'property.communications.messages',
                    'active' => ['property.communications.messages', 'property.communications.messages.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Send bulk SMS',
                    'sublabel' => 'Campaigns',
                    'route' => 'property.communications.bulk',
                    'active' => ['property.communications.bulk', 'property.communications.bulk.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Message templates',
                    'sublabel' => 'Reusable text',
                    'route' => 'property.communications.templates',
                    'active' => [
                        'property.communications.templates',
                        'property.communications.templates.store',
                        'property.communications.templates.destroy',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'AI advisor',
            'icon' => 'fa-robot',
            'kicker' => 'Also in floating button',
            'items' => [
                [
                    'label' => 'Ask AI advisor',
                    'sublabel' => 'Help & insights',
                    'route' => 'property.advisor',
                    'active' => ['property.advisor', 'property.advisor.ask'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Settings',
            'icon' => 'fa-gear',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'Settings',
                    'sublabel' => 'Configuration hub',
                    'route' => 'property.settings.index',
                    'active' => ['property.settings.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Users & roles',
                    'sublabel' => 'Manage access',
                    'route' => 'property.settings.roles',
                    'active' => ['property.settings.roles'],
                    'badge' => null,
                ],
                [
                    'label' => 'Commission settings',
                    'sublabel' => 'Set commission rules',
                    'route' => 'property.settings.commission',
                    'active' => ['property.settings.commission', 'property.settings.commission.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'M-Pesa payment settings',
                    'sublabel' => 'STK & Daraja config',
                    'route' => 'property.settings.payments',
                    'active' => ['property.settings.payments', 'property.settings.payments.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Automation rules',
                    'sublabel' => 'Business rules',
                    'route' => 'property.settings.rules',
                    'active' => ['property.settings.rules', 'property.settings.rules.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'System setup',
                    'sublabel' => 'Forms · workflows · templates',
                    'route' => 'property.settings.system_setup',
                    'active' => [
                        'property.settings.system_setup',
                        'property.settings.system_setup.forms',
                        'property.settings.system_setup.forms.store',
                        'property.settings.system_setup.workflows',
                        'property.settings.system_setup.workflows.store',
                        'property.settings.system_setup.templates',
                        'property.settings.system_setup.templates.store',
                    ],
                    'badge' => null,
                ],
            ],
        ],
    ];
@endphp

<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-[2px] lg:hidden"
    @click="sidebarOpen = false"
    x-cloak>
</div>

<aside
    class="property-sidebar fixed inset-y-0 left-0 z-50 w-[300px] sm:w-[312px] h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-transform duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-hidden flex-shrink-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none'"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Menu</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#264040] bg-[#243d3d]/30">
        <a
            href="{{ route('property.dashboard') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.dashboard"
            @if ($navActive(['property.dashboard'])) aria-current="page" @endif
            class="flex items-center gap-3 min-w-0 group"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-8 w-8 object-contain rounded-md bg-white/95 p-0.5" />
                @else
                    <i class="fa-solid fa-building text-xl text-[#c5ebe8]" aria-hidden="true"></i>
                @endif
            </span>
            <span class="flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">{{ $companyName !== '' ? $companyName : 'Agent workspace' }}</span>
                <span class="text-sm font-medium text-[#8db1af] truncate">Property operations</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 min-h-0 overflow-y-auto py-2 px-2 custom-scrollbar">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-2 pb-3">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors"
                >
                    <i class="fa-solid fa-shield-halved text-[#c5ebe8]" aria-hidden="true"></i>
                    <span class="font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        @foreach ($sections as $si => $section)
            @php
                $secActive = $sectionAnyActive($section['items']);
                $itemCount = count($section['items']);
                $sectionPatterns = collect($section['items'])->pluck('active')->flatten()->unique()->values()->implode('|');
            @endphp

            @if ($itemCount === 1)
                @php $item = $section['items'][0]; $active = $navActive($item['active']); @endphp
                <div class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }}">
                    <a
                        href="{{ route($item['route']) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $item['active']) }}"
                        @if ($active) aria-current="page" @endif
                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                        class="group flex items-start gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                    >
                        @if (! empty($section['icon']))
                            <i class="fa-solid {{ $section['icon'] }} text-[#c5ebe8] text-base shrink-0 mt-0.5 w-6 text-center group-aria-[current=page]:text-[#c5ebe8]" aria-hidden="true"></i>
                        @endif
                        <span class="flex flex-col gap-0.5 min-w-0 flex-1">
                            @if (trim((string) ($section['heading'] ?? '')) !== '')
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#c5ebe8]">{{ $section['heading'] }}</span>
                            @endif
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                @endif
                            </span>
                            @if (! empty($item['sublabel']))
                                <span class="text-sm leading-snug text-[#8db1af] group-hover:text-[#d4e4e3] group-aria-[current=page]:text-[#c5ddd9]">{{ $item['sublabel'] }}</span>
                            @endif
                        </span>
                    </a>
                </div>
            @else
                <div
                    class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }} group"
                    data-property-nav-section
                    data-property-nav-aggregate="{{ $sectionPatterns }}"
                    @if ($secActive) data-section-active @endif
                    x-data="{ open: {{ $secActive ? 'true' : 'false' }} }"
                >
                    <button
                        type="button"
                        class="w-full flex items-start gap-2 rounded-xl px-2 py-2.5 text-left text-[#d4e4e3] hover:bg-[#406866]/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50"
                        @click="open = ! open"
                        :aria-expanded="open"
                        aria-controls="nav-section-{{ $si }}"
                    >
                        <span class="flex flex-col items-center justify-center shrink-0 pt-0.5 w-5" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down text-sm text-[#8db1af] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-data-[section-active]:text-[#c5ebe8]">
                                @if (! empty($section['icon']))
                                    <i class="fa-solid {{ $section['icon'] }} text-base text-[#a8c9c7] not-uppercase normal-case group-data-[section-active]:text-[#c5ebe8]" aria-hidden="true"></i>
                                @endif
                                <span>{{ $section['heading'] }}</span>
                            </span>
                            @if (! empty($section['kicker']))
                                <span class="mt-0.5 block text-xs leading-snug text-[#a8c9c7]/95 group-data-[section-active]:text-[#c5ebe8]/95">{{ $section['kicker'] }}</span>
                            @endif
                        </span>
                    </button>

                    <div
                        id="nav-section-{{ $si }}"
                        @unless ($secActive) x-cloak @endunless
                        x-show="open"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="space-y-0.5 pb-1 pl-1"
                    >
                        @foreach ($section['items'] as $item)
                            @php $active = $navActive($item['active']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                data-turbo-frame="property-main"
                                data-property-nav="{{ implode('|', $item['active']) }}"
                                @if ($active) aria-current="page" @endif
                                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                class="group flex flex-col gap-0.5 rounded-xl border-l-[3px] px-3 py-2.5 ml-6 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                            >
                                <span class="flex items-start justify-between gap-2 w-full">
                                    <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="shrink-0 mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                    @endif
                                </span>
                                @if (! empty($item['sublabel']))
                                    <span class="text-sm leading-snug text-[#8db1af] group-hover:text-[#d4e4e3] group-aria-[current=page]:text-[#c5ddd9]">{{ $item['sublabel'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="pt-4 mt-3 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group">
                    <i class="fa-solid fa-right-from-bracket w-5 shrink-0 text-center text-[#8db1af] group-hover:text-red-400 transition-colors" aria-hidden="true"></i>
                    Log out
                </button>
            </form>
        </div>
    </nav>

    <div class="p-3 border-t border-[#264040] bg-[#243d3d]/40 shrink-0">
        <a
            href="{{ route('profile.edit') }}"
            data-turbo-frame="property-main"
            data-property-nav="profile.edit"
            class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors"
        >
            <div class="w-11 h-11 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-base shrink-0">
                @if (Auth::check() && Auth::user()->name)
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'User' }}</span>
                <span class="text-sm text-[#8db1af] truncate">{{ Auth::user()->email ?? '' }}</span>
            </div>
        </a>

        <a
            href="{{ route('public.home') }}"
            target="_blank"
            rel="noopener"
            class="mt-2 flex items-center gap-3 p-2.5 rounded-xl border border-[#406866]/60 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors"
        >
            <i class="fa-solid fa-globe w-5 text-center text-[#8db1af]" aria-hidden="true"></i>
            <span class="text-sm font-medium">Open public website</span>
            <i class="fa-solid fa-arrow-up-right-from-square ml-auto text-xs text-[#8db1af]" aria-hidden="true"></i>
        </a>
    </div>
</aside>

