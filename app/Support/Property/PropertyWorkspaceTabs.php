<?php

namespace App\Support\Property;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

/**
 * Phase 2 — contextual workspace tab definitions and route matching.
 */
final class PropertyWorkspaceTabs
{
    /** Routes that belong to Settings (admin) rather than Collections operational tabs. */
    public static function settingsAdminRoutePatterns(): array
    {
        return [
            'property.revenue.utilities.periods.overrides.*',
            'property.revenue.utilities.period-overrides.*',
            'property.revenue.utilities.period_overrides.*',
        ];
    }

    /**
     * @return list<string>
     */
    public static function implementedWorkspaceKeys(): array
    {
        return [
            'portfolio',
            'tenants',
            'collections',
            'maintenance',
            'vendors',
            'listings',
            'communications',
            'financials',
            'reports',
            'analytics',
            'accounting',
            'settings',
        ];
    }

    /**
     * Card-grid landing routes — navigation should skip these and open the first workspace tab.
     *
     * @return list<string>
     */
    public static function hubShellRoutes(): array
    {
        return [
            'property.properties.index',
            'property.tenants.index',
            'property.maintenance.index',
            'property.vendors.index',
            'property.listings.index',
            'property.communications.index',
            'property.performance.index',
            'property.settings.index',
        ];
    }

    public static function isHubShellRoute(?string $routeName): bool
    {
        $routeName = trim((string) $routeName);

        return $routeName !== '' && in_array($routeName, self::hubShellRoutes(), true);
    }

    /**
     * @return array{route: string, route_params?: array<string, mixed>, query?: array<string, mixed>}|null
     */
    public static function defaultEntryTab(string $workspaceKey): ?array
    {
        $tabs = self::tabsFor($workspaceKey);
        if ($tabs === []) {
            return null;
        }

        foreach ($tabs as $tab) {
            $route = (string) ($tab['route'] ?? '');
            if ($route !== '' && ! self::isHubShellRoute($route)) {
                return $tab;
            }
        }

        return $tabs[0];
    }

    public static function defaultEntryUrl(string $workspaceKey): ?string
    {
        $entry = self::defaultEntryTab($workspaceKey);
        if ($entry === null) {
            return null;
        }

        return self::tabUrl($entry);
    }

    /**
     * Carry bound route parameters (e.g. {property}) into generated URLs when omitted.
     *
     * @param  array<string, mixed>  $routeParams
     * @return array<string, mixed>
     */
    public static function routeParamsWithContext(array $routeParams, string $routeName): array
    {
        try {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                return $routeParams;
            }

            foreach ($route->parameterNames() as $name) {
                if (array_key_exists($name, $routeParams) && $routeParams[$name] !== null && $routeParams[$name] !== '') {
                    continue;
                }

                $bound = request()->route($name);
                if ($bound === null) {
                    continue;
                }

                $routeParams[$name] = is_object($bound) && method_exists($bound, 'getKey')
                    ? $bound->getKey()
                    : $bound;
            }
        } catch (\Throwable) {
            // Best-effort only — caller may still fail with UrlGenerationException.
        }

