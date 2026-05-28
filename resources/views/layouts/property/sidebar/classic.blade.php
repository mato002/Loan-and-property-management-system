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

    $itemAnyActive = null;
    $itemAnyActive = function (array $item) use (&$itemAnyActive, $navActive): bool {
        if (! empty($item['active']) && $navActive($item['active'])) {
            return true;
        }
        foreach (($item['children'] ?? []) as $child) {
            if ($itemAnyActive($child)) {
                return true;
            }
        }

        return false;
    };

    $sectionAnyActive = function (array $items) use ($itemAnyActive): bool {
        foreach ($items as $it) {
            if ($itemAnyActive($it)) {
                return true;
            }
        }

        return false;
    };

    $collectActivePatterns = null;
    $collectActivePatterns = function (array $items) use (&$collectActivePatterns): array {
        $patterns = [];
        foreach ($items as $item) {
            foreach ((array) ($item['active'] ?? []) as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $patterns[] = $p;
                }
            }
            if (! empty($item['children']) && is_array($item['children'])) {
                $patterns = array_merge($patterns, $collectActivePatterns($item['children']));
            }
        }

        return array_values(array_unique($patterns));
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
                    'label' => 'Listings workspace',
                    'sublabel' => 'Create · vacant · published · leads · applications',
                    'route' => 'property.listings.index',
                    'active' => [
                        'property.listings.index',
                        'property.listings.create',
                        'property.listings.start',
                        'property.listings.vacant',
                        'property.listings.vacant.public.edit',
                        'property.listings.vacant.public.update',
                        'property.listings.vacant.public.photos.store',
                        'property.listings.vacant.public.photos.main',
                        'property.listings.vacant.public.photos.destroy',
                        'property.listings.ads',
                        'property.listings.leads',
                        'property.listings.leads.export',
                        'property.listings.leads.store',
                        'property.listings.leads.update',
                        'property.listings.applications',
                        'property.listings.applications.export',
                        'property.listings.applications.store',
                        'property.listings.applications.show',
                        'property.listings.applications.message',
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
                    'active' => ['property.tenants.directory', 'property.tenants.store', 'property.tenants.profiles'],
                    'badge' => null,
                ],
                [
                    'label' => 'Manage leases',
                    'sublabel' => 'Create · renew · update',
                    'route' => 'property.tenants.leases',
                    'active' => ['property.tenants.leases', 'property.tenants.expiry', 'property.leases.store'],
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
            'kicker' => 'Rent · billing · utilities',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'property.revenue.index',
                    'active' => ['property.revenue.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Rent & arrears',
                    'children' => [
                        [
                            'label' => 'Rent roll',
                            'route' => 'property.revenue.rent_roll',
                            'active' => ['property.revenue.rent_roll'],
                            'badge' => 'Top',
                        ],
                        [
                            'label' => 'Arrears',
                            'route' => 'property.revenue.arrears',
                            'active' => [
                                'property.revenue.arrears',
                                'property.revenue.arrears.tenant',
                            ],
                            'badge' => 'Top',
                        ],
                        [
                            'label' => 'Uninvoiced leases',
                            'route' => 'property.revenue.uninvoiced_leases',
                            'active' => ['property.revenue.uninvoiced_leases'],
                            'badge' => null,
                        ],
                    ],
                ],
                [
                    'label' => 'Billing',
                    'children' => [
                        [
                            'label' => 'Invoices & billing',
                            'route' => 'property.revenue.invoices',
                            'active' => ['property.revenue.invoices'],
                            'badge' => null,
                        ],
                        [
                            'label' => 'Payments & reconciliation',
                            'route' => 'property.revenue.payments',
                            'active' => ['property.revenue.payments'],
                            'badge' => null,
                        ],
                        [
                            'label' => 'Receipts / eTIMS',
                            'route' => 'property.revenue.receipts',
                            'active' => ['property.revenue.receipts'],
                            'badge' => null,
                        ],
                    ],
                ],
                [
                    'label' => 'Utilities',
                    'children' => [
                        [
                            'label' => 'Utilities & water billing',
                            'route' => 'property.revenue.utilities',
                            'active' => [
                                'property.revenue.utilities',
                                'property.revenue.utilities.store',
                                'property.revenue.utilities.destroy',
                                'property.revenue.utilities.water_readings.*',
                                'property.revenue.utilities.water_invoices.*',
                                'property.revenue.utilities.water_penalties.*',
                                'property.revenue.utilities.invoices.*',
                            ],
                            'badge' => null,
                        ],
                        [
                            'label' => 'Utility reconciliation',
                            'route' => 'property.revenue.utilities.reconciliation',
                            'active' => [
                                'property.revenue.utilities.reconciliation',
                                'property.revenue.utilities.ledger',
                                'property.tenants.utility.statement',
                                'property.reports.expense.utility_aging',
                            ],
                            'badge' => null,
                        ],
                        [
                            'label' => 'Utility intelligence',
                            'route' => 'property.revenue.utilities.analytics',
                            'active' => ['property.revenue.utilities.analytics'],
                            'badge' => null,
                        ],
                    ],
                ],
                [
                    'label' => 'Controls',
                    'children' => [
                        [
                            'label' => 'Period closing',
                            'route' => 'property.revenue.utilities.periods',
                            'active' => [
                                'property.revenue.utilities.periods',
                                'property.revenue.utilities.periods.*',
                            ],
                            'badge' => null,
                        ],
                        [
                            'label' => 'Penalty rules',
                            'route' => 'property.revenue.penalties',
                            'active' => [
                                'property.revenue.penalties',
                                'property.revenue.penalties.store',
                                'property.revenue.penalties.destroy',
                            ],
                            'badge' => null,
                        ],
                    ],
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
                    'label' => 'Income & expenses',
                    'sublabel' => 'Profit & loss view',
                    'route' => 'property.financials.income_expenses',
                    'active' => ['property.financials.index', 'property.financials.income_expenses'],
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
            'kicker' => 'Trust accounting and GL',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'sublabel' => 'Accounting overview',
                    'route' => 'property.accounting.index',
                    'active' => ['property.accounting.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Receivables',
                    'sublabel' => null,
                    'active' => [
                        'property.revenue.arrears',
                        'property.reports.tenant.statements',
                    ],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Accounts Receivable',
                            'route' => 'property.accounting.receivables.accounts',
                            'active' => ['property.accounting.receivables.accounts'],
                        ],
                        [
                            'label' => 'Tenant Statements',
                            'route' => 'property.accounting.receivables.tenant_statements',
                            'active' => ['property.accounting.receivables.tenant_statements'],
                        ],
                    ],
                ],
                [
                    'label' => 'Payables',
                    'sublabel' => null,
                    'route' => 'property.accounting.entries',
                    'active' => [
                        'property.financials.owner_balances',
                        'property.reports.landlord.balance_summary',
                        'property.vendors.work_records',
                    ],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Landlord Payables',
                            'route' => 'property.accounting.payables.landlord_payables',
                            'active' => ['property.accounting.payables.landlord_payables'],
                        ],
                        [
                            'label' => 'Landlord Payouts',
                            'route' => 'property.accounting.payables.landlord_payouts',
                            'active' => ['property.accounting.payables.landlord_payouts'],
                        ],
                        [
                            'label' => 'Accounts Payable',
                            'route' => 'property.accounting.payables.accounts_payable',
                            'active' => ['property.accounting.payables.accounts_payable'],
                        ],
                    ],
                ],
                [
                    'label' => 'Cash & Bank',
                    'sublabel' => null,
                    'active' => [
                        'property.accounting.reports.cash_book',
                        'property.accounting.index',
                    ],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Cash Book',
                            'route' => 'property.accounting.reports.cash_book',
                            'active' => ['property.accounting.reports.cash_book', 'property.accounting.reports.cash_book.export'],
                        ],
                        [
                            'label' => 'Bank Reconciliation',
                            'route' => 'property.accounting.cash_bank.reconciliation',
                            'active' => ['property.accounting.cash_bank.reconciliation'],
                        ],
                    ],
                ],
                [
                    'label' => 'Reports',
                    'sublabel' => null,
                    'active' => [
                        'property.accounting.reports.trial_balance',
                        'property.accounting.reports.income_statement',
                        'property.reports.financial.balance_sheet_standard',
                        'property.reports.financial.balance_sheet_itemised',
                        'property.reports.tenant.aging_balance',
                        'property.reports.landlord.balance_summary',
                        'property.reports.tenant.deposits',
                    ],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Trial Balance',
                            'route' => 'property.accounting.reports.trial_balance',
                            'active' => ['property.accounting.reports.trial_balance', 'property.accounting.reports.trial_balance.export'],
                        ],
                        [
                            'label' => 'Income Statement',
                            'route' => 'property.accounting.reports.income_statement',
                            'active' => ['property.accounting.reports.income_statement', 'property.accounting.reports.income_statement.export'],
                        ],
                        [
                            'label' => 'Balance Sheet',
                            'route' => 'property.accounting.reports.balance_sheet',
                            'active' => ['property.accounting.reports.balance_sheet'],
                        ],
                        [
                            'label' => 'Aged Receivables',
                            'route' => 'property.accounting.reports.aged_receivables',
                            'active' => ['property.accounting.reports.aged_receivables'],
                        ],
                        [
                            'label' => 'Aged Payables',
                            'route' => 'property.accounting.reports.aged_payables',
                            'active' => ['property.accounting.reports.aged_payables'],
                        ],
                        [
                            'label' => 'Deposit Liability Report',
                            'route' => 'property.accounting.reports.deposit_liability',
                            'active' => ['property.accounting.reports.deposit_liability'],
                        ],
                    ],
                ],
                [
                    'label' => 'General Ledger',
                    'sublabel' => null,
                    'active' => [],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Chart of Accounts',
                            'route' => 'property.accounting.gl.chart_accounts',
                            'active' => [
                                'property.accounting.gl.chart_accounts',
                            ],
                        ],
                        [
                            'label' => 'Journal Entries',
                            'route' => 'property.accounting.entries',
                            'active' => [
                                'property.accounting.entries',
                                'property.accounting.entries.store',
                                'property.accounting.entries.reverse',
                                'property.accounting.entries.export',
                                'property.accounting.entries.bulk',
                            ],
                        ],
                        [
                            'label' => 'Journal Batches',
                            'route' => 'property.accounting.gl.journal_batches',
                            'active' => [
                                'property.accounting.gl.journal_batches',
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'Payroll',
                    'sublabel' => null,
                    'active' => [],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Run Payroll',
                            'route' => 'property.accounting.payroll',
                            'active' => [
                                'property.accounting.payroll',
                                'property.accounting.payroll.store',
                                'property.accounting.payroll.employee.store',
                                'property.accounting.payroll.settings',
                                'property.accounting.payroll.settings.save',
                            ],
                        ],
                        [
                            'label' => 'Payroll Ledger',
                            'route' => 'property.accounting.payroll.payslips',
                            'active' => [
                                'property.accounting.payroll.payslips',
                                'property.accounting.payroll.payslips.show',
                                'property.accounting.payroll.payslips.export',
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'Controls',
                    'sublabel' => null,
                    'active' => [],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Accounting Audit Trail',
                            'route' => 'property.accounting.audit_trail',
                            'active' => ['property.accounting.audit_trail', 'property.accounting.audit_trail.export'],
                        ],
                        [
                            'label' => 'Reversals',
                            'route' => 'property.accounting.controls.reversals',
                            'active' => ['property.accounting.controls.reversals'],
                        ],
                        [
                            'label' => 'Accounting Periods',
                            'route' => 'property.accounting.controls.periods',
                            'active' => ['property.accounting.controls.periods'],
                        ],
                    ],
                ],
                [
                    'label' => 'Settings',
                    'sublabel' => null,
                    'active' => ['property.accounting.index', 'property.settings.system_setup'],
                    'badge' => null,
                    'children' => [
                        [
                            'label' => 'Chart of Accounts',
                            'route' => 'property.accounting.gl.chart_accounts',
                            'active' => [
                                'property.accounting.gl.chart_accounts',
                            ],
                        ],
                        [
                            'label' => 'Account Mapping',
                            'route' => 'property.accounting.settings.account_mapping',
                            'active' => ['property.accounting.settings.account_mapping'],
                        ],
                        [
                            'label' => 'Financial Settings',
                            'route' => 'property.accounting.settings.financial',
                            'active' => ['property.accounting.settings.financial'],
                            'requires_superadmin' => true,
                        ],
                    ],
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
                [
                    'label' => 'Conversations',
                    'sublabel' => 'Inbound and replies',
                    'route' => 'property.communications.conversations',
                    'active' => [
                        'property.communications.conversations',
                        'property.communications.conversations.data',
                        'property.communications.conversations.show',
                        'property.communications.conversations.reply',
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
                    'label' => 'Users & roles',
                    'sublabel' => 'Manage access',
                    'route' => 'property.settings.roles',
                    'active' => [
                        'property.settings.roles',
                        'property.settings.team_users.create',
                        'property.settings.team_users.store',
                    ],
                    'badge' => null,
                    'requires_pm_permission' => 'team.users.manage',
                ],
                [
                    'label' => 'Commission settings',
                    'sublabel' => 'Set commission rules',
                    'route' => 'property.settings.commission',
                    'active' => ['property.settings.index', 'property.settings.commission', 'property.settings.commission.store'],
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
                    'label' => 'System setup',
                    'sublabel' => 'Forms · workflows · templates',
                    'route' => 'property.settings.system_setup',
                    'active' => [
                        'property.settings.rules',
                        'property.settings.rules.store',
                        'property.settings.deposits',
                        'property.settings.deposits.store',
                        'property.settings.expenses',
                        'property.settings.expenses.store',
                        'property.settings.permissions',
                        'property.settings.system_setup',
                        'property.settings.system_setup.forms',
                        'property.settings.system_setup.forms.store',
                        'property.settings.system_setup.workflows',
                        'property.settings.system_setup.workflows.store',
                        'property.settings.system_setup.templates',
                        'property.settings.system_setup.templates.store',
                        'property.settings.system_setup.access',
                        'property.settings.system_setup.access.roles.store',
                        'property.settings.system_setup.access.roles.clone',
                        'property.settings.system_setup.access.permissions.store',
                        'property.settings.system_setup.access.permissions.update',
                        'property.settings.system_setup.access.permissions.destroy',
                        'property.settings.system_setup.access.roles.permissions.store',
                        'property.settings.system_setup.access.matrix.store',
                        'property.settings.system_setup.access.users.roles.store',
                        'property.settings.system_setup.access.users.permissions.store',
                    ],
                    'badge' => null,
                    'requires_pm_permission' => 'settings.manage',
                ],
            ],
        ],
    ];

    $propertyAgentIsSuperAdmin = auth()->check() && (auth()->user()->is_super_admin ?? false);
    $filterItemsByAccess = null;
    $filterItemsByAccess = static function (array $items) use (&$filterItemsByAccess, $propertyAgentIsSuperAdmin): array {
        $out = [];
        foreach ($items as $item) {
            if (! $propertyAgentIsSuperAdmin && ! empty($item['requires_superadmin'])) {
                continue;
            }
            if (! empty($item['requires_pm_permission'] ?? null)) {
                $u = auth()->user();
                if (! $u || ! $u->hasPmPermission($item['requires_pm_permission'])) {
                    continue;
                }
            }
            $children = $item['children'] ?? null;
            if (is_array($children) && $children !== []) {
                $children = $filterItemsByAccess($children);
                if ($children === []) {
                    continue;
                }
                $item['children'] = $children;
            }
            $out[] = $item;
        }

        return $out;
    };
    if (! $propertyAgentIsSuperAdmin) {
        $sections = array_values(array_map(static function (array $section): array {
            $section['items'] = array_values(array_filter(
                $section['items'],
                static function (array $item): bool {
                    if (! empty($item['requires_superadmin'])) {
                        return false;
                    }
                    if (! empty($item['requires_pm_permission'] ?? null)) {
                        $u = auth()->user();
                        if (! $u || ! $u->hasPmPermission($item['requires_pm_permission'])) {
                            return false;
                        }
                    }

                    return true;
                }
            ));

            return $section;
        }, $sections));
        $sections = array_values(array_filter($sections, static fn (array $section): bool => count($section['items']) > 0));
    }
    $sections = array_values(array_map(function (array $section) use ($filterItemsByAccess): array {
        $section['items'] = $filterItemsByAccess($section['items']);

        return $section;
    }, $sections));
    $sections = array_values(array_filter($sections, static fn (array $section): bool => count($section['items']) > 0));

    // Keep related modules adjacent for faster navigation.
    $preferredSectionOrder = [
        '',
        'Properties',
        'Listings',
        'Tenants',
        'Revenue',
        'Accounting',
        'Financials',
        'Maintenance',
        'Vendors',
        'Communications',
        'Analytics',
        'Reports',
        'AI advisor',
        'Settings',
    ];
    $sectionOrderMap = array_flip($preferredSectionOrder);
    usort($sections, static function (array $a, array $b) use ($sectionOrderMap): int {
        $aOrder = $sectionOrderMap[(string) ($a['heading'] ?? '')] ?? PHP_INT_MAX;
        $bOrder = $sectionOrderMap[(string) ($b['heading'] ?? '')] ?? PHP_INT_MAX;
        if ($aOrder === $bOrder) {
            return 0;
        }

        return $aOrder <=> $bOrder;
    });
