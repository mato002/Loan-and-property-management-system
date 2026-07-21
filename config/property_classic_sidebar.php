<?php

return array (
  0 => 
  array (
    'heading' => '',
    'icon' => 'fa-gauge-high',
    'kicker' => NULL,
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Dashboard',
        'sublabel' => 'Alerts  -  risks  -  KPIs',
        'route' => 'property.dashboard',
        'active' => 
        array (
          0 => 'property.dashboard',
        ),
        'badge' => NULL,
      ),
    ),
  ),
  1 => 
  array (
    'heading' => 'Properties',
    'icon' => 'fa-building',
    'kicker' => 'Clean  -  structural  -  no financials',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Manage properties',
        'route' => 'property.properties.list',
        'active' => 
        array (
          0 => 'property.properties.list',
          1 => 'property.properties.store',
          2 => 'property.properties.landlords.attach',
          3 => 'property.properties.landlords.detach',
          4 => 'property.properties.landlords.ownership',
        ),
      ),
      1 => 
      array (
        'label' => 'Manage landlords',
        'route' => 'property.landlords.index',
        'active' => 
        array (
          0 => 'property.landlords.index',
        ),
      ),
      2 => 
      array (
        'label' => 'Manage units',
        'route' => 'property.properties.units',
        'active' => 
        array (
          0 => 'property.properties.units',
          1 => 'property.units.store',
        ),
      ),
      3 => 
      array (
        'label' => 'View occupancy',
        'route' => 'property.properties.occupancy',
        'active' => 
        array (
          0 => 'property.properties.occupancy',
        ),
      ),
      4 => 
      array (
        'label' => 'Unit performance',
        'route' => 'property.properties.performance',
        'active' => 
        array (
          0 => 'property.properties.performance',
        ),
      ),
      5 => 
      array (
        'label' => 'Manage amenities',
        'route' => 'property.properties.amenities',
        'active' => 
        array (
          0 => 'property.properties.amenities',
          1 => 'property.properties.amenities.store',
          2 => 'property.properties.amenities.attach',
          3 => 'property.properties.amenities.detach',
          4 => 'property.properties.amenities.destroy',
        ),
      ),
    ),
  ),
  2 => 
  array (
    'heading' => 'Listings',
    'icon' => 'fa-sign-hanging',
    'kicker' => 'Lean',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Create listing',
        'route' => 'property.listings.create',
        'active' => 
        array (
          0 => 'property.listings.create',
        ),
      ),
      1 => 
      array (
        'label' => 'Vacant listings',
        'route' => 'property.listings.vacant',
        'active' => 
        array (
          0 => 'property.listings.vacant',
          1 => 'property.listings.vacant.public.edit',
          2 => 'property.listings.vacant.public.update',
          3 => 'property.listings.vacant.public.photos.store',
          4 => 'property.listings.vacant.public.photos.destroy',
        ),
      ),
      2 => 
      array (
        'label' => 'Published on website',
        'route' => 'property.listings.ads',
        'active' => 
        array (
          0 => 'property.listings.ads',
        ),
      ),
      3 => 
      array (
        'label' => 'Enquiries (leads)',
        'route' => 'property.listings.leads',
        'active' => 
        array (
          0 => 'property.listings.leads',
          1 => 'property.listings.leads.store',
          2 => 'property.listings.leads.update',
        ),
      ),
      4 => 
      array (
        'label' => 'Rental applications',
        'route' => 'property.listings.applications',
        'active' => 
        array (
          0 => 'property.listings.applications',
          1 => 'property.listings.applications.store',
          2 => 'property.listings.applications.update',
        ),
      ),
    ),
  ),
  3 => 
  array (
    'heading' => 'Tenants',
    'icon' => 'fa-users',
    'kicker' => 'People-focused  -  leases live here',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Manage tenants',
        'route' => 'property.tenants.directory',
        'active' => 
        array (
          0 => 'property.tenants.directory',
          1 => 'property.tenants.store',
        ),
      ),
      1 => 
      array (
        'label' => 'Tenant profiles',
        'route' => 'property.tenants.profiles',
        'active' => 
        array (
          0 => 'property.tenants.profiles',
        ),
      ),
      2 => 
      array (
        'label' => 'Import tenants (CSV)',
        'route' => 'property.tenants.import',
        'active' => 
        array (
          0 => 'property.tenants.import',
          1 => 'property.tenants.import.store',
        ),
      ),
      3 => 
      array (
        'label' => 'Manage leases',
        'route' => 'property.tenants.leases',
        'active' => 
        array (
          0 => 'property.tenants.leases',
          1 => 'property.leases.store',
        ),
      ),
      4 => 
      array (
        'label' => 'Lease expiries',
        'route' => 'property.tenants.expiry',
        'active' => 
        array (
          0 => 'property.tenants.expiry',
        ),
      ),
      5 => 
      array (
        'label' => 'Move-ins & move-outs',
        'route' => 'property.tenants.movements',
        'active' => 
        array (
          0 => 'property.tenants.movements',
          1 => 'property.tenants.movements.store',
        ),
      ),
      6 => 
      array (
        'label' => 'Tenant notices',
        'route' => 'property.tenants.notices',
        'active' => 
        array (
          0 => 'property.tenants.notices',
          1 => 'property.tenants.notices.store',
        ),
      ),
    ),
  ),
  4 => 
  array (
    'heading' => 'Revenue',
    'icon' => 'fa-sack-dollar',
    'kicker' => 'Most used  -  keep high',
    'items' => 
    array (
      0 => 
      array (
        'type' => 'group',
        'label' => 'Rent',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'View rent roll',
            'route' => 'property.revenue.rent_roll',
            'active' => 
            array (
              0 => 'property.revenue.rent_roll',
            ),
            'badge' => 'Top',
          ),
          1 => 
          array (
            'label' => 'View arrears',
            'route' => 'property.revenue.arrears',
            'active' => 
            array (
              0 => 'property.revenue.arrears',
              1 => 'property.revenue.arrears.*',
            ),
            'badge' => 'Top',
          ),
          2 => 
          array (
            'label' => 'Uninvoiced leases',
            'route' => 'property.revenue.uninvoiced_leases',
            'active' => 
            array (
              0 => 'property.revenue.uninvoiced_leases',
              1 => 'property.revenue.uninvoiced_leases.*',
            ),
          ),
        ),
      ),
      1 => 
      array (
        'type' => 'group',
        'label' => 'Billing',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Invoices & billing',
            'route' => 'property.revenue.invoices',
            'active' => 
            array (
              0 => 'property.revenue.invoices',
              1 => 'property.revenue.invoices.*',
              2 => 'property.invoices.*',
            ),
          ),
        ),
      ),
      2 => 
      array (
        'type' => 'group',
        'label' => 'Cash',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Payments & reconciliation',
            'route' => 'property.revenue.payments',
            'active' => 
            array (
              0 => 'property.revenue.payments',
              1 => 'property.payments.*',
              2 => 'property.tenants.credit.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Receipts / eTIMS',
            'route' => 'property.revenue.receipts',
            'active' => 
            array (
              0 => 'property.revenue.receipts',
            ),
          ),
          2 => 
          array (
            'label' => 'Tenant credits',
            'route' => 'property.revenue.tenant_credits',
            'active' => 
            array (
              0 => 'property.revenue.tenant_credits',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'type' => 'group',
        'label' => 'Utilities',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Utilities & water billing',
            'route' => 'property.revenue.utilities',
            'active' => 
            array (
              0 => 'property.revenue.utilities',
              1 => 'property.revenue.utilities.store',
              2 => 'property.revenue.utilities.destroy',
              3 => 'property.revenue.utilities.water_readings.store',
              4 => 'property.revenue.utilities.water_invoices.generate',
              5 => 'property.revenue.utilities.water_penalties.apply',
            ),
          ),
          1 => 
          array (
            'label' => 'Utility reconciliation',
            'route' => 'property.revenue.utilities.reconciliation',
            'active' => 
            array (
              0 => 'property.revenue.utilities.reconciliation',
              1 => 'property.revenue.utilities.ledger',
            ),
          ),
          2 => 
          array (
            'label' => 'Utility intelligence',
            'route' => 'property.revenue.utilities.analytics',
            'active' => 
            array (
              0 => 'property.revenue.utilities.analytics',
            ),
          ),
        ),
      ),
      4 => 
      array (
        'type' => 'group',
        'label' => 'Controls',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Period closing',
            'route' => 'property.revenue.utilities.periods',
            'active' => 
            array (
              0 => 'property.revenue.utilities.periods',
              1 => 'property.revenue.utilities.periods.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Penalty rules',
            'route' => 'property.revenue.penalties',
            'active' => 
            array (
              0 => 'property.revenue.penalties',
              1 => 'property.revenue.penalties.store',
              2 => 'property.revenue.penalties.destroy',
            ),
          ),
        ),
      ),
      5 => 
      array (
        'type' => 'group',
        'label' => 'Bank',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Matched payments',
            'route' => 'property.equity.matched',
            'active' => 
            array (
              0 => 'property.equity.matched',
              1 => 'property.equity.matched.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Unmatched payments',
            'route' => 'property.equity.unmatched',
            'active' => 
            array (
              0 => 'property.equity.unmatched',
              1 => 'property.equity.unmatched.*',
            ),
          ),
        ),
      ),
    ),
  ),
  5 => 
  array (
    'heading' => 'Maintenance',
    'icon' => 'fa-screwdriver-wrench',
    'kicker' => 'Action-heavy',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Maintenance requests',
        'route' => 'property.maintenance.requests',
        'active' => 
        array (
          0 => 'property.maintenance.requests',
          1 => 'property.maintenance.requests.store',
        ),
      ),
      1 => 
      array (
        'label' => 'Maintenance jobs',
        'route' => 'property.maintenance.jobs',
        'active' => 
        array (
          0 => 'property.maintenance.jobs',
          1 => 'property.maintenance.jobs.store',
        ),
      ),
      2 => 
      array (
        'label' => 'Maintenance history',
        'route' => 'property.maintenance.history',
        'active' => 
        array (
          0 => 'property.maintenance.history',
        ),
      ),
      3 => 
      array (
        'label' => 'Maintenance costs',
        'route' => 'property.maintenance.costs',
        'active' => 
        array (
          0 => 'property.maintenance.costs',
        ),
      ),
      4 => 
      array (
        'label' => 'Issue frequency report',
        'route' => 'property.maintenance.frequency',
        'active' => 
        array (
          0 => 'property.maintenance.frequency',
        ),
      ),
    ),
  ),
  6 => 
  array (
    'heading' => 'Vendors',
    'icon' => 'fa-truck-field',
    'kicker' => 'Separate module',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Manage vendors',
        'route' => 'property.vendors.directory',
        'active' => 
        array (
          0 => 'property.vendors.directory',
          1 => 'property.vendors.store',
        ),
      ),
      1 => 
      array (
        'label' => 'RFQs & bidding',
        'route' => 'property.vendors.bidding',
        'active' => 
        array (
          0 => 'property.vendors.bidding',
          1 => 'property.vendors.bidding.create',
          2 => 'property.vendors.bidding.store',
        ),
      ),
      2 => 
      array (
        'label' => 'Vendor quotes',
        'route' => 'property.vendors.quotes',
        'active' => 
        array (
          0 => 'property.vendors.quotes',
        ),
      ),
      3 => 
      array (
        'label' => 'Vendor performance',
        'route' => 'property.vendors.performance',
        'active' => 
        array (
          0 => 'property.vendors.performance',
        ),
      ),
      4 => 
      array (
        'label' => 'Work records',
        'route' => 'property.vendors.work_records',
        'active' => 
        array (
          0 => 'property.vendors.work_records',
        ),
      ),
    ),
  ),
  7 => 
  array (
    'heading' => 'Analytics',
    'icon' => 'fa-chart-line',
    'kicker' => 'Not daily ops  -  avoid clutter',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Collection rate',
        'route' => 'property.performance.collection_rate',
        'active' => 
        array (
          0 => 'property.performance.collection_rate',
        ),
      ),
      1 => 
      array (
        'label' => 'Vacancy trends',
        'route' => 'property.performance.vacancy',
        'active' => 
        array (
          0 => 'property.performance.vacancy',
        ),
      ),
      2 => 
      array (
        'label' => 'Arrears trends',
        'route' => 'property.performance.arrears_trends',
        'active' => 
        array (
          0 => 'property.performance.arrears_trends',
        ),
      ),
      3 => 
      array (
        'label' => 'Maintenance cost trends',
        'route' => 'property.performance.maintenance_trends',
        'active' => 
        array (
          0 => 'property.performance.maintenance_trends',
        ),
      ),
      4 => 
      array (
        'label' => 'Tenant reliability',
        'route' => 'property.performance.tenant_reliability',
        'active' => 
        array (
          0 => 'property.performance.tenant_reliability',
        ),
      ),
    ),
  ),
  8 => 
  array (
    'heading' => 'Reports',
    'icon' => 'fa-file-lines',
    'kicker' => 'Tenant, landlord, expense, maintenance, and financial reporting',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Tenant reports',
        'sublabel' => 'Profiles, lease activity, and movements',
        'route' => 'property.reports.tenant',
        'active' => 
        array (
          0 => 'property.reports.tenant',
        ),
        'badge' => NULL,
      ),
      1 => 
      array (
        'label' => 'Landlord reports',
        'sublabel' => 'Ownership, collections, and payouts context',
        'route' => 'property.reports.landlord',
        'active' => 
        array (
          0 => 'property.reports.landlord',
        ),
        'badge' => NULL,
      ),
      2 => 
      array (
        'label' => 'Expense reports',
        'sublabel' => 'Income vs expenses and spend tracking',
        'route' => 'property.reports.expense',
        'active' => 
        array (
          0 => 'property.reports.expense',
        ),
        'badge' => NULL,
      ),
      3 => 
      array (
        'label' => 'Maintenance reports',
        'sublabel' => 'History, costs, and issue frequency',
        'route' => 'property.reports.maintenance',
        'active' => 
        array (
          0 => 'property.reports.maintenance',
        ),
        'badge' => NULL,
      ),
      4 => 
      array (
        'label' => 'Financial reports',
        'sublabel' => 'Cash flow, commissions, balances, statements',
        'route' => 'property.reports.financial',
        'active' => 
        array (
          0 => 'property.reports.financial',
        ),
        'badge' => NULL,
      ),
    ),
  ),
  9 => 
  array (
    'heading' => 'Financials',
    'icon' => 'fa-coins',
    'kicker' => 'Owner-facing money views',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Owner overview',
        'route' => 'property.financials.index',
        'active' => 
        array (
          0 => 'property.financials.index',
        ),
      ),
      1 => 
      array (
        'label' => 'Income & expenses',
        'route' => 'property.financials.income_expenses',
        'active' => 
        array (
          0 => 'property.financials.income_expenses',
        ),
      ),
      2 => 
      array (
        'label' => 'Cash flow',
        'route' => 'property.financials.cash_flow',
        'active' => 
        array (
          0 => 'property.financials.cash_flow',
        ),
      ),
      3 => 
      array (
        'label' => 'Owner balances',
        'route' => 'property.financials.owner_balances',
        'active' => 
        array (
          0 => 'property.financials.owner_balances',
        ),
      ),
      4 => 
      array (
        'label' => 'Commission report',
        'route' => 'property.financials.commission',
        'active' => 
        array (
          0 => 'property.financials.commission',
        ),
      ),
    ),
  ),
  10 => 
  array (
    'heading' => 'Accounting',
    'icon' => 'fa-book',
    'kicker' => 'Books of accounts',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Dashboard',
        'route' => 'property.accounting.index',
        'active' => 
        array (
          0 => 'property.accounting.index',
        ),
      ),
      1 => 
      array (
        'type' => 'group',
        'label' => 'Receivables',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Accounts receivable',
            'route' => 'property.accounting.receivables.accounts',
            'active' => 
            array (
              0 => 'property.accounting.receivables.accounts',
            ),
          ),
          1 => 
          array (
            'label' => 'Tenant statements',
            'route' => 'property.accounting.receivables.tenant_statements',
            'active' => 
            array (
              0 => 'property.accounting.receivables.tenant_statements',
            ),
          ),
        ),
      ),
      2 => 
      array (
        'type' => 'group',
        'label' => 'Payables',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Landlord payables',
            'route' => 'property.accounting.payables.landlord_payables',
            'active' => 
            array (
              0 => 'property.accounting.payables.landlord_payables',
            ),
          ),
          1 => 
          array (
            'label' => 'Landlord payouts',
            'route' => 'property.accounting.payables.landlord_payouts',
            'active' => 
            array (
              0 => 'property.accounting.payables.landlord_payouts',
            ),
          ),
          2 => 
          array (
            'label' => 'Accounts payable',
            'route' => 'property.accounting.payables.accounts_payable',
            'active' => 
            array (
              0 => 'property.accounting.payables.accounts_payable',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'type' => 'group',
        'label' => 'Cash & Bank',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Bank reconciliation',
            'route' => 'property.accounting.cash_bank.reconciliation',
            'active' => 
            array (
              0 => 'property.accounting.cash_bank.reconciliation',
            ),
          ),
          1 => 
          array (
            'label' => 'Cash book',
            'route' => 'property.accounting.reports.cash_book',
            'active' => 
            array (
              0 => 'property.accounting.reports.cash_book',
              1 => 'property.accounting.reports.cash_book.*',
            ),
          ),
        ),
      ),
      4 => 
      array (
        'type' => 'group',
        'label' => 'Reports',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Trial balance',
            'route' => 'property.accounting.reports.trial_balance',
            'active' => 
            array (
              0 => 'property.accounting.reports.trial_balance',
              1 => 'property.accounting.reports.trial_balance.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Income statement',
            'route' => 'property.accounting.reports.income_statement',
            'active' => 
            array (
              0 => 'property.accounting.reports.income_statement',
              1 => 'property.accounting.reports.income_statement.*',
            ),
          ),
          2 => 
          array (
            'label' => 'Balance sheet',
            'route' => 'property.accounting.reports.balance_sheet',
            'active' => 
            array (
              0 => 'property.accounting.reports.balance_sheet',
            ),
          ),
          3 => 
          array (
            'label' => 'Aged receivables',
            'route' => 'property.accounting.reports.aged_receivables',
            'active' => 
            array (
              0 => 'property.accounting.reports.aged_receivables',
            ),
          ),
          4 => 
          array (
            'label' => 'Aged payables',
            'route' => 'property.accounting.reports.aged_payables',
            'active' => 
            array (
              0 => 'property.accounting.reports.aged_payables',
            ),
          ),
          5 => 
          array (
            'label' => 'Deposit liability',
            'route' => 'property.accounting.reports.deposit_liability',
            'active' => 
            array (
              0 => 'property.accounting.reports.deposit_liability',
            ),
          ),
        ),
      ),
      5 => 
      array (
        'type' => 'group',
        'label' => 'General Ledger',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Journal entries',
            'route' => 'property.accounting.entries',
            'active' => 
            array (
              0 => 'property.accounting.entries',
              1 => 'property.accounting.entries.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Chart of accounts',
            'route' => 'property.accounting.gl.chart_accounts',
            'active' => 
            array (
              0 => 'property.accounting.gl.chart_accounts',
              1 => 'property.accounting.gl.chart_accounts.*',
            ),
          ),
          2 => 
          array (
            'label' => 'Journal batches',
            'route' => 'property.accounting.gl.journal_batches',
            'active' => 
            array (
              0 => 'property.accounting.gl.journal_batches',
              1 => 'property.accounting.gl.journal_batches.*',
            ),
          ),
        ),
      ),
      6 => 
      array (
        'type' => 'group',
        'label' => 'Payroll',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Run payroll',
            'route' => 'property.accounting.payroll',
            'active' => 
            array (
              0 => 'property.accounting.payroll',
              1 => 'property.accounting.payroll.store',
              2 => 'property.accounting.payroll.employee.store',
              3 => 'property.accounting.payroll.show',
              4 => 'property.accounting.payroll.approve',
              5 => 'property.accounting.payroll.post',
              6 => 'property.accounting.payroll.reverse',
              7 => 'property.accounting.payroll.export',
            ),
          ),
          1 => 
          array (
            'label' => 'Payslips',
            'route' => 'property.accounting.payroll.payslips',
            'active' => 
            array (
              0 => 'property.accounting.payroll.payslips',
              1 => 'property.accounting.payroll.payslips.*',
              2 => 'property.accounting.payroll.lines.*',
            ),
          ),
        ),
      ),
      7 => 
      array (
        'type' => 'group',
        'label' => 'Controls',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Audit trail',
            'route' => 'property.accounting.audit_trail',
            'active' => 
            array (
              0 => 'property.accounting.audit_trail',
              1 => 'property.accounting.audit_trail.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Reversals',
            'route' => 'property.accounting.controls.reversals',
            'active' => 
            array (
              0 => 'property.accounting.controls.reversals',
            ),
          ),
          2 => 
          array (
            'label' => 'Periods',
            'route' => 'property.accounting.controls.periods',
            'active' => 
            array (
              0 => 'property.accounting.controls.periods',
              1 => 'property.accounting.controls.periods.*',
            ),
          ),
        ),
      ),
      8 => 
      array (
        'type' => 'group',
        'label' => 'Settings',
        'items' => 
        array (
          0 => 
          array (
            'label' => 'Account mapping',
            'route' => 'property.accounting.settings.account_mapping',
            'active' => 
            array (
              0 => 'property.accounting.settings.account_mapping',
              1 => 'property.accounting.settings.account_map.*',
            ),
          ),
          1 => 
          array (
            'label' => 'Financial settings',
            'route' => 'property.accounting.settings.financial',
            'active' => 
            array (
              0 => 'property.accounting.settings.financial',
            ),
          ),
          2 => 
          array (
            'label' => 'Payroll settings',
            'route' => 'property.accounting.payroll.settings',
            'active' => 
            array (
              0 => 'property.accounting.payroll.settings',
              1 => 'property.accounting.payroll.settings.*',
            ),
          ),
        ),
      ),
    ),
  ),
  11 => 
  array (
    'heading' => 'Communications',
    'icon' => 'fa-comments',
    'kicker' => NULL,
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Send SMS / email',
        'route' => 'property.communications.messages',
        'active' => 
        array (
          0 => 'property.communications.messages',
          1 => 'property.communications.messages.store',
        ),
      ),
      1 => 
      array (
        'label' => 'Send bulk SMS',
        'route' => 'property.communications.bulk',
        'active' => 
        array (
          0 => 'property.communications.bulk',
          1 => 'property.communications.bulk.store',
        ),
      ),
      2 => 
      array (
        'label' => 'Message templates',
        'route' => 'property.communications.templates',
        'active' => 
        array (
          0 => 'property.communications.templates',
          1 => 'property.communications.templates.store',
          2 => 'property.communications.templates.destroy',
        ),
      ),
      3 => 
      array (
        'label' => 'Conversations',
        'route' => 'property.communications.conversations',
        'active' => 
        array (
          0 => 'property.communications.conversations',
          1 => 'property.communications.conversations.data',
          2 => 'property.communications.conversations.show',
          3 => 'property.communications.conversations.reply',
        ),
      ),
    ),
  ),
  12 => 
  array (
    'heading' => 'AI advisor',
    'icon' => 'fa-robot',
    'kicker' => 'Also in floating button',
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Ask AI advisor',
        'sublabel' => 'Help & insights',
        'route' => 'property.advisor',
        'active' => 
        array (
          0 => 'property.advisor',
          1 => 'property.advisor.ask',
        ),
        'badge' => NULL,
      ),
    ),
  ),
  13 => 
  array (
    'heading' => 'Settings',
    'icon' => 'fa-gear',
    'kicker' => NULL,
    'items' => 
    array (
      0 => 
      array (
        'label' => 'Settings',
        'route' => 'property.settings.index',
        'active' => 
        array (
          0 => 'property.settings.index',
        ),
      ),
      1 => 
      array (
        'label' => 'Users & roles',
        'route' => 'property.settings.roles',
        'active' => 
        array (
          0 => 'property.settings.roles',
        ),
        'requires_superadmin' => true,
      ),
      2 => 
      array (
        'label' => 'Permissions',
        'route' => 'property.settings.permissions',
        'active' => 
        array (
          0 => 'property.settings.permissions',
        ),
      ),
      3 => 
      array (
        'label' => 'Commission settings',
        'route' => 'property.settings.commission',
        'active' => 
        array (
          0 => 'property.settings.commission',
          1 => 'property.settings.commission.store',
        ),
      ),
      4 => 
      array (
        'label' => 'M-Pesa payment settings',
        'route' => 'property.settings.payments',
        'active' => 
        array (
          0 => 'property.settings.payments',
          1 => 'property.settings.payments.store',
        ),
      ),
      5 => 
      array (
        'label' => 'Deposit rules',
        'route' => 'property.settings.deposits',
        'active' => 
        array (
          0 => 'property.settings.deposits',
          1 => 'property.settings.deposits.store',
        ),
      ),
      6 => 
      array (
        'label' => 'Expense rules',
        'route' => 'property.settings.expenses',
        'active' => 
        array (
          0 => 'property.settings.expenses',
          1 => 'property.settings.expenses.store',
        ),
      ),
      7 => 
      array (
        'label' => 'Branding',
        'route' => 'property.settings.branding',
        'active' => 
        array (
          0 => 'property.settings.branding',
          1 => 'property.settings.branding.store',
        ),
      ),
      8 => 
      array (
        'label' => 'SMS forwarder',
        'route' => 'property.settings.forwarder',
        'active' => 
        array (
          0 => 'property.settings.forwarder',
          1 => 'property.settings.forwarder.store',
          2 => 'property.settings.forwarder.revoke',
        ),
      ),
      9 => 
      array (
        'label' => 'Automation rules',
        'route' => 'property.settings.rules',
        'active' => 
        array (
          0 => 'property.settings.rules',
          1 => 'property.settings.rules.store',
        ),
      ),
      10 => 
      array (
        'label' => 'System setup',
        'route' => 'property.settings.system_setup',
        'active' => 
        array (
          0 => 'property.settings.system_setup',
          1 => 'property.settings.system_setup.forms',
          2 => 'property.settings.system_setup.forms.store',
          3 => 'property.settings.system_setup.workflows',
          4 => 'property.settings.system_setup.workflows.store',
          5 => 'property.settings.system_setup.templates',
          6 => 'property.settings.system_setup.templates.store',
          7 => 'property.settings.system_setup.access',
          8 => 'property.settings.system_setup.access.roles.store',
          9 => 'property.settings.system_setup.access.roles.clone',
          10 => 'property.settings.system_setup.access.permissions.store',
          11 => 'property.settings.system_setup.access.permissions.update',
          12 => 'property.settings.system_setup.access.permissions.destroy',
          13 => 'property.settings.system_setup.access.roles.permissions.store',
          14 => 'property.settings.system_setup.access.users.roles.store',
          15 => 'property.settings.system_setup.access.users.permissions.store',
        ),
        'requires_superadmin' => true,
      ),
    ),
  ),
);
