<?php

namespace App\Support\Property;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Phase 1 — single source of truth for Property ERP agent workspaces.
 */
final class PropertyNavigation
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     sublabel: string,
     *     icon: string,
     *     route: string,
     *     active: list<string>,
     *     flyout?: list<array{label: string, route: string, active: list<string>}>,
     *     requires_pm_permission?: string|null,
     *     hidden_unless_permission?: bool,
     * }>
     */
    public static function agentWorkspaces(?User $user = null): array
    {
        return self::filterWorkspacesForSidebar(self::allAgentWorkspaces($user));
    }

    /**
     * Sidebar + header workspace strip (operational order — matches daily workflow).
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     sublabel: string,
     *     icon: string,
     *     route: string,
     *     active: list<string>,
     *     flyout?: list<array{label: string, route: string, active: list<string>}>,
     *     requires_pm_permission?: string|null,
     *     hidden_unless_permission?: bool,
     * }>
     */
    private static function filterWorkspacesForSidebar(array $workspaces): array
    {
        return array_values(array_filter(
            $workspaces,
            static fn (array $workspace): bool => ($workspace['sidebar'] ?? true) === true,
        ));
    }

    /**
     * Full workspace catalog — used for route matching and mobile drawer.
     *
     * @return list<array<string, mixed>>
     */
    public static function allAgentWorkspaces(?User $user = null): array
    {
        $user ??= auth()->user();

        $catalog = self::agentWorkspaceCatalog();
        $ordered = [];

        foreach (self::agentWorkspaceSidebarOrder() as $key) {
            if (! isset($catalog[$key])) {
                continue;
            }
            $ordered[] = $catalog[$key];
        }

        foreach ($catalog as $key => $workspace) {
            if (in_array($key, self::agentWorkspaceSidebarOrder(), true)) {
                continue;
            }
            $ordered[] = $workspace;
        }

        $workspaces = array_values(array_filter(
            $ordered,
            static function (array $workspace) use ($user): bool {
                $perm = $workspace['requires_pm_permission'] ?? null;
                if ($perm === null || $perm === '') {
                    return true;
                }
                if (! $user instanceof User) {
                    return false;
                }

                return $user->hasPmPermission($perm);
            }
        ));

        return array_map(static function (array $workspace): array {
            unset($workspace['sidebar']);

            $key = (string) ($workspace['key'] ?? '');
            if ($key === '' || ! in_array($key, PropertyWorkspaceTabs::implementedWorkspaceKeys(), true)) {
                return $workspace;
            }

            $entry = PropertyWorkspaceTabs::defaultEntryTab($key);
            if ($entry === null) {
                return $workspace;
            }

            $workspace['route'] = (string) $entry['route'];
            if (! empty($entry['route_params'])) {
                $workspace['route_params'] = $entry['route_params'];
            } else {
                unset($workspace['route_params']);
            }
            if (! empty($entry['query'])) {
                $workspace['route_query'] = $entry['query'];
            } else {
                unset($workspace['route_query']);
            }

            return $workspace;
        }, $workspaces);
    }

    /**
     * Primary sidebar order — daily property operations flow.
     *
     * @return list<string>
     */
    private static function agentWorkspaceSidebarOrder(): array
    {
        return [
            'dashboard',
            'portfolio',
            'tenants',
            'collections',
            'maintenance',
            'listings',
            'reports',
            'accounting',
            'settings',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function agentWorkspaceCatalog(): array
    {
        return [
            'dashboard' => [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'sublabel' => 'Alerts, KPIs, notifications',
                'icon' => 'fa-gauge-high',
                'route' => 'property.dashboard',
                'sidebar' => true,
                'active' => [
                    'property.dashboard',
                ],
                'flyout' => [
                    ['label' => 'Command center', 'route' => 'property.dashboard', 'active' => ['property.dashboard']],
                    ['label' => 'Search', 'route' => 'property.search', 'active' => ['property.search', 'property.search.*']],
                    ['label' => 'Analytics hub', 'route' => 'property.performance.index', 'active' => ['property.performance.index']],
                    ['label' => 'Collection rate', 'route' => 'property.performance.collection_rate', 'active' => ['property.performance.collection_rate']],
                ],
            ],
            'portfolio' => [
                'key' => 'portfolio',
                'label' => 'Portfolio',
                'sublabel' => 'Properties, units, landlords',
                'icon' => 'fa-building',
                'route' => 'property.properties.index',
                'sidebar' => true,
                'active' => [
                    'property.properties.*',
                    'property.landlords.*',
                    'property.field_officers.*',
                    'property.units.*',
                ],
                'flyout' => [
                    ['label' => 'All properties', 'route' => 'property.properties.list', 'active' => ['property.properties.list', 'property.properties.show', 'property.properties.edit']],
                    ['label' => 'Units', 'route' => 'property.properties.units', 'active' => ['property.properties.units', 'property.units.*']],
                    ['label' => 'Landlords', 'route' => 'property.landlords.index', 'active' => ['property.landlords.*']],
                    ['label' => 'Field officers', 'route' => 'property.field_officers.index', 'active' => ['property.field_officers.*']],
                    ['label' => 'Occupancy', 'route' => 'property.properties.occupancy', 'active' => ['property.properties.occupancy']],
                ],
            ],
            'tenants' => [
                'key' => 'tenants',
                'label' => 'Tenants & Leasing',
                'sublabel' => 'Directory, leases, notices',
                'icon' => 'fa-users',
                'route' => 'property.tenants.index',
                'sidebar' => true,
                'active' => [
                    'property.tenants.index',
                    'property.tenants.directory',
                    'property.tenants.directory.*',
                    'property.tenants.profiles',
                    'property.tenants.import',
                    'property.tenants.import.*',
                    'property.tenants.show',
                    'property.tenants.edit',
                    'property.tenants.update',
                    'property.tenants.destroy',
                    'property.tenants.store',
                    'property.tenants.store_json',
                    'property.tenants.statement',
                    'property.tenants.leases',
                    'property.tenants.expiry',
                    'property.tenants.movements',
                    'property.tenants.movements.*',
                    'property.tenants.notices',
                    'property.tenants.notices.*',
                    'property.leases.*',
                ],
                'flyout' => [
                    ['label' => 'Tenant directory', 'route' => 'property.tenants.directory', 'active' => ['property.tenants.directory', 'property.tenants.profiles']],
                    ['label' => 'Leases', 'route' => 'property.tenants.leases', 'active' => ['property.tenants.leases', 'property.leases.*']],
                    ['label' => 'Movements', 'route' => 'property.tenants.movements', 'active' => ['property.tenants.movements', 'property.tenants.movements.*']],
                    ['label' => 'Notices', 'route' => 'property.tenants.notices', 'active' => ['property.tenants.notices', 'property.tenants.notices.*']],
                ],
            ],
            'collections' => [
                'key' => 'collections',
                'label' => 'Collections',
                'sublabel' => 'Rent, billing, payments, utilities',
                'icon' => 'fa-sack-dollar',
                'route' => 'property.revenue.overview',
                'sidebar' => true,
                'active' => [
                    'property.revenue.*',
                    'property.equity.*',
                    'property.invoices.*',
                    'property.payments.*',
                    'property.tenants.credit.*',
                    'property.tenants.utility.statement',
                ],
                'flyout' => [
                    ['label' => 'Rent roll', 'route' => 'property.revenue.rent_roll', 'active' => ['property.revenue.rent_roll']],
                    ['label' => 'Arrears', 'route' => 'property.revenue.arrears', 'active' => ['property.revenue.arrears', 'property.revenue.arrears.*']],
                    ['label' => 'Invoices', 'route' => 'property.revenue.invoices', 'active' => ['property.revenue.invoices', 'property.revenue.invoices.*']],
                    ['label' => 'Payments', 'route' => 'property.revenue.payments', 'active' => ['property.revenue.payments', 'property.payments.*']],
                    ['label' => 'Utilities', 'route' => 'property.revenue.utilities', 'active' => ['property.revenue.utilities', 'property.revenue.utilities.*']],
                    ['label' => 'Bulk messaging', 'route' => 'property.communications.bulk', 'active' => ['property.communications.bulk', 'property.communications.bulk.*']],
                    ['label' => 'Matched payments', 'route' => 'property.equity.matched', 'active' => ['property.equity.matched', 'property.equity.matched.*']],
                    ['label' => 'Unmatched payments', 'route' => 'property.equity.unmatched', 'active' => ['property.equity.unmatched', 'property.equity.unmatched.*']],
                    ['label' => 'Tenant credits', 'route' => 'property.revenue.tenant_credits', 'active' => ['property.revenue.tenant_credits', 'property.tenants.credit.*']],
                ],
            ],
            'maintenance' => [
                'key' => 'maintenance',
                'label' => 'Maintenance',
                'sublabel' => 'Requests, jobs, vendors',
                'icon' => 'fa-screwdriver-wrench',
                'route' => 'property.maintenance.index',
                'sidebar' => true,
                'active' => [
                    'property.maintenance.*',
                ],
                'flyout' => [
                    ['label' => 'Requests', 'route' => 'property.maintenance.requests', 'active' => ['property.maintenance.requests', 'property.maintenance.requests.*']],
                    ['label' => 'Jobs', 'route' => 'property.maintenance.jobs', 'active' => ['property.maintenance.jobs', 'property.maintenance.jobs.*']],
                    ['label' => 'History', 'route' => 'property.maintenance.history', 'active' => ['property.maintenance.history']],
                    ['label' => 'Costs', 'route' => 'property.maintenance.costs', 'active' => ['property.maintenance.costs']],
                    ['label' => 'Issue frequency', 'route' => 'property.maintenance.frequency', 'active' => ['property.maintenance.frequency']],
                    ['label' => 'Vendor directory', 'route' => 'property.vendors.directory', 'active' => ['property.vendors.directory', 'property.vendors.store', 'property.vendors.show', 'property.vendors.edit']],
                    ['label' => 'RFQ & bidding', 'route' => 'property.vendors.bidding', 'active' => ['property.vendors.bidding', 'property.vendors.bidding.*']],
                ],
            ],
            'listings' => [
                'key' => 'listings',
                'label' => 'Listings',
                'sublabel' => 'Vacancies, leads, applications',
                'icon' => 'fa-sign-hanging',
                'route' => 'property.listings.create',
                'sidebar' => true,
                'active' => [
                    'property.listings.*',
                ],
                'flyout' => [
                    ['label' => 'Listing setup', 'route' => 'property.listings.create', 'active' => ['property.listings.create']],
                    ['label' => 'Vacant units', 'route' => 'property.listings.vacant', 'active' => ['property.listings.vacant', 'property.listings.vacant.*']],
                    ['label' => 'Live ads', 'route' => 'property.listings.ads', 'active' => ['property.listings.ads']],
                    ['label' => 'Leads', 'route' => 'property.listings.leads', 'active' => ['property.listings.leads', 'property.listings.leads.*']],
                    ['label' => 'Applications', 'route' => 'property.listings.applications', 'active' => ['property.listings.applications', 'property.listings.applications.*']],
                ],
            ],
            'reports' => [
                'key' => 'reports',
                'label' => 'Reports',
                'sublabel' => 'Tenant, landlord, financials',
                'icon' => 'fa-file-lines',
                'route' => 'property.reports.center',
                'sidebar' => true,
                'active' => [
                    'property.reports.*',
                    'property.exports.*',
                ],
                'flyout' => [
                    ['label' => 'Report center', 'route' => 'property.reports.center', 'active' => ['property.reports.center']],
                    ['label' => 'Tenant reports', 'route' => 'property.reports.tenant', 'active' => ['property.reports.tenant', 'property.reports.tenant.*']],
                    ['label' => 'Landlord reports', 'route' => 'property.reports.landlord', 'active' => ['property.reports.landlord', 'property.reports.landlord.*']],
                    ['label' => 'Financial summaries', 'route' => 'property.financials.index', 'active' => ['property.financials.index']],
                    ['label' => 'Income & expenses', 'route' => 'property.financials.income_expenses', 'active' => ['property.financials.income_expenses']],
                    ['label' => 'Analytics hub', 'route' => 'property.performance.index', 'active' => ['property.performance.index']],
                    ['label' => 'Arrears trends', 'route' => 'property.performance.arrears_trends', 'active' => ['property.performance.arrears_trends']],
                ],
            ],
            'accounting' => [
                'key' => 'accounting',
                'label' => 'Accounting',
                'sublabel' => 'Trust GL, payroll, controls',
                'icon' => 'fa-book',
                'route' => 'property.accounting.index',
                'sidebar' => true,
                'active' => [
                    'property.accounting.*',
                ],
                'flyout' => [
                    ['label' => 'Dashboard', 'route' => 'property.accounting.index', 'active' => ['property.accounting.index']],
                    ['label' => 'Landlord payment & fees', 'route' => 'property.accounting.payables.landlord_payment_fees', 'active' => ['property.accounting.payables.landlord_payment_fees', 'property.accounting.payables.landlord_payment_fees.*']],
                    ['label' => 'Journal entries', 'route' => 'property.accounting.entries', 'active' => ['property.accounting.entries', 'property.accounting.entries.*']],
                    ['label' => 'Chart of accounts', 'route' => 'property.accounting.gl.chart_accounts', 'active' => ['property.accounting.gl.chart_accounts', 'property.accounting.gl.chart_accounts.*']],
                    ['label' => 'Payroll', 'route' => 'property.accounting.payroll', 'active' => ['property.accounting.payroll', 'property.accounting.payroll.*']],
                ],
            ],
            'settings' => [
                'key' => 'settings',
                'label' => 'Settings',
                'sublabel' => 'Users, rules, system setup',
                'icon' => 'fa-gear',
                'route' => 'property.settings.index',
                'sidebar' => true,
                'active' => [
                    'property.settings.*',
                ],
                'requires_pm_permission' => null,
                'flyout' => [
                    ['label' => 'Settings hub', 'route' => 'property.settings.index', 'active' => ['property.settings.index']],
                    ['label' => 'Users & roles', 'route' => 'property.settings.roles', 'active' => ['property.settings.roles']],
                    ['label' => 'Permissions', 'route' => 'property.settings.permissions', 'active' => ['property.settings.permissions']],
                    ['label' => 'Commission', 'route' => 'property.settings.commission', 'active' => ['property.settings.commission', 'property.settings.commission.*']],
                    ['label' => 'Payment config', 'route' => 'property.settings.payments', 'active' => ['property.settings.payments', 'property.settings.payments.*']],
                    ['label' => 'Branding', 'route' => 'property.settings.branding', 'active' => ['property.settings.branding', 'property.settings.branding.*']],
                    ['label' => 'Automation rules', 'route' => 'property.settings.rules', 'active' => ['property.settings.rules', 'property.settings.rules.*']],
                    ['label' => 'System setup', 'route' => 'property.settings.system_setup', 'active' => ['property.settings.system_setup', 'property.settings.system_setup.*']],
                ],
            ],
            'vendors' => [
                'key' => 'vendors',
                'label' => 'Vendors',
                'sublabel' => 'Directory, RFQ, quotes',
                'icon' => 'fa-truck-field',
                'route' => 'property.vendors.index',
                'sidebar' => false,
                'active' => [
                    'property.vendors.*',
                ],
                'flyout' => [
                    ['label' => 'Vendor hub', 'route' => 'property.vendors.index', 'active' => ['property.vendors.index']],
                    ['label' => 'Vendor directory', 'route' => 'property.vendors.directory', 'active' => ['property.vendors.directory', 'property.vendors.store', 'property.vendors.show', 'property.vendors.edit']],
                    ['label' => 'RFQ & bidding', 'route' => 'property.vendors.bidding', 'active' => ['property.vendors.bidding', 'property.vendors.bidding.*']],
                    ['label' => 'Quotes', 'route' => 'property.vendors.quotes', 'active' => ['property.vendors.quotes']],
                    ['label' => 'Work records', 'route' => 'property.vendors.work_records', 'active' => ['property.vendors.work_records']],
                    ['label' => 'Vendor performance', 'route' => 'property.vendors.performance', 'active' => ['property.vendors.performance']],
                ],
            ],
            'communications' => [
                'key' => 'communications',
                'label' => 'Communications',
                'sublabel' => 'SMS, email, templates',
                'icon' => 'fa-comments',
                'route' => 'property.communications.index',
                'sidebar' => false,
                'active' => [
                    'property.communications.*',
                    'property.notifications',
                    'property.notifications.*',
                ],
                'flyout' => [
                    ['label' => 'Communications hub', 'route' => 'property.communications.index', 'active' => ['property.communications.index']],
                    ['label' => 'Notifications', 'route' => 'property.notifications', 'active' => ['property.notifications', 'property.notifications.*']],
                    ['label' => 'SMS / email log', 'route' => 'property.communications.messages', 'active' => ['property.communications.messages', 'property.communications.messages.*']],
                    ['label' => 'Bulk messaging', 'route' => 'property.communications.bulk', 'active' => ['property.communications.bulk', 'property.communications.bulk.*']],
                    ['label' => 'Templates', 'route' => 'property.communications.templates', 'active' => ['property.communications.templates', 'property.communications.templates.*']],
                    ['label' => 'Conversations', 'route' => 'property.communications.conversations', 'active' => ['property.communications.conversations', 'property.communications.conversations.*']],
                ],
            ],
            'financials' => [
                'key' => 'financials',
                'label' => 'Financials',
                'sublabel' => 'Income, cash flow, balances',
                'icon' => 'fa-coins',
                'route' => 'property.financials.index',
                'sidebar' => false,
                'active' => [
                    'property.financials.*',
                ],
                'flyout' => [
                    ['label' => 'Overview', 'route' => 'property.financials.index', 'active' => ['property.financials.index']],
                    ['label' => 'Income & expenses', 'route' => 'property.financials.income_expenses', 'active' => ['property.financials.income_expenses']],
                    ['label' => 'Cash flow', 'route' => 'property.financials.cash_flow', 'active' => ['property.financials.cash_flow']],
                    ['label' => 'Owner balances', 'route' => 'property.financials.owner_balances', 'active' => ['property.financials.owner_balances']],
                    ['label' => 'Earnings & commission', 'route' => 'property.financials.commission', 'active' => ['property.financials.commission']],
                ],
            ],
            'analytics' => [
                'key' => 'analytics',
                'label' => 'Analytics',
                'sublabel' => 'Collection, vacancy, trends',
                'icon' => 'fa-chart-line',
                'route' => 'property.performance.index',
                'sidebar' => false,
                'active' => [
                    'property.performance.*',
                ],
                'flyout' => [
                    ['label' => 'Analytics hub', 'route' => 'property.performance.index', 'active' => ['property.performance.index']],
                    ['label' => 'Collection rate', 'route' => 'property.performance.collection_rate', 'active' => ['property.performance.collection_rate']],
                    ['label' => 'Vacancy trends', 'route' => 'property.performance.vacancy', 'active' => ['property.performance.vacancy']],
                    ['label' => 'Arrears trends', 'route' => 'property.performance.arrears_trends', 'active' => ['property.performance.arrears_trends']],
                    ['label' => 'Maintenance trends', 'route' => 'property.performance.maintenance_trends', 'active' => ['property.performance.maintenance_trends']],
                    ['label' => 'Tenant reliability', 'route' => 'property.performance.tenant_reliability', 'active' => ['property.performance.tenant_reliability']],
                ],
            ],
        ];
    }

    public static function workspaceHref(array $workspace): string
    {
        $route = (string) ($workspace['route'] ?? '');

        if ($route !== '' && PropertyWorkspaceTabs::isHubShellRoute($route)) {
            $matched = self::workspaceForRoute($route);
            $workspaceKey = is_array($matched) ? (string) ($matched['key'] ?? '') : '';
            if ($workspaceKey !== '') {
                $direct = PropertyWorkspaceTabs::defaultEntryUrl($workspaceKey);
                if ($direct !== null) {
                    return $direct;
                }
            }
        }

        $entry = [
            'route' => $route,
            'route_params' => PropertyWorkspaceTabs::routeParamsWithContext(
                $workspace['route_params'] ?? [],
                $route,
            ),
            'query' => $workspace['route_query'] ?? ($workspace['query'] ?? []),
        ];

        return PropertyWorkspaceTabs::tabUrl($entry);
    }

    /**
     * First navigable href for a classic sidebar section (skips group-only rows).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function sectionDefaultHref(array $items): ?string
    {
        foreach ($items as $item) {
            $route = trim((string) ($item['route'] ?? ''));
            if ($route !== '') {
                return self::workspaceHref($item);
            }

            foreach ($item['children'] ?? [] as $child) {
                $childRoute = trim((string) ($child['route'] ?? ''));
                if ($childRoute !== '') {
                    return self::workspaceHref($child);
                }
            }
        }

        return null;
    }

    /**
     * Header workspace strip — shown only when desktop sidebar is collapsed.
     *
     * @return list<array{label: string, route: string, patterns: list<string>, key: string}>
     */
    public static function agentHeaderWorkspaces(?User $user = null): array
    {
        return array_map(static function (array $workspace): array {
            return [
                'key' => $workspace['key'],
                'label' => $workspace['label'],
                'route' => $workspace['route'],
                'route_params' => $workspace['route_params'] ?? [],
                'route_query' => $workspace['route_query'] ?? [],
                'patterns' => $workspace['active'],
            ];
        }, self::agentWorkspaces($user));
    }

    public static function routeIsActive(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || trim($pattern) === '') {
                continue;
            }
            if (self::routeNameMatches($routeName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function workspaceForRoute(?string $routeName, ?User $user = null): ?array
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return null;
        }

        foreach (self::allAgentWorkspaces($user) as $workspace) {
            if (self::routeIsActive($routeName, $workspace['active'])) {
                return $workspace;
            }
        }

        return null;
    }

    public static function navActive(array $patterns): bool
    {
        $current = Route::currentRouteName();

        return is_string($current) && self::routeIsActive($current, $patterns);
    }

    /**
     * Bottom bar shortcuts — highest-frequency daily workspaces.
     *
     * @return list<array{label: string, longLabel: string, icon: string, route: string, patterns: list<string>}>
     */
    public static function mobilePrimaryNav(?User $user = null): array
    {
        $primaryKeys = ['dashboard', 'portfolio', 'tenants', 'collections'];
        $labels = [
            'dashboard' => ['label' => 'Home', 'longLabel' => 'Dashboard'],
            'portfolio' => ['label' => 'Portfolio', 'longLabel' => 'Portfolio'],
            'tenants' => ['label' => 'Tenants', 'longLabel' => 'Tenants & Leasing'],
            'collections' => ['label' => 'Collect', 'longLabel' => 'Collections'],
        ];

        $nav = [];
        foreach ($primaryKeys as $key) {
            foreach (self::agentWorkspaces($user) as $workspace) {
                if (($workspace['key'] ?? '') !== $key) {
                    continue;
                }
                $nav[] = [
                    'label' => $labels[$key]['label'],
                    'longLabel' => $labels[$key]['longLabel'],
                    'icon' => (string) ($workspace['icon'] ?? 'fa-circle'),
                    'route' => (string) ($workspace['route'] ?? 'property.dashboard'),
                    'patterns' => $workspace['active'] ?? [],
                ];
                break;
            }
        }

        return $nav;
    }

    /**
     * @return list<array{label: string, icon: string, route: string, patterns: list<string>, tone?: string}>
     */
    public static function mobileDrawerNav(?User $user = null): array
    {
        $primaryKeys = ['dashboard', 'portfolio', 'tenants', 'collections'];
        $nav = [];

        foreach (self::agentWorkspaces($user) as $workspace) {
            $key = (string) ($workspace['key'] ?? '');
            if ($key === '' || in_array($key, $primaryKeys, true)) {
                continue;
            }
            $nav[] = [
                'label' => (string) ($workspace['label'] ?? $key),
                'icon' => (string) ($workspace['icon'] ?? 'fa-circle'),
                'route' => (string) ($workspace['route'] ?? 'property.dashboard'),
                'patterns' => $workspace['active'] ?? [],
            ];
        }

        foreach (['vendors', 'communications', 'financials', 'analytics'] as $hiddenKey) {
            foreach (self::allAgentWorkspaces($user) as $workspace) {
                if (($workspace['key'] ?? '') !== $hiddenKey) {
                    continue;
                }
                $nav[] = [
                    'label' => (string) ($workspace['label'] ?? $hiddenKey),
                    'icon' => (string) ($workspace['icon'] ?? 'fa-circle'),
                    'route' => (string) ($workspace['route'] ?? 'property.dashboard'),
                    'patterns' => $workspace['active'] ?? [],
                ];
                break;
            }
        }

        $nav[] = [
            'label' => 'AI advisor',
            'icon' => 'fa-robot',
            'route' => 'property.advisor',
            'patterns' => ['property.advisor', 'property.advisor.*'],
            'tone' => 'violet',
        ];

        return $nav;
    }

    public static function mobileMoreNavActive(string $routeName): bool
    {
        foreach (self::mobileDrawerNav() as $item) {
            if (self::routeIsActive($routeName, $item['patterns'] ?? [])) {
                return true;
            }
        }

        return self::routeIsActive($routeName, ['property.advisor', 'property.advisor.*']);
    }

    private static function routeNameMatches(string $current, string $pattern): bool
    {
        if ($pattern === $current) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return $current === $prefix || str_starts_with($current, $prefix.'.');
        }

        return false;
    }
}