@endphp

@include('layouts.partials.property_sidebar_collapsed_styles')

<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[5500] bg-slate-950/70 backdrop-blur-[2px] lg:hidden"
    @click="sidebarOpen = false"
    x-cloak>
</div>

<aside
    class="property-sidebar fixed inset-y-0 left-0 z-[5600] lg:z-50 w-[min(100vw-2.5rem,300px)] sm:w-[312px] h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-[transform,width] duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-x-hidden flex-shrink-0"
    :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none', sidebarDesktopOpen ? 'lg:w-[19rem] lg:min-w-[19rem] lg:max-w-[19rem]' : 'lg:w-[5.5rem] lg:min-w-[5.5rem] lg:max-w-[5.5rem]']"
    :style="window.matchMedia('(min-width: 1024px)').matches
        ? (sidebarDesktopOpen
            ? 'width: 19rem; min-width: 19rem; max-width: 19rem;'
            : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;')
        : ''"
    :data-collapsed="sidebarDesktopOpen ? '0' : '1'"
    data-property-nav-mode="classic"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Menu</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#264040] bg-[#243d3d]/30">
        <div class="property-sidebar-collapse-toggle-wrap mb-2 hidden lg:flex justify-end">
            <button
                type="button"
                @click="toggleDesktopSidebar()"
                class="inline-flex items-center justify-center rounded-lg p-2 text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors"
                :title="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                :aria-label="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
            >
                <i class="fa-solid" :class="sidebarDesktopOpen ? 'fa-angles-left' : 'fa-angles-right'" aria-hidden="true"></i>
            </button>
        </div>
        <a
            href="{{ route('property.dashboard') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.dashboard"
            @if ($navActive(['property.dashboard'])) aria-current="page" @endif
            class="property-sidebar-brand flex items-center gap-3 min-w-0 group property-collapse-center"
            :title="sidebarDesktopOpen ? '' : '{{ $companyName !== '' ? $companyName : 'Home' }}'"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="property-sidebar-brand-logo flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-8 w-8 object-contain rounded-md bg-white/95 p-0.5" />
                @else
                    <i class="fa-solid fa-building text-xl text-[#c5ebe8]" aria-hidden="true"></i>
                @endif
            </span>
            <span class="property-collapse-text flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">{{ $companyName }}</span>
            </span>
        </a>
        @if (app()->environment('local'))
            <div class="property-db-safety-expanded property-collapse-text mt-2 rounded-lg border px-2.5 py-2 text-[11px] leading-4 {{ !empty($allowDestructiveDbCommands) ? 'border-rose-300/40 bg-rose-500/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100' }}">
                <span class="font-semibold uppercase tracking-wide">DB safety</span>
                <span class="ml-1">{{ !empty($allowDestructiveDbCommands) ? 'ON: destructive commands allowed' : 'ON: destructive commands blocked' }}</span>
            </div>
            <div
                class="property-db-safety-collapsed mt-2 items-center justify-center rounded-lg border p-2 {{ !empty($allowDestructiveDbCommands) ? 'border-rose-300/40 bg-rose-500/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100' }}"
                title="{{ !empty($allowDestructiveDbCommands) ? 'DB safety: destructive commands allowed' : 'DB safety: destructive commands blocked' }}"
            >
                <i class="fa-solid fa-shield-halved text-sm" aria-hidden="true"></i>
            </div>
        @endif
    </div>

    <nav class="flex-1 min-h-0 overflow-y-auto py-2 px-2 custom-scrollbar">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-2 pb-3">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center property-collapse-compact"
                    :title="sidebarDesktopOpen ? '' : 'Super Admin'"
                >
                    <i class="fa-solid fa-shield-halved text-[#c5ebe8]" aria-hidden="true"></i>
                    <span class="property-collapse-text font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        @foreach ($sections as $si => $section)
            @php
                $secActive = $sectionAnyActive($section['items']);
                $itemCount = count($section['items']);
                $sectionPatterns = implode('|', $collectActivePatterns($section['items']));
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
                        class="property-nav-single-link group flex items-start gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white property-collapse-center property-collapse-compact"
                        :title="sidebarDesktopOpen ? '' : '{{ $item['label'] }}'"
                    >
                        @if (! empty($section['icon']))
                            <i class="fa-solid {{ $section['icon'] }} text-[#c5ebe8] text-base shrink-0 mt-0.5 w-6 text-center group-aria-[current=page]:text-[#c5ebe8]" aria-hidden="true"></i>
                        @endif
                        <span class="property-collapse-text flex flex-col gap-0.5 min-w-0 flex-1">
                            @if (trim((string) ($section['heading'] ?? '')) !== '')
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#c5ebe8]">{{ $section['heading'] }}</span>
                            @endif
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                @endif
                            </span>
                        </span>
                    </a>
                </div>
            @else
                @php
                    $propertySidebarGroupKey = trim((string) ($section['heading'] ?? ''));
                    if ($propertySidebarGroupKey === '') {
                        $propertySidebarGroupKey = 'property-nav-' . $si;
                    }
                @endphp
                <div
                    class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }} group"
                    data-property-nav-section
                    data-property-nav-aggregate="{{ $sectionPatterns }}"
                    @if ($secActive) data-section-active @endif
                    x-data="propertySidebarGroup(@js($propertySidebarGroupKey), @js($secActive))"
                >
                    <button
                        type="button"
                        class="property-section-toggle w-full flex items-start gap-2 rounded-xl px-2 py-2.5 text-left text-[#d4e4e3] hover:bg-[#406866]/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 property-collapse-center property-collapse-compact border-l-[3px] border-transparent group-data-[section-active]:border-emerald-300/60 group-data-[section-active]:bg-[#406866]/80 group-data-[section-active]:text-white"
                        @click="if ($el.closest('aside')?.dataset?.collapsed === '1' && window.matchMedia('(min-width: 1024px)').matches) { window.dispatchEvent(new CustomEvent('property-sidebar-expand')); return; } toggleGroup()"
                        :aria-expanded="open"
                        :title="sidebarDesktopOpen ? '' : '{{ $section['heading'] }}'"
                        aria-controls="nav-section-{{ $si }}"
                    >
                        @if (! empty($section['icon']))
                            <span class="property-section-rail-icon h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#406866]/35 ring-1 ring-[#5a8583]/40">
                                <i class="fa-solid {{ $section['icon'] }} text-base text-[#c5ebe8] group-data-[section-active]:text-[#c5ebe8]" aria-hidden="true"></i>
                            </span>
                        @endif
                        <span class="property-section-expanded-only property-collapse-hide flex flex-col items-center justify-center shrink-0 pt-0.5 w-5" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down text-sm text-[#8db1af] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </span>
                        <span class="property-section-expanded-only property-collapse-text flex-1 min-w-0">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-data-[section-active]:text-[#c5ebe8]">
                                @if (! empty($section['icon']))
                                    <i class="fa-solid {{ $section['icon'] }} text-base text-[#a8c9c7] not-uppercase normal-case group-data-[section-active]:text-[#c5ebe8]" aria-hidden="true"></i>
                                @endif
                                <span>{{ $section['heading'] }}</span>
                            </span>
                        </span>
                    </button>

                    <div
                        id="nav-section-{{ $si }}"
                        x-cloak
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
                            @php
                                $active = ! empty($item['active']) ? $navActive($item['active']) : false;
                                $hasChildren = ! empty($item['children']) && is_array($item['children']);
                                $itemPatterns = implode('|', $collectActivePatterns([$item]));
                            @endphp
                            @if ($hasChildren)
                                @php
                                    $groupKey = 'property-nav-sub-'.$si.'-'.\Illuminate\Support\Str::slug((string) $item['label']);
                                    $childActive = $sectionAnyActive($item['children']);
                                @endphp
                                <div
                                    class="ml-4 property-nav-subgroup"
                                    data-property-nav-section
                                    data-property-nav-aggregate="{{ $itemPatterns }}"
                                    @if ($childActive) data-section-active @endif
                                    x-data="propertySidebarGroup(@js($groupKey), @js($childActive))"
                                >
                                    <button
                                        type="button"
                                        class="w-full group flex items-center gap-2 rounded-lg border-l-[3px] px-2.5 py-2 text-left transition-all duration-150 border-transparent text-[#c5ebe8] hover:bg-[#406866]/50 hover:text-white group-data-[section-active]:border-emerald-300/60"
                                        @click="if ($el.closest('aside')?.dataset?.collapsed === '1' && window.matchMedia('(min-width: 1024px)').matches) { window.dispatchEvent(new CustomEvent('property-sidebar-expand')); return; } toggleGroup()"
                                        :aria-expanded="open"
                                    >
                                        <span class="property-collapse-text text-sm font-semibold leading-snug tracking-tight">{{ $item['label'] }}</span>
                                        <span class="property-collapse-hide ml-auto flex items-center gap-1.5">
                                            @if (! empty($item['badge']))
                                                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                            @endif
                                            <i class="fa-solid fa-chevron-down text-[10px] text-[#8db1af] transition-transform duration-200 group-data-[section-active]:text-[#c5ebe8]" :class="{ 'rotate-180': open }" aria-hidden="true"></i>
                                        </span>
                                    </button>
                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-100"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        class="space-y-0.5 pb-0.5 pl-2 border-l border-[#406866]/50 ml-2"
                                    >
                                        @foreach ($item['children'] as $child)
                                            @php $childActiveState = $navActive($child['active'] ?? []); @endphp
                                            <a
                                                href="{{ route($child['route'], $child['route_params'] ?? []) }}"
                                                data-turbo-frame="property-main"
                                                data-property-nav="{{ implode('|', (array) ($child['active'] ?? [])) }}"
                                                @if ($childActiveState) aria-current="page" @endif
                                                @click="if ($el.closest('aside')?.dataset?.collapsed === '1' && window.matchMedia('(min-width: 1024px)').matches) { window.dispatchEvent(new CustomEvent('property-sidebar-expand')); return; } if (window.innerWidth < 1024) sidebarOpen = false"
                                                class="group flex items-center justify-between gap-2 rounded-lg border-l-[3px] px-2.5 py-2 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                                            >
                                                <span class="property-collapse-text text-sm font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $child['label'] }}</span>
                                                @if (! empty($child['badge']))
                                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $child['badge'] }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a
                                    href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                    data-turbo-frame="property-main"
                                    data-property-nav="{{ implode('|', (array) ($item['active'] ?? [])) }}"
                                    @if ($active) aria-current="page" @endif
                                    @click="if ($el.closest('aside')?.dataset?.collapsed === '1' && window.matchMedia('(min-width: 1024px)').matches) { window.dispatchEvent(new CustomEvent('property-sidebar-expand')); return; } if (window.innerWidth < 1024) sidebarOpen = false"
                                    class="group flex items-center justify-between gap-2 rounded-lg border-l-[3px] px-2.5 py-2 ml-4 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                                >
                                    <span class="property-collapse-text flex items-center justify-between gap-2 w-full min-w-0">
                                        <span class="text-sm font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                        @if (! empty($item['badge']))
                                            <span class="shrink-0 mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                        @endif
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="pt-4 mt-3 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group property-collapse-center property-collapse-compact" :title="sidebarDesktopOpen ? '' : 'Log out'">
                    <i class="fa-solid fa-right-from-bracket w-5 shrink-0 text-center text-[#8db1af] group-hover:text-red-400 transition-colors" aria-hidden="true"></i>
                    <span class="property-collapse-text">Log out</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="p-3 border-t border-[#264040] bg-[#243d3d]/40 shrink-0">
        <a
            href="{{ route('profile.edit') }}"
            data-turbo-frame="property-main"
            data-property-nav="profile.edit"
            class="property-sidebar-footer-link flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors property-collapse-center property-collapse-compact"
            :title="sidebarDesktopOpen ? '' : '{{ Auth::user()->name ?? 'Profile' }}'"
        >
            <div class="property-sidebar-avatar w-11 h-11 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-base shrink-0">
                @if (Auth::check() && Auth::user()->name)
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="property-collapse-text flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'User' }}</span>
            </div>
        </a>

        <a
            href="{{ route('public.home') }}"
            target="_blank"
            rel="noopener"
            class="property-sidebar-footer-link mt-2 flex items-center gap-3 p-2.5 rounded-xl border border-[#406866]/60 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center property-collapse-compact"
            :title="sidebarDesktopOpen ? '' : 'Open public website'"
        >
            <i class="property-collapse-icon-only fa-solid fa-globe w-5 text-center text-[#8db1af]" aria-hidden="true"></i>
            <span class="property-collapse-text text-sm font-medium">Open public website</span>
            <i class="property-collapse-text fa-solid fa-arrow-up-right-from-square ml-auto text-xs text-[#8db1af]" aria-hidden="true"></i>
        </a>
    </div>
</aside>