        return $routeParams;
    }

    /**
     * @param  array{route: string, route_params?: array<string, mixed>, query?: array<string, mixed>}  $tab
     */
    public static function tabUrl(array $tab): string
    {
        $routeName = (string) $tab['route'];
        $routeParams = self::routeParamsWithContext($tab['route_params'] ?? [], $routeName);
        $url = route($routeName, $routeParams);
        $query = $tab['query'] ?? [];
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }

    public static function redirectToDefaultEntry(string $workspaceKey): RedirectResponse
    {
        $url = self::defaultEntryUrl($workspaceKey);
        abort_if($url === null, 404);

        return redirect()->to($url);
    }

    public static function resolveWorkspaceKey(?string $routeName): ?string
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return null;
        }

        if (PropertyNavigation::routeIsActive($routeName, self::settingsAdminRoutePatterns())) {
            return 'settings';
        }

        $workspace = PropertyNavigation::workspaceForRoute($routeName);

        return is_array($workspace) ? (string) ($workspace['key'] ?? '') : null;
    }

    public static function shouldShow(?string $routeName): bool
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return false;
        }

        if (! PropertyNavMode::showShellWorkspaceTabs()) {
            return false;
        }

        $workspaceKey = self::resolveWorkspaceKey($routeName);
        if ($workspaceKey === null || ! in_array($workspaceKey, self::implementedWorkspaceKeys(), true)) {
            return false;
        }

        foreach (['.show', '.edit', '.create', '.print', '.pdf', '.download'] as $suffix) {
            if (str_ends_with($routeName, $suffix)) {
                return false;
            }
        }

        $hiddenRoutes = [
            'property.invoices.lease_info',
            'property.payments.receipt.show',
            'property.payments.receipt.download',
            'property.revenue.invoices.edit',
            'property.accounting.entries.show',
            'property.accounting.audit_trail.show',
            'property.accounting.payroll.show',
            'property.listings.applications.show',
            'property.properties.offboarding',
        ];

        if (str_contains($routeName, '.offboarding')) {
            return false;
        }

        return ! in_array($routeName, $hiddenRoutes, true);
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     route: string,
     *     route_params?: array<string, mixed>,
     *     active: list<string>,
     *     query?: array<string, mixed>|null,
     *     query_exclude?: array<string, mixed>|null,
     * }>
     */
    public static function tabsFor(string $workspaceKey): array
    {
        return match ($workspaceKey) {
            'portfolio' => [
                ['key' => 'properties', 'label' => 'Properties', 'route' => 'property.properties.list', 'active' => ['property.properties.list', 'property.properties.store', 'property.properties.store_json', 'property.properties.update', 'property.properties.destroy', 'property.properties.offboarding', 'property.properties.offboarding.*']],
                ['key' => 'units', 'label' => 'Units', 'route' => 'property.properties.units', 'active' => ['property.properties.units', 'property.units.*']],
                ['key' => 'occupancy', 'label' => 'Occupancy', 'route' => 'property.properties.occupancy', 'active' => ['property.properties.occupancy', 'property.properties.occupancy.*']],
                ['key' => 'landlords', 'label' => 'Landlords', 'route' => 'property.landlords.index', 'active' => ['property.landlords.*']],
                ['key' => 'field_officers', 'label' => 'Field officers', 'route' => 'property.field_officers.index', 'active' => ['property.field_officers.*']],
                ['key' => 'amenities', 'label' => 'Amenities', 'route' => 'property.properties.amenities', 'active' => ['property.properties.amenities', 'property.properties.amenities.*']],
                ['key' => 'performance', 'label' => 'Performance', 'route' => 'property.properties.performance', 'active' => ['property.properties.performance']],
            ],
            'tenants' => [
                ['key' => 'directory', 'label' => 'Directory', 'route' => 'property.tenants.directory', 'active' => ['property.tenants.directory', 'property.tenants.directory.*', 'property.tenants.store', 'property.tenants.store_json', 'property.tenants.update', 'property.tenants.destroy']],
                ['key' => 'leases', 'label' => 'Leases', 'route' => 'property.tenants.leases', 'active' => ['property.tenants.leases', 'property.leases.*'], 'query_exclude' => ['tab' => 'expiry']],
                ['key' => 'notices', 'label' => 'Notices', 'route' => 'property.tenants.notices', 'active' => ['property.tenants.notices', 'property.tenants.notices.*']],
                ['key' => 'movements', 'label' => 'Move-ins/out', 'route' => 'property.tenants.movements', 'active' => ['property.tenants.movements', 'property.tenants.movements.*']],
                ['key' => 'compliance', 'label' => 'Compliance', 'route' => 'property.tenants.profiles', 'active' => ['property.tenants.profiles', 'property.tenants.import', 'property.tenants.import.*']],
            ],
            'collections' => [
                ['key' => 'overview', 'label' => 'Overview', 'route' => 'property.revenue.overview', 'active' => ['property.revenue.overview', 'property.revenue.index']],
                ['key' => 'rent_roll', 'label' => 'Rent roll', 'route' => 'property.revenue.rent_roll', 'active' => ['property.revenue.rent_roll']],
                ['key' => 'arrears', 'label' => 'Arrears', 'route' => 'property.revenue.arrears', 'active' => ['property.revenue.arrears', 'property.revenue.arrears.*']],
                ['key' => 'invoices', 'label' => 'Invoices', 'route' => 'property.revenue.invoices', 'active' => ['property.revenue.invoices', 'property.revenue.invoices.*', 'property.invoices.*']],
                ['key' => 'uninvoiced', 'label' => 'Uninvoiced leases', 'route' => 'property.revenue.uninvoiced_leases', 'active' => ['property.revenue.uninvoiced_leases', 'property.revenue.uninvoiced_leases.*']],
                ['key' => 'payments', 'label' => 'Payments', 'route' => 'property.revenue.payments', 'active' => ['property.revenue.payments', 'property.payments.*']],
                ['key' => 'utilities', 'label' => 'Utilities', 'route' => 'property.revenue.utilities', 'active' => ['property.revenue.utilities', 'property.revenue.utilities.*', 'property.tenants.utility.statement']],
                ['key' => 'receipts', 'label' => 'Receipts', 'route' => 'property.revenue.receipts', 'active' => ['property.revenue.receipts']],
                ['key' => 'penalties', 'label' => 'Penalties', 'route' => 'property.revenue.penalties', 'active' => ['property.revenue.penalties', 'property.revenue.penalties.*']],
                ['key' => 'matched', 'label' => 'Matched payments', 'route' => 'property.equity.matched', 'active' => ['property.equity.matched', 'property.equity.matched.*']],
                ['key' => 'unmatched', 'label' => 'Unmatched', 'route' => 'property.equity.unmatched', 'active' => ['property.equity.unmatched', 'property.equity.unmatched.*', 'property.equity.all']],
                ['key' => 'tenant_credits', 'label' => 'Tenant credits', 'route' => 'property.revenue.tenant_credits', 'active' => ['property.revenue.tenant_credits', 'property.tenants.credit.*']],
            ],
            'maintenance' => [
                ['key' => 'requests', 'label' => 'Requests', 'route' => 'property.maintenance.requests', 'active' => ['property.maintenance.requests', 'property.maintenance.requests.*']],
                ['key' => 'jobs', 'label' => 'Jobs', 'route' => 'property.maintenance.jobs', 'active' => ['property.maintenance.jobs', 'property.maintenance.jobs.*']],
                ['key' => 'history', 'label' => 'History', 'route' => 'property.maintenance.history', 'active' => ['property.maintenance.history']],
                ['key' => 'costs', 'label' => 'Costs', 'route' => 'property.maintenance.costs', 'active' => ['property.maintenance.costs']],
                ['key' => 'frequency', 'label' => 'Issue frequency', 'route' => 'property.maintenance.frequency', 'active' => ['property.maintenance.frequency']],
            ],
            'vendors' => [
                ['key' => 'hub', 'label' => 'Hub', 'route' => 'property.vendors.index', 'active' => ['property.vendors.index']],
                ['key' => 'directory', 'label' => 'Directory', 'route' => 'property.vendors.directory', 'active' => ['property.vendors.directory', 'property.vendors.store', 'property.vendors.show', 'property.vendors.edit', 'property.vendors.update', 'property.vendors.destroy']],
                ['key' => 'bidding', 'label' => 'RFQ & bidding', 'route' => 'property.vendors.bidding', 'active' => ['property.vendors.bidding', 'property.vendors.bidding.*']],
                ['key' => 'quotes', 'label' => 'Quotes', 'route' => 'property.vendors.quotes', 'active' => ['property.vendors.quotes']],
                ['key' => 'work_records', 'label' => 'Work records', 'route' => 'property.vendors.work_records', 'active' => ['property.vendors.work_records']],
                ['key' => 'performance', 'label' => 'Performance', 'route' => 'property.vendors.performance', 'active' => ['property.vendors.performance']],
            ],
            'listings' => [
                ['key' => 'setup', 'label' => 'Setup', 'route' => 'property.listings.create', 'active' => ['property.listings.create']],
                ['key' => 'vacant', 'label' => 'Vacant units', 'route' => 'property.listings.vacant', 'active' => ['property.listings.vacant', 'property.listings.vacant.*']],
                ['key' => 'ads', 'label' => 'Live ads', 'route' => 'property.listings.ads', 'active' => ['property.listings.ads']],
                ['key' => 'leads', 'label' => 'Leads', 'route' => 'property.listings.leads', 'active' => ['property.listings.leads', 'property.listings.leads.*']],
                ['key' => 'applications', 'label' => 'Applications', 'route' => 'property.listings.applications', 'active' => ['property.listings.applications', 'property.listings.applications.*']],
            ],
            'communications' => [
                ['key' => 'hub', 'label' => 'Hub', 'route' => 'property.communications.index', 'active' => ['property.communications.index']],
                ['key' => 'notifications', 'label' => 'Notifications', 'route' => 'property.notifications', 'active' => ['property.notifications', 'property.notifications.*']],
                ['key' => 'messages', 'label' => 'SMS / email', 'route' => 'property.communications.messages', 'active' => ['property.communications.messages', 'property.communications.messages.*']],
                ['key' => 'provider_sms', 'label' => 'Provider SMS', 'route' => 'property.communications.sms_provider', 'active' => ['property.communications.sms_provider']],
                ['key' => 'bulk', 'label' => 'Bulk messaging', 'route' => 'property.communications.bulk', 'active' => ['property.communications.bulk', 'property.communications.bulk.*', 'property.communications.recipients']],
                ['key' => 'templates', 'label' => 'Templates', 'route' => 'property.communications.templates', 'active' => ['property.communications.templates', 'property.communications.templates.*']],
                ['key' => 'rent_templates', 'label' => 'Rent templates', 'route' => 'property.communications.rent_templates', 'active' => ['property.communications.rent_templates', 'property.communications.rent_templates.*']],
                ['key' => 'conversations', 'label' => 'Conversations', 'route' => 'property.communications.conversations', 'active' => ['property.communications.conversations', 'property.communications.conversations.*']],
            ],
            'financials' => [
                ['key' => 'overview', 'label' => 'Overview', 'route' => 'property.financials.index', 'active' => ['property.financials.index']],
                ['key' => 'income_expenses', 'label' => 'Income & expenses', 'route' => 'property.financials.income_expenses', 'active' => ['property.financials.income_expenses']],
                ['key' => 'cash_flow', 'label' => 'Cash flow', 'route' => 'property.financials.cash_flow', 'active' => ['property.financials.cash_flow']],
                ['key' => 'owner_balances', 'label' => 'Owner balances', 'route' => 'property.financials.owner_balances', 'active' => ['property.financials.owner_balances']],
                ['key' => 'commission', 'label' => 'Earnings & commission', 'route' => 'property.financials.commission', 'active' => ['property.financials.commission']],
            ],
            'reports' => [
                ['key' => 'center', 'label' => 'Center', 'route' => 'property.reports.center', 'active' => ['property.reports.center']],
                ['key' => 'tenant', 'label' => 'Tenant', 'route' => 'property.reports.tenant', 'active' => ['property.reports.tenant', 'property.reports.tenant.*']],
                ['key' => 'landlord', 'label' => 'Landlord', 'route' => 'property.reports.landlord', 'active' => ['property.reports.landlord', 'property.reports.landlord.*']],
                ['key' => 'expense', 'label' => 'Expense', 'route' => 'property.reports.expense', 'active' => ['property.reports.expense', 'property.reports.expense.*']],
                ['key' => 'maintenance', 'label' => 'Maintenance', 'route' => 'property.reports.maintenance', 'active' => ['property.reports.maintenance', 'property.reports.maintenance.*']],
                ['key' => 'financial', 'label' => 'Financial', 'route' => 'property.reports.financial', 'active' => ['property.reports.financial', 'property.reports.financial.*']],
            ],
            'analytics' => [
                ['key' => 'overview', 'label' => 'Overview', 'route' => 'property.performance.index', 'active' => ['property.performance.index']],
                ['key' => 'collection_rate', 'label' => 'Collection rate', 'route' => 'property.performance.collection_rate', 'active' => ['property.performance.collection_rate']],
                ['key' => 'vacancy', 'label' => 'Vacancy', 'route' => 'property.performance.vacancy', 'active' => ['property.performance.vacancy']],
                ['key' => 'arrears_trends', 'label' => 'Arrears trends', 'route' => 'property.performance.arrears_trends', 'active' => ['property.performance.arrears_trends']],
                ['key' => 'maintenance_trends', 'label' => 'Maintenance trends', 'route' => 'property.performance.maintenance_trends', 'active' => ['property.performance.maintenance_trends']],
                ['key' => 'tenant_reliability', 'label' => 'Tenant reliability', 'route' => 'property.performance.tenant_reliability', 'active' => ['property.performance.tenant_reliability']],
            ],
            'accounting' => [
                ['key' => 'overview', 'label' => 'Overview', 'route' => 'property.accounting.index', 'active' => ['property.accounting.index']],
                ['key' => 'gl', 'label' => 'GL', 'route' => 'property.accounting.entries', 'active' => ['property.accounting.entries', 'property.accounting.entries.*', 'property.accounting.gl.*']],
                ['key' => 'receivables', 'label' => 'Receivables', 'route' => 'property.accounting.receivables.accounts', 'active' => ['property.accounting.receivables.*']],
                ['key' => 'payables', 'label' => 'Payables', 'route' => 'property.accounting.payables.landlord_payment_fees', 'active' => ['property.accounting.payables.*']],
                ['key' => 'cash_bank', 'label' => 'Cash & Bank', 'route' => 'property.accounting.cash_bank.reconciliation', 'active' => ['property.accounting.cash_bank.*', 'property.accounting.reports.cash_book', 'property.accounting.reports.cash_book.*']],
                ['key' => 'reports', 'label' => 'Reports', 'route' => 'property.accounting.reports.trial_balance', 'active' => ['property.accounting.reports.trial_balance', 'property.accounting.reports.trial_balance.*', 'property.accounting.reports.income_statement', 'property.accounting.reports.income_statement.*', 'property.accounting.reports.balance_sheet', 'property.accounting.reports.aged_receivables', 'property.accounting.reports.aged_payables', 'property.accounting.reports.deposit_liability']],
                ['key' => 'controls', 'label' => 'Controls', 'route' => 'property.accounting.audit_trail', 'active' => ['property.accounting.audit_trail', 'property.accounting.audit_trail.*', 'property.accounting.controls.*']],
                ['key' => 'setup', 'label' => 'Setup', 'route' => 'property.accounting.settings.account_mapping', 'active' => ['property.accounting.settings.*', 'property.accounting.payroll.settings', 'property.accounting.payroll.settings.*']],
            ],
            'settings' => [
                ['key' => 'hub', 'label' => 'Overview', 'route' => 'property.settings.index', 'active' => ['property.settings.index']],
                ['key' => 'roles', 'label' => 'Users & roles', 'route' => 'property.settings.roles', 'active' => ['property.settings.roles']],
                ['key' => 'permissions', 'label' => 'Permissions', 'route' => 'property.settings.permissions', 'active' => ['property.settings.permissions']],
                ['key' => 'commission', 'label' => 'Commission', 'route' => 'property.settings.commission', 'active' => ['property.settings.commission', 'property.settings.commission.*']],
                ['key' => 'payments', 'label' => 'Payment config', 'route' => 'property.settings.payments', 'active' => ['property.settings.payments', 'property.settings.payments.*']],
                ['key' => 'branding', 'label' => 'Branding', 'route' => 'property.settings.branding', 'active' => ['property.settings.branding', 'property.settings.branding.*']],
                ['key' => 'rules', 'label' => 'Automation rules', 'route' => 'property.settings.rules', 'active' => ['property.settings.rules', 'property.settings.rules.*']],
                ['key' => 'deposits', 'label' => 'Deposit rules', 'route' => 'property.settings.deposits', 'active' => ['property.settings.deposits', 'property.settings.deposits.*']],
                ['key' => 'expenses', 'label' => 'Expense rules', 'route' => 'property.settings.expenses', 'active' => ['property.settings.expenses', 'property.settings.expenses.*']],
                ['key' => 'forwarder', 'label' => 'SMS forwarder', 'route' => 'property.settings.forwarder', 'active' => ['property.settings.forwarder', 'property.settings.forwarder.*']],
                ['key' => 'system_setup', 'label' => 'System setup', 'route' => 'property.settings.system_setup', 'active' => ['property.settings.system_setup', 'property.settings.system_setup.*']],
            ],
            default => [],
        };
    }

    /**
     * Workspace tabs that expose a second-level sub-nav strip (Utilities-style).
     *
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    public static function subTabGroupsFor(string $workspaceKey): array
    {
        return match ($workspaceKey) {
            'collections' => self::collectionsSubTabGroups(),
            'tenants' => self::tenantsSubTabGroups(),
            'settings' => self::settingsSubTabGroups(),
            'accounting' => self::accountingSubTabGroups(),
            'reports' => self::reportsSubTabGroups(),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    private static function collectionsSubTabGroups(): array
    {
        return [
            [
                'label' => 'Utilities',
                'tabs' => self::collectionsUtilitySubTabs(),
            ],
        ];
    }

    /**
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    private static function tenantsSubTabGroups(): array
    {
        return [
            [
                'label' => 'Leases',
                'tabs' => [
                    ['key' => 'leases', 'label' => 'All leases', 'route' => 'property.tenants.leases', 'active' => ['property.leases.*', 'property.tenants.leases'], 'query_exclude' => ['tab' => 'expiry']],
                    ['key' => 'expiry', 'label' => 'Expiring soon', 'route' => 'property.tenants.leases', 'query' => ['tab' => 'expiry'], 'active' => ['property.tenants.leases']],
                ],
            ],
        ];
    }

    /**
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    private static function settingsSubTabGroups(): array
    {
        return [
            [
                'label' => 'System setup',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'Overview', 'route' => 'property.settings.system_setup', 'active' => ['property.settings.system_setup']],
                    ['key' => 'forms', 'label' => 'Form switches', 'route' => 'property.settings.system_setup.forms', 'active' => ['property.settings.system_setup.forms', 'property.settings.system_setup.forms.*']],
                    ['key' => 'workflows', 'label' => 'Workflows', 'route' => 'property.settings.system_setup.workflows', 'active' => ['property.settings.system_setup.workflows', 'property.settings.system_setup.workflows.*']],
                    ['key' => 'templates', 'label' => 'Templates', 'route' => 'property.settings.system_setup.templates', 'active' => ['property.settings.system_setup.templates', 'property.settings.system_setup.templates.*']],
                    ['key' => 'access', 'label' => 'Access control', 'route' => 'property.settings.system_setup.access', 'active' => ['property.settings.system_setup.access', 'property.settings.system_setup.access.*']],
                ],
            ],
            [
                'label' => 'Field modules',
                'tabs' => [
                    ['key' => 'property_fields', 'label' => 'Property', 'route' => 'property.settings.system_setup.property_onboarding_fields', 'active' => ['property.settings.system_setup.property_onboarding_fields', 'property.settings.system_setup.property_onboarding_fields.*']],
                    ['key' => 'unit_fields', 'label' => 'Units', 'route' => 'property.settings.system_setup.unit_fields', 'active' => ['property.settings.system_setup.unit_fields', 'property.settings.system_setup.unit_fields.*']],
                    ['key' => 'tenant_fields', 'label' => 'Tenants', 'route' => 'property.settings.system_setup.tenant_fields', 'active' => ['property.settings.system_setup.tenant_fields', 'property.settings.system_setup.tenant_fields.*']],
                    ['key' => 'lease_fields', 'label' => 'Leases', 'route' => 'property.settings.system_setup.lease_fields', 'active' => ['property.settings.system_setup.lease_fields', 'property.settings.system_setup.lease_fields.*']],
                    ['key' => 'landlord_fields', 'label' => 'Landlords', 'route' => 'property.settings.system_setup.landlord_fields', 'active' => ['property.settings.system_setup.landlord_fields', 'property.settings.system_setup.landlord_fields.*']],
                    ['key' => 'invoice_fields', 'label' => 'Invoices', 'route' => 'property.settings.system_setup.invoice_fields', 'active' => ['property.settings.system_setup.invoice_fields', 'property.settings.system_setup.invoice_fields.*']],
                    ['key' => 'maintenance_fields', 'label' => 'Maintenance', 'route' => 'property.settings.system_setup.maintenance_fields', 'active' => ['property.settings.system_setup.maintenance_fields', 'property.settings.system_setup.maintenance_fields.*']],
                    ['key' => 'vendor_fields', 'label' => 'Vendors', 'route' => 'property.settings.system_setup.vendor_fields', 'active' => ['property.settings.system_setup.vendor_fields', 'property.settings.system_setup.vendor_fields.*']],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, tabs: list<array<string, mixed>>}|null
     */
    public static function resolveActiveSubTabGroup(?string $routeName): ?array
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return null;
        }

        if (PropertyNavigation::routeIsActive($routeName, self::settingsAdminRoutePatterns())) {
            return null;
        }

        $workspaceKey = self::resolveWorkspaceKey($routeName);
        if ($workspaceKey === null) {
            return null;
        }

        foreach (self::subTabGroupsFor($workspaceKey) as $group) {
            foreach ($group['tabs'] as $tab) {
                if (self::tabIsActive($tab, $routeName)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    private static function accountingSubTabGroups(): array
    {
        return [
            [
                'label' => 'GL',
                'tabs' => [
                    ['key' => 'entries', 'label' => 'Journal entries', 'route' => 'property.accounting.entries', 'active' => ['property.accounting.entries', 'property.accounting.entries.*']],
                    ['key' => 'chart', 'label' => 'Chart of accounts', 'route' => 'property.accounting.gl.chart_accounts', 'active' => ['property.accounting.gl.chart_accounts', 'property.accounting.gl.chart_accounts.*']],
                    ['key' => 'batches', 'label' => 'Journal batches', 'route' => 'property.accounting.gl.journal_batches', 'active' => ['property.accounting.gl.journal_batches', 'property.accounting.gl.journal_batches.*']],
                ],
            ],
            [
                'label' => 'Receivables',
                'tabs' => [
                    ['key' => 'accounts', 'label' => 'AR accounts', 'route' => 'property.accounting.receivables.accounts', 'active' => ['property.accounting.receivables.accounts']],
                    ['key' => 'tenant_statements', 'label' => 'Tenant statements', 'route' => 'property.accounting.receivables.tenant_statements', 'active' => ['property.accounting.receivables.tenant_statements']],
                ],
            ],
            [
                'label' => 'Payables',
                'tabs' => [
                    ['key' => 'landlord_payment_fees', 'label' => 'Landlord payment & fees', 'route' => 'property.accounting.payables.landlord_payment_fees', 'active' => ['property.accounting.payables.landlord_payment_fees', 'property.accounting.payables.landlord_payment_fees.*']],
                    ['key' => 'landlord_settlements', 'label' => 'Landlord settlements', 'route' => 'property.accounting.payables.landlord_settlements', 'active' => ['property.accounting.payables.landlord_settlements', 'property.accounting.payables.landlord_settlements.*']],
                    ['key' => 'landlord_payables', 'label' => 'Landlord payables', 'route' => 'property.accounting.payables.landlord_payables', 'active' => ['property.accounting.payables.landlord_payables']],
                    ['key' => 'landlord_payouts', 'label' => 'Landlord payouts', 'route' => 'property.accounting.payables.landlord_payouts', 'active' => ['property.accounting.payables.landlord_payouts']],
                    ['key' => 'landlord_advances', 'label' => 'Advances & pay dates', 'route' => 'property.accounting.payables.landlord_advances', 'active' => ['property.accounting.payables.landlord_advances', 'property.accounting.payables.landlord_advances.*']],
                    ['key' => 'accounts_payable', 'label' => 'Accounts payable', 'route' => 'property.accounting.payables.accounts_payable', 'active' => ['property.accounting.payables.accounts_payable']],
                ],
            ],
            [
                'label' => 'Cash & Bank',
                'tabs' => [
                    ['key' => 'reconciliation', 'label' => 'Reconciliation', 'route' => 'property.accounting.cash_bank.reconciliation', 'active' => ['property.accounting.cash_bank.reconciliation']],
                    ['key' => 'cash_book', 'label' => 'Cash book', 'route' => 'property.accounting.reports.cash_book', 'active' => ['property.accounting.reports.cash_book', 'property.accounting.reports.cash_book.*']],
                ],
            ],
            [
                'label' => 'GL reports',
                'tabs' => [
                    ['key' => 'trial_balance', 'label' => 'Trial balance', 'route' => 'property.accounting.reports.trial_balance', 'active' => ['property.accounting.reports.trial_balance', 'property.accounting.reports.trial_balance.*']],
                    ['key' => 'income_statement', 'label' => 'Income statement', 'route' => 'property.accounting.reports.income_statement', 'active' => ['property.accounting.reports.income_statement', 'property.accounting.reports.income_statement.*']],
                    ['key' => 'balance_sheet', 'label' => 'Balance sheet', 'route' => 'property.accounting.reports.balance_sheet', 'active' => ['property.accounting.reports.balance_sheet']],
                    ['key' => 'aged_receivables', 'label' => 'Aged receivables', 'route' => 'property.accounting.reports.aged_receivables', 'active' => ['property.accounting.reports.aged_receivables']],
                    ['key' => 'aged_payables', 'label' => 'Aged payables', 'route' => 'property.accounting.reports.aged_payables', 'active' => ['property.accounting.reports.aged_payables']],
                    ['key' => 'deposit_liability', 'label' => 'Deposit liability', 'route' => 'property.accounting.reports.deposit_liability', 'active' => ['property.accounting.reports.deposit_liability']],
                ],
            ],
            [
                'label' => 'Controls',
                'tabs' => [
                    ['key' => 'audit_trail', 'label' => 'Audit trail', 'route' => 'property.accounting.audit_trail', 'active' => ['property.accounting.audit_trail', 'property.accounting.audit_trail.*']],
                    ['key' => 'reversals', 'label' => 'Reversals', 'route' => 'property.accounting.controls.reversals', 'active' => ['property.accounting.controls.reversals']],
                    ['key' => 'periods', 'label' => 'Periods', 'route' => 'property.accounting.controls.periods', 'active' => ['property.accounting.controls.periods', 'property.accounting.controls.periods.*']],
                ],
            ],
            [
                'label' => 'Setup',
                'tabs' => [
                    ['key' => 'account_mapping', 'label' => 'Account mapping', 'route' => 'property.accounting.settings.account_mapping', 'active' => ['property.accounting.settings.account_mapping']],
                    ['key' => 'financial', 'label' => 'Financial settings', 'route' => 'property.accounting.settings.financial', 'active' => ['property.accounting.settings.financial']],
                    ['key' => 'payroll_settings', 'label' => 'Payroll settings', 'route' => 'property.accounting.payroll.settings', 'active' => ['property.accounting.payroll.settings', 'property.accounting.payroll.settings.*']],
                ],
            ],
        ];
    }

    /**
     * @return list<array{label: string, tabs: list<array<string, mixed>>}>
     */
    private static function reportsSubTabGroups(): array
    {
        return [
            [
                'label' => 'Tenant',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'All reports', 'route' => 'property.reports.tenant', 'active' => ['property.reports.tenant']],
                    ['key' => 'statements', 'label' => 'Statements', 'route' => 'property.reports.tenant.statements', 'active' => ['property.reports.tenant.statements']],
                    ['key' => 'rent_penalties', 'label' => 'Rent penalties', 'route' => 'property.reports.tenant.rent_penalties', 'active' => ['property.reports.tenant.rent_penalties']],
                    ['key' => 'de_allocation', 'label' => 'De-allocation', 'route' => 'property.reports.tenant.de_allocation', 'active' => ['property.reports.tenant.de_allocation']],
                    ['key' => 'allocation', 'label' => 'Allocation', 'route' => 'property.reports.tenant.allocation', 'active' => ['property.reports.tenant.allocation']],
                    ['key' => 'deposits', 'label' => 'Deposits', 'route' => 'property.reports.tenant.deposits', 'active' => ['property.reports.tenant.deposits']],
                    ['key' => 'aging_balance', 'label' => 'Aging balance', 'route' => 'property.reports.tenant.aging_balance', 'active' => ['property.reports.tenant.aging_balance']],
                    ['key' => 'statements_by_allocation', 'label' => 'By allocation', 'route' => 'property.reports.tenant.statements_by_allocation', 'active' => ['property.reports.tenant.statements_by_allocation']],
                ],
            ],
            [
                'label' => 'Landlord',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'All reports', 'route' => 'property.reports.landlord', 'active' => ['property.reports.landlord']],
                    ['key' => 'statements', 'label' => 'Statements', 'route' => 'property.reports.landlord.statements', 'active' => ['property.reports.landlord.statements', 'property.reports.landlord.statements.*']],
                    ['key' => 'detailed_statement', 'label' => 'Detailed statement', 'route' => 'property.reports.landlord.detailed_statement', 'active' => ['property.reports.landlord.detailed_statement']],
                    ['key' => 'balance_summary', 'label' => 'Balance summary', 'route' => 'property.reports.landlord.balance_summary', 'active' => ['property.reports.landlord.balance_summary']],
                    ['key' => 'rental_income_commissions', 'label' => 'Income & commissions', 'route' => 'property.reports.landlord.rental_income_commissions', 'active' => ['property.reports.landlord.rental_income_commissions']],
                    ['key' => 'rent_collection', 'label' => 'Rent collection', 'route' => 'property.reports.landlord.rent_collection', 'active' => ['property.reports.landlord.rent_collection']],
                    ['key' => 'property_statement', 'label' => 'Property statement', 'route' => 'property.reports.landlord.property_statement', 'active' => ['property.reports.landlord.property_statement']],
                ],
            ],
            [
                'label' => 'Expense',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'All reports', 'route' => 'property.reports.expense', 'active' => ['property.reports.expense']],
                    ['key' => 'income_expenses_summary', 'label' => 'Income & expenses', 'route' => 'property.reports.expense.income_expenses_summary', 'active' => ['property.reports.expense.income_expenses_summary']],
                    ['key' => 'maintenance_expense', 'label' => 'Maintenance expense', 'route' => 'property.reports.expense.maintenance_expense', 'active' => ['property.reports.expense.maintenance_expense']],
                    ['key' => 'utility_billing', 'label' => 'Utility billing', 'route' => 'property.reports.expense.utility_billing', 'active' => ['property.reports.expense.utility_billing']],
                    ['key' => 'utility_aging', 'label' => 'Utility AR aging', 'route' => 'property.reports.expense.utility_aging', 'active' => ['property.reports.expense.utility_aging']],
                    ['key' => 'vendor_expense_work', 'label' => 'Vendor expense work', 'route' => 'property.reports.expense.vendor_expense_work', 'active' => ['property.reports.expense.vendor_expense_work']],
                    ['key' => 'cash_book', 'label' => 'Cash book', 'route' => 'property.reports.expense.cash_book', 'active' => ['property.reports.expense.cash_book']],
                ],
            ],
            [
                'label' => 'Maintenance',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'All reports', 'route' => 'property.reports.maintenance', 'active' => ['property.reports.maintenance']],
                    ['key' => 'history', 'label' => 'History', 'route' => 'property.reports.maintenance.history', 'active' => ['property.reports.maintenance.history']],
                    ['key' => 'cost', 'label' => 'Cost', 'route' => 'property.reports.maintenance.cost', 'active' => ['property.reports.maintenance.cost']],
                    ['key' => 'frequency', 'label' => 'Issue frequency', 'route' => 'property.reports.maintenance.frequency', 'active' => ['property.reports.maintenance.frequency']],
                    ['key' => 'audit_trail', 'label' => 'Audit trail', 'route' => 'property.reports.maintenance.audit_trail', 'active' => ['property.reports.maintenance.audit_trail']],
                    ['key' => 'email_logs', 'label' => 'Email logs', 'route' => 'property.reports.maintenance.email_logs', 'active' => ['property.reports.maintenance.email_logs']],
                    ['key' => 'login_logs', 'label' => 'Login logs', 'route' => 'property.reports.maintenance.login_logs', 'active' => ['property.reports.maintenance.login_logs']],
                ],
            ],
            [
                'label' => 'Financial',
                'tabs' => [
                    ['key' => 'hub', 'label' => 'All reports', 'route' => 'property.reports.financial', 'active' => ['property.reports.financial']],
                    ['key' => 'profit_loss_summary', 'label' => 'P&L summary', 'route' => 'property.reports.financial.profit_loss_summary', 'active' => ['property.reports.financial.profit_loss_summary']],
                    ['key' => 'profit_loss_comparison', 'label' => 'P&L comparison', 'route' => 'property.reports.financial.profit_loss_comparison', 'active' => ['property.reports.financial.profit_loss_comparison']],
                    ['key' => 'profit_loss_department', 'label' => 'P&L by department', 'route' => 'property.reports.financial.profit_loss_department', 'active' => ['property.reports.financial.profit_loss_department']],
                    ['key' => 'profit_loss_months', 'label' => 'P&L by month', 'route' => 'property.reports.financial.profit_loss_months', 'active' => ['property.reports.financial.profit_loss_months']],
                    ['key' => 'manufacturing_account', 'label' => 'Manufacturing account', 'route' => 'property.reports.financial.manufacturing_account', 'active' => ['property.reports.financial.manufacturing_account']],
                    ['key' => 'balance_sheet_standard', 'label' => 'Balance sheet', 'route' => 'property.reports.financial.balance_sheet_standard', 'active' => ['property.reports.financial.balance_sheet_standard']],
                    ['key' => 'balance_sheet_itemised', 'label' => 'Balance sheet (itemised)', 'route' => 'property.reports.financial.balance_sheet_itemised', 'active' => ['property.reports.financial.balance_sheet_itemised']],
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, route: string, route_params?: array<string, mixed>, active: list<string>}>
     */
    public static function collectionsUtilitySubTabs(): array
    {
        return [
            ['key' => 'billing', 'label' => 'Billing', 'route' => 'property.revenue.utilities', 'active' => ['property.revenue.utilities', 'property.revenue.utilities.store', 'property.revenue.utilities.destroy', 'property.revenue.utilities.water_readings.*', 'property.revenue.utilities.water_invoices.*', 'property.revenue.utilities.invoices.*']],
            ['key' => 'reconciliation', 'label' => 'Reconciliation', 'route' => 'property.revenue.utilities.reconciliation', 'active' => ['property.revenue.utilities.reconciliation', 'property.revenue.utilities.ledger']],
            ['key' => 'intelligence', 'label' => 'Intelligence', 'route' => 'property.revenue.utilities.analytics', 'active' => ['property.revenue.utilities.analytics']],
            ['key' => 'period_closing', 'label' => 'Period closing', 'route' => 'property.revenue.utilities.periods', 'active' => ['property.revenue.utilities.periods', 'property.revenue.utilities.periods.*']],
        ];
    }

    public static function isCollectionsUtilityRoute(?string $routeName): bool
    {
        $group = self::resolveActiveSubTabGroup($routeName);

        return is_array($group) && ($group['label'] ?? '') === 'Utilities';
    }

    public static function tabIsActive(array $tab, ?string $routeName = null): bool
    {
        $routeName = trim((string) ($routeName ?? Route::currentRouteName() ?? ''));
        if ($routeName === '') {
            return false;
        }

        if (! PropertyNavigation::routeIsActive($routeName, $tab['active'] ?? [])) {
            return false;
        }

        if (! empty($tab['query']) && is_array($tab['query'])) {
            foreach ($tab['query'] as $key => $expected) {
                if ((string) request()->query($key, '') !== (string) $expected) {
                    return false;
                }
            }
        }

        if (! empty($tab['query_exclude']) && is_array($tab['query_exclude'])) {
            foreach ($tab['query_exclude'] as $key => $excluded) {
                if ((string) request()->query($key, '') === (string) $excluded) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function workspaceLabel(string $workspaceKey): string
    {
        foreach (PropertyNavigation::allAgentWorkspaces() as $workspace) {
            if (($workspace['key'] ?? '') === $workspaceKey) {
                return (string) ($workspace['label'] ?? ucfirst($workspaceKey));
            }
        }

        return ucfirst($workspaceKey);
    }
}
