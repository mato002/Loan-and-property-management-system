<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Loan module workspaces, sidebar / quick-nav visibility by {@see User::$loan_role}.
 * Aligns navigation with route middleware (e.g. accounting/financial require accountant|admin|manager).
 */
final class LoanNavigation
{
    /**
     * Sidebar workspace rail — main operational domains only.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     sublabel: string,
     *     icon: string,
     *     route: string,
     *     active: list<string>,
     *     flyout?: list<array{label: string, route: string, active: list<string>}>,
     *     requires_loan_permission?: string|null,
     * }>
     */
    public static function agentWorkspaces(?User $user = null): array
    {
        return self::allAgentWorkspaces($user, sidebarOnly: true);
    }

    /**
     * Full workspace catalog — route matching, mobile drawer, flyouts.
     *
     * @return list<array<string, mixed>>
     */
    public static function allAgentWorkspaces(?User $user = null, bool $sidebarOnly = false): array
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
            static fn (array $workspace): bool => self::userCanSeeWorkspace($user, $workspace),
        ));

        if ($sidebarOnly) {
            $workspaces = self::filterWorkspacesForSidebar($workspaces);
        }

        return array_map(static function (array $workspace): array {
            unset($workspace['sidebar']);

            $key = (string) ($workspace['key'] ?? '');
            if ($key === '' || ! in_array($key, LoanWorkspaceTabs::implementedWorkspaceKeys(), true)) {
                return $workspace;
            }

            $entry = LoanWorkspaceTabs::defaultEntryTab($key);
            if ($entry === null) {
                return $workspace;
            }

            $workspace['route'] = (string) $entry['route'];

            return $workspace;
        }, $workspaces);
    }

    /**
     * @return list<string>
     */
    private static function agentWorkspaceSidebarOrder(): array
    {
        return [
            LoanWorkspaces::DASHBOARD,
            LoanWorkspaces::CLIENTS,
            LoanWorkspaces::LOANBOOK,
            LoanWorkspaces::COLLECTIONS,
            LoanWorkspaces::PAYMENTS,
            LoanWorkspaces::ACCOUNTING,
            LoanWorkspaces::FINANCIAL,
            LoanWorkspaces::HR,
            LoanWorkspaces::BRANCHES,
            LoanWorkspaces::ANALYTICS,
            LoanWorkspaces::SETTINGS,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function agentWorkspaceCatalog(): array
    {
        return [
            LoanWorkspaces::DASHBOARD => [
                'key' => LoanWorkspaces::DASHBOARD,
                'label' => 'Dashboard',
                'sublabel' => 'KPIs, alerts, today\'s queue',
                'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                'route' => 'loan.dashboard',
                'sidebar' => true,
                'active' => ['loan.dashboard', 'loan.dashboard.*'],
                'requires_loan_permission' => null,
            ],
            LoanWorkspaces::CLIENTS => [
                'key' => LoanWorkspaces::CLIENTS,
                'label' => 'Clients',
                'sublabel' => 'Directory, leads, interactions',
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                'route' => 'loan.clients.index',
                'sidebar' => true,
                'active' => ['loan.clients.*'],
                'requires_loan_permission' => 'clients.view',
                'flyout' => [
                    ['label' => 'Client directory', 'route' => 'loan.clients.index', 'active' => ['loan.clients.index', 'loan.clients.show', 'loan.clients.edit']],
                    ['label' => 'Add client', 'route' => 'loan.clients.create', 'active' => ['loan.clients.create']],
                    ['label' => 'Client leads', 'route' => 'loan.clients.leads', 'active' => ['loan.clients.leads', 'loan.clients.leads.*']],
                    ['label' => 'Create lead', 'route' => 'loan.clients.leads.create', 'active' => ['loan.clients.leads.create']],
                    ['label' => 'Transfer clients', 'route' => 'loan.clients.transfer', 'active' => ['loan.clients.transfer']],
                    ['label' => 'Interactions', 'route' => 'loan.clients.interactions', 'active' => ['loan.clients.interactions']],
                ],
            ],
            LoanWorkspaces::LOANBOOK => [
                'key' => LoanWorkspaces::LOANBOOK,
                'label' => 'Loan Book',
                'sublabel' => 'Applications, loans, disbursements',
                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'route' => 'loan.book.applications.index',
                'sidebar' => true,
                'active' => [
                    'loan.book.applications.*',
                    'loan.book.loans.*',
                    'loan.book.disbursements.*',
                    'loan.book.checkoff_loans',
                    'loan.book.app_loans_report',
                ],
                'requires_loan_permission' => 'loanbook.view',
                'flyout' => [
                    ['label' => 'Loan applications', 'route' => 'loan.book.applications.index', 'active' => ['loan.book.applications.index', 'loan.book.applications.show', 'loan.book.applications.edit']],
                    ['label' => 'Create application', 'route' => 'loan.book.applications.create', 'active' => ['loan.book.applications.create']],
                    ['label' => 'View loans', 'route' => 'loan.book.loans.index', 'active' => ['loan.book.loans.*']],
                    ['label' => 'Disbursements', 'route' => 'loan.book.disbursements.index', 'active' => ['loan.book.disbursements.*']],
                    ['label' => 'Checkoff loans', 'route' => 'loan.book.checkoff_loans', 'active' => ['loan.book.checkoff_loans']],
                    ['label' => 'App loans report', 'route' => 'loan.book.app_loans_report', 'active' => ['loan.book.app_loans_report']],
                ],
            ],
            LoanWorkspaces::COLLECTIONS => [
                'key' => LoanWorkspaces::COLLECTIONS,
                'label' => 'Collections',
                'sublabel' => 'Sheets, arrears, rates, agents',
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'route' => 'loan.book.collections_reports',
                'sidebar' => true,
                'active' => [
                    'loan.book.collection_sheet.*',
                    'loan.book.collection_mtd',
                    'loan.book.loan_arrears',
                    'loan.book.collection_agents.*',
                    'loan.book.collection_rates.*',
                    'loan.book.collection_reports',
                    'loan.book.collections_reports',
                ],
                'requires_loan_permission' => 'loanbook.view',
                'flyout' => [
                    ['label' => 'Collections overview', 'route' => 'loan.book.collections_reports', 'active' => ['loan.book.collections_reports']],
                    ['label' => 'Collection sheet', 'route' => 'loan.book.collection_sheet.index', 'active' => ['loan.book.collection_sheet.*']],
                    ['label' => 'Loan arrears', 'route' => 'loan.book.loan_arrears', 'active' => ['loan.book.loan_arrears']],
                    ['label' => 'Collection rates', 'route' => 'loan.book.collection_rates.index', 'active' => ['loan.book.collection_rates.*']],
                    ['label' => 'Collection agents', 'route' => 'loan.book.collection_agents.index', 'active' => ['loan.book.collection_agents.*']],
                    ['label' => 'Bulk messaging', 'route' => 'loan.communications.bulk', 'active' => ['loan.communications.bulk', 'loan.communications.bulk.*']],
                ],
            ],
            LoanWorkspaces::PAYMENTS => [
                'key' => LoanWorkspaces::PAYMENTS,
                'label' => 'Payments',
                'sublabel' => 'Pay-ins, receipts, reversals',
                'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                'route' => 'loan.payments.unposted',
                'sidebar' => true,
                'active' => ['loan.payments.*'],
                'requires_loan_permission' => 'payments.view',
                'flyout' => [
                    ['label' => 'Unposted payments', 'route' => 'loan.payments.unposted', 'active' => ['loan.payments.unposted', 'loan.payments.create', 'loan.payments.edit']],
                    ['label' => 'Processed payments', 'route' => 'loan.payments.processed', 'active' => ['loan.payments.processed']],
                    ['label' => 'Receipts', 'route' => 'loan.payments.receipts', 'active' => ['loan.payments.receipts']],
                    ['label' => 'Pay-in summary', 'route' => 'loan.payments.payin_summary', 'active' => ['loan.payments.payin_summary']],
                    ['label' => 'Payments report', 'route' => 'loan.payments.report', 'active' => ['loan.payments.report']],
                ],
            ],
            LoanWorkspaces::ACCOUNTING => [
                'key' => LoanWorkspaces::ACCOUNTING,
                'label' => 'Accounting',
                'sublabel' => 'Books, payroll, cashflow',
                'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'route' => 'loan.accounting.books',
                'sidebar' => true,
                'active' => ['loan.accounting.*'],
                'requires_loan_permission' => 'accounting.view',
                'flyout' => [
                    ['label' => 'Books of account', 'route' => 'loan.accounting.books', 'active' => ['loan.accounting.books', 'loan.accounting.books.*']],
                    ['label' => 'Employee payroll', 'route' => 'loan.accounting.payroll.hub', 'active' => ['loan.accounting.payroll.*']],
                    ['label' => 'Petty cashbook', 'route' => 'loan.accounting.petty.index', 'active' => ['loan.accounting.petty.*']],
                    ['label' => 'Cashflow', 'route' => 'loan.accounting.cashflow', 'active' => ['loan.accounting.cashflow']],
                ],
            ],
            LoanWorkspaces::FINANCIAL => [
                'key' => LoanWorkspaces::FINANCIAL,
                'label' => 'Financial',
                'sublabel' => 'M-Pesa, wallets, teller',
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'route' => 'loan.financial.mpesa_platform',
                'sidebar' => true,
                'active' => ['loan.financial.*'],
                'requires_loan_permission' => 'financial.view',
                'flyout' => [
                    ['label' => 'M-Pesa platform', 'route' => 'loan.financial.mpesa_platform', 'active' => ['loan.financial.mpesa_platform']],
                    ['label' => 'Client wallets', 'route' => 'loan.financial.account_balances', 'active' => ['loan.financial.account_balances']],
                    ['label' => 'Teller operations', 'route' => 'loan.financial.teller_operations', 'active' => ['loan.financial.teller_operations']],
                    ['label' => 'Investors list', 'route' => 'loan.financial.investors_list', 'active' => ['loan.financial.investors_list']],
                ],
            ],
            LoanWorkspaces::HR => [
                'key' => LoanWorkspaces::HR,
                'label' => 'Human Resources',
                'sublabel' => 'Employees, payroll, HR hub',
                'icon' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
                'route' => 'loan.hr.dashboard',
                'sidebar' => true,
                'active' => ['loan.hr.*', 'loan.employees.*'],
                'requires_loan_permission' => 'employees.view',
                'flyout' => [
                    ['label' => 'HR workspace', 'route' => 'loan.hr.dashboard', 'active' => ['loan.hr.*']],
                    ['label' => 'Add employee', 'route' => 'loan.employees.create', 'active' => ['loan.employees.create']],
                    ['label' => 'Employee directory', 'route' => 'loan.employees.index', 'active' => ['loan.employees.index']],
                ],
            ],
            LoanWorkspaces::BRANCHES => [
                'key' => LoanWorkspaces::BRANCHES,
                'label' => 'Branches',
                'sublabel' => 'Regions, branches, summaries',
                'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                'route' => 'loan.branches.index',
                'sidebar' => true,
                'active' => ['loan.regions.*', 'loan.branches.*'],
                'requires_loan_permission' => 'branches.view',
                'flyout' => [
                    ['label' => 'View branches', 'route' => 'loan.branches.index', 'active' => ['loan.branches.index', 'loan.branches.edit']],
                    ['label' => 'View regions', 'route' => 'loan.regions.index', 'active' => ['loan.regions.index', 'loan.regions.edit']],
                    ['label' => 'Loan summary', 'route' => 'loan.branches.loan_summary', 'active' => ['loan.branches.loan_summary']],
                ],
            ],
            LoanWorkspaces::ANALYTICS => [
                'key' => LoanWorkspaces::ANALYTICS,
                'label' => 'Analytics',
                'sublabel' => 'Targets, performance, loan sizes',
                'icon' => 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z',
                'route' => 'loan.analytics.performance',
                'sidebar' => true,
                'active' => ['loan.analytics.*'],
                'requires_loan_permission' => 'analytics.view',
                'flyout' => [
                    ['label' => 'Business performance', 'route' => 'loan.analytics.performance', 'active' => ['loan.analytics.performance*']],
                    ['label' => 'Loan sizes', 'route' => 'loan.analytics.loan_sizes', 'active' => ['loan.analytics.loan_sizes*']],
                    ['label' => 'Targets & accruals', 'route' => 'loan.analytics.targets', 'active' => ['loan.analytics.targets*']],
                ],
            ],
            LoanWorkspaces::SETTINGS => [
                'key' => LoanWorkspaces::SETTINGS,
                'label' => 'Settings',
                'sublabel' => 'Setup, tickets, access logs',
                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                'route' => 'loan.system.setup',
                'sidebar' => true,
                'active' => ['loan.system.*', 'loan.account.*', 'profile.*'],
                'requires_loan_permission' => 'system.help.view',
                'flyout' => [
                    ['label' => 'System setup', 'route' => 'loan.system.setup', 'active' => ['loan.system.setup', 'loan.system.setup.*']],
                    ['label' => 'Support tickets', 'route' => 'loan.system.tickets.index', 'active' => ['loan.system.tickets.*']],
                    ['label' => 'My account', 'route' => 'loan.account.show', 'active' => ['loan.account.*']],
                    ['label' => 'Access logs', 'route' => 'loan.system.access_logs.index', 'active' => ['loan.system.access_logs.index']],
                ],
            ],
            LoanWorkspaces::COMMUNICATIONS => [
                'key' => LoanWorkspaces::COMMUNICATIONS,
                'label' => 'Communications',
                'sublabel' => 'SMS, email, templates',
                'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'route' => 'loan.communications.index',
                'sidebar' => false,
                'active' => ['loan.communications.*', 'loan.bulksms.*'],
                'requires_loan_permission' => 'bulksms.view',
                'flyout' => [
                    ['label' => 'Communications hub', 'route' => 'loan.communications.index', 'active' => ['loan.communications.index']],
                    ['label' => 'Bulk messaging', 'route' => 'loan.communications.bulk', 'active' => ['loan.communications.bulk*']],
                    ['label' => 'Templates', 'route' => 'loan.communications.templates', 'active' => ['loan.communications.templates*']],
                    ['label' => 'Conversations', 'route' => 'loan.communications.conversations', 'active' => ['loan.communications.conversations*']],
                ],
            ],
            LoanWorkspaces::ASSETS => [
                'key' => LoanWorkspaces::ASSETS,
                'label' => 'Asset Financing',
                'sublabel' => 'Stock, categories, units',
                'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                'route' => 'loan.assets.items.index',
                'sidebar' => false,
                'active' => ['loan.assets.*'],
                'requires_loan_permission' => 'loanbook.view',
                'flyout' => [
                    ['label' => 'Asset list', 'route' => 'loan.assets.items.index', 'active' => ['loan.assets.items.*']],
                    ['label' => 'Asset categories', 'route' => 'loan.assets.categories.index', 'active' => ['loan.assets.categories.*']],
                    ['label' => 'Measurement units', 'route' => 'loan.assets.units.index', 'active' => ['loan.assets.units.*']],
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $workspaces
     * @return list<array<string, mixed>>
     */
    private static function filterWorkspacesForSidebar(array $workspaces): array
    {
        return array_values(array_filter(
            $workspaces,
            static fn (array $workspace): bool => ($workspace['sidebar'] ?? true) === true,
        ));
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private static function userCanSeeWorkspace(?User $user, array $workspace): bool
    {
        if ($user && ($user->loanPermissionKeys() !== [])) {
            $perm = $workspace['requires_loan_permission'] ?? null;
            if ($perm === null || $perm === '') {
                return true;
            }

            return $user->hasLoanPermission($perm);
        }

        if (self::seesFullLoanSidebar($user)) {
            return true;
        }

        $r = self::normalizeLoanRole($user);
        $key = (string) ($workspace['key'] ?? '');

        $allowedKeys = match ($r) {
            'accountant' => [
                LoanWorkspaces::DASHBOARD,
                LoanWorkspaces::ACCOUNTING,
                LoanWorkspaces::FINANCIAL,
                LoanWorkspaces::SETTINGS,
            ],
            'officer', 'user' => [
                LoanWorkspaces::DASHBOARD,
                LoanWorkspaces::CLIENTS,
                LoanWorkspaces::LOANBOOK,
                LoanWorkspaces::COLLECTIONS,
                LoanWorkspaces::PAYMENTS,
                LoanWorkspaces::COMMUNICATIONS,
                LoanWorkspaces::SETTINGS,
            ],
            'applicant' => [
                LoanWorkspaces::DASHBOARD,
                LoanWorkspaces::SETTINGS,
            ],
            default => LoanWorkspaces::all(),
        };

        if (! in_array($key, $allowedKeys, true)) {
            return false;
        }

        if ($key === LoanWorkspaces::SETTINGS && in_array($r, ['accountant', 'officer', 'user', 'applicant'], true)) {
            return self::settingsWorkspaceVisibleForRole($user, $r);
        }

        return true;
    }

    private static function settingsWorkspaceVisibleForRole(?User $user, string $role): bool
    {
        return in_array($role, ['accountant', 'officer', 'user', 'applicant', 'admin', 'manager'], true);
    }

    public static function workspaceHref(array $workspace): string
    {
        $route = (string) ($workspace['route'] ?? '');
        if ($route !== '' && Route::has($route)) {
            return route($route);
        }

        $key = (string) ($workspace['key'] ?? '');
        $direct = $key !== '' ? LoanWorkspaceTabs::defaultEntryUrl($key) : null;

        return $direct ?? '#';
    }

    /**
     * @param  array{label: string, route: string, active?: list<string>}  $link
     */
    public static function flyoutHref(array $link): string
    {
        $route = (string) ($link['route'] ?? '');

        return $route !== '' && Route::has($route) ? route($route) : '#';
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return list<array{label: string, route: string, active: list<string>}>
     */
    public static function flyoutLinksForUser(array $workspace, ?User $user = null): array
    {
        $links = $workspace['flyout'] ?? [];
        if (! is_array($links)) {
            return [];
        }

        if (($workspace['key'] ?? '') !== LoanWorkspaces::SETTINGS || self::canOpenLoanSystemSetup($user)) {
            return array_values($links);
        }

        $blockedRoutes = ['loan.system.setup', 'loan.system.access_logs.index'];

        return array_values(array_filter(
            $links,
            static fn (array $link): bool => ! in_array((string) ($link['route'] ?? ''), $blockedRoutes, true),
        ));
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
            if (self::routeIsActive($routeName, $workspace['active'] ?? [])) {
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

    private static function routeNameMatches(string $current, string $pattern): bool
    {
        if ($pattern === $current) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return $current === $prefix || str_starts_with($current, $prefix.'.');
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return str_starts_with($current, $prefix);
        }

        return false;
    }

    public static function normalizeLoanRole(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return strtolower(trim((string) ($user->effectiveLoanRole() ?? '')));
    }

    public static function seesFullLoanSidebar(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (($user->is_super_admin ?? false) === true) {
            return true;
        }

        $r = self::normalizeLoanRole($user);

        // Legacy installs: empty role keeps the previous “see everything” behaviour.
        return $r === '' || $r === 'admin' || $r === 'manager';
    }

    /**
     * @param  array<string, array<string, mixed>>  $menu
     * @return array<string, array<string, mixed>>
     */
    public static function filterSidebarMenu(?User $user, array $menu): array
    {
        if ($user && ($user->loanPermissionKeys() !== [])) {
            $permissionGroupMap = [
                'Human Resources' => 'employees.view',
                'Employees' => 'employees.view',
                'Accounting' => 'accounting.view',
                'Branches & Regions' => 'branches.view',
                'Business Analytics' => 'analytics.view',
                'Clients' => 'clients.view',
                'LoanBook' => 'loanbook.view',
                'Payments' => 'payments.view',
                'Bulk SMS' => 'bulksms.view',
                'Financial' => 'financial.view',
                'My Account' => 'my_account.view',
                'System & Help' => 'system.help.view',
            ];

            $out = [];
            foreach ($menu as $name => $data) {
                $perm = $permissionGroupMap[$name] ?? null;
                if ($perm === null || $user->hasLoanPermission($perm)) {
                    $out[$name] = $data;
                }
            }

            return $out;
        }

        if (self::seesFullLoanSidebar($user)) {
            return $menu;
        }

        $r = self::normalizeLoanRole($user);

        $allowedGroups = match ($r) {
            'accountant' => ['Accounting', 'Financial', 'My Account', 'System & Help'],
            'officer', 'user' => ['Clients', 'LoanBook', 'Payments', 'Bulk SMS', 'My Account', 'System & Help'],
            'applicant' => ['My Account', 'System & Help'],
            default => array_keys($menu),
        };

        $out = [];
        foreach ($menu as $name => $data) {
            if (! in_array($name, $allowedGroups, true)) {
                continue;
            }
            $out[$name] = self::filterSidebarGroupItems($name, $data, $r);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function filterSidebarGroupItems(string $groupName, array $data, string $role): array
    {
        if ($groupName !== 'System & Help') {
            return $data;
        }

        if (! isset($data['items']) || ! is_array($data['items'])) {
            return $data;
        }

        $hideAdminRoutes = in_array($role, ['accountant', 'officer', 'user', 'applicant'], true);
        if (! $hideAdminRoutes) {
            return $data;
        }

        $blockedRoutes = ['loan.system.setup', 'loan.system.access_logs.index'];
        $data['items'] = array_values(array_filter(
            $data['items'],
            static function ($item) use ($blockedRoutes) {
                $route = $item['route'] ?? '';

                return ! in_array($route, $blockedRoutes, true);
            }
        ));

        return $data;
    }

    /**
     * Header quick links — full operational jump bar (attached to topbar, not page body).
     *
     * @return list<array{route: string, label: string, nav: string, active: bool}>
     */
    public static function quickLinksForUser(?User $user): array
    {
        $templates = [
            ['route' => 'loan.dashboard', 'label' => 'Dashboard', 'nav' => 'loan.dashboard'],
            ['route' => 'loan.clients.index', 'label' => 'Clients', 'nav' => 'loan.clients.*'],
            ['route' => 'loan.book.applications.index', 'label' => 'Applications', 'nav' => 'loan.book.applications.*'],
            ['route' => 'loan.book.loans.index', 'label' => 'Loans', 'nav' => 'loan.book.loans.*'],
            ['route' => 'loan.book.disbursements.index', 'label' => 'Disbursements', 'nav' => 'loan.book.disbursements.*'],
            ['route' => 'loan.payments.processed', 'label' => 'Payments', 'nav' => 'loan.payments.processed*'],
            ['route' => 'loan.payments.report', 'label' => 'Pay-in report', 'nav' => 'loan.payments.report*'],
            ['route' => 'loan.payments.unposted', 'label' => 'Unposted', 'nav' => 'loan.payments.unposted*'],
            ['route' => 'loan.accounting.books', 'label' => 'Accounting', 'nav' => 'loan.accounting.*'],
        ];

        if ($user && ($user->loanPermissionKeys() !== [])) {
            $permissionRouteMap = [
                'loan.dashboard' => 'dashboard.view',
                'loan.clients.index' => 'clients.view',
                'loan.book.applications.index' => 'loanbook.view',
                'loan.book.loans.index' => 'loanbook.view',
                'loan.book.disbursements.index' => 'loanbook.view',
                'loan.payments.processed' => 'payments.view',
                'loan.payments.report' => 'payments.view',
                'loan.payments.unposted' => 'payments.view',
                'loan.accounting.books' => 'accounting.view',
            ];
            $filtered = array_values(array_filter($templates, static function (array $t) use ($user, $permissionRouteMap): bool {
                $perm = $permissionRouteMap[$t['route']] ?? null;

                return $perm === null || $user->hasLoanPermission($perm);
            }));

            return self::decorateQuickLinksActive($filtered);
        }

        if (! $user || ($user->is_super_admin ?? false) === true) {
            return self::decorateQuickLinksActive($templates);
        }

        $r = self::normalizeLoanRole($user);
        if ($r === '' || $r === 'admin' || $r === 'manager') {
            return self::decorateQuickLinksActive($templates);
        }

        $routes = match ($r) {
            'accountant' => [
                'loan.dashboard',
                'loan.payments.processed',
                'loan.payments.report',
                'loan.payments.unposted',
                'loan.accounting.books',
            ],
            'officer', 'user' => [
                'loan.dashboard',
                'loan.clients.index',
                'loan.book.applications.index',
                'loan.book.loans.index',
                'loan.book.disbursements.index',
                'loan.payments.processed',
                'loan.payments.report',
                'loan.payments.unposted',
            ],
            'applicant' => [
                'loan.dashboard',
                'loan.book.applications.index',
            ],
            default => array_column($templates, 'route'),
        };

        $filtered = array_values(array_filter(
            $templates,
            static fn (array $t): bool => in_array($t['route'], $routes, true)
        ));

        return self::decorateQuickLinksActive($filtered);
    }

    /**
     * @param  list<array{route: string, label: string, nav?: string}>  $links
     * @return list<array{route: string, label: string, nav: string, active: bool}>
     */
    private static function decorateQuickLinksActive(array $links): array
    {
        return array_map(static function (array $link): array {
            $nav = (string) ($link['nav'] ?? $link['route']);
            $active = request()->routeIs($nav);

            return [...$link, 'nav' => $nav, 'active' => $active];
        }, $links);
    }

    public static function canOpenLoanSystemSetup(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (($user->is_super_admin ?? false) === true) {
            return true;
        }

        $r = self::normalizeLoanRole($user);

        return in_array($r, ['admin', 'manager'], true);
    }

    /**
     * Resolve the active loan sidebar group for the current route.
     *
     * - No argument: returns the active group label, or null.
     * - With argument: returns whether that group is active.
     */
    public static function activeSidebarGroupName(?string $groupName = null): bool|string|null
    {
        if ($groupName !== null && $groupName !== '') {
            return self::isSidebarGroupActive($groupName);
        }

        foreach (self::sidebarGroupNames() as $name) {
            if (self::isSidebarGroupActive($name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function sidebarGroupNames(): array
    {
        return [
            'Human Resources',
            'Accounting',
            'Branches & Regions',
            'Business Analytics',
            'Clients',
            'LoanBook',
            'Payments',
            'Bulk SMS',
            'Financial',
            'Asset Financing',
            'My Account',
            'System & Help',
        ];
    }

    private static function isSidebarGroupActive(string $groupName): bool
    {
        return match ($groupName) {
            'Human Resources' => request()->routeIs('loan.hr.*', 'loan.employees.*'),
            'Accounting' => request()->routeIs('loan.accounting.*'),
            'Branches & Regions' => request()->routeIs('loan.regions.*', 'loan.branches.*'),
            'Business Analytics' => request()->routeIs('loan.analytics.*'),
            'Clients' => request()->routeIs('loan.clients.*'),
            'LoanBook' => request()->routeIs('loan.book.*'),
            'Payments' => request()->routeIs('loan.payments.*'),
            'Bulk SMS' => request()->routeIs('loan.communications.*', 'loan.bulksms.*'),
            'Financial' => request()->routeIs('loan.financial.*'),
            'Asset Financing' => request()->routeIs('loan.assets.*'),
            'My Account' => request()->routeIs('loan.account.*', 'profile.*'),
            'System & Help' => request()->routeIs('loan.system.*'),
            default => false,
        };
    }
}
