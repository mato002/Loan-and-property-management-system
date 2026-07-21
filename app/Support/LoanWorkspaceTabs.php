<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class LoanWorkspaceTabs
{
    /**
     * @return list<string>
     */
    public static function implementedWorkspaceKeys(): array
    {
        return [
            LoanWorkspaces::CLIENTS,
            LoanWorkspaces::LOANBOOK,
            LoanWorkspaces::COLLECTIONS,
            LoanWorkspaces::PAYMENTS,
            LoanWorkspaces::COMMUNICATIONS,
            LoanWorkspaces::ACCOUNTING,
            LoanWorkspaces::FINANCIAL,
            LoanWorkspaces::HR,
            LoanWorkspaces::BRANCHES,
            LoanWorkspaces::ANALYTICS,
            LoanWorkspaces::ASSETS,
            LoanWorkspaces::SETTINGS,
        ];
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
            if ($route !== '' && Route::has($route)) {
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
     * @param  array{route: string, route_params?: array<string, mixed>, query?: array<string, mixed>}  $tab
     */
    public static function tabUrl(array $tab): string
    {
        $routeName = (string) $tab['route'];
        if ($routeName === '' || ! Route::has($routeName)) {
            return '#';
        }

        $url = route($routeName, $tab['route_params'] ?? []);
        $query = $tab['query'] ?? [];
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }

    public static function resolveWorkspaceKey(?string $routeName): ?string
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return null;
        }

        $workspace = LoanNavigation::workspaceForRoute($routeName);

        $key = is_array($workspace) ? (string) ($workspace['key'] ?? '') : '';

        return $key !== '' && in_array($key, self::implementedWorkspaceKeys(), true) ? $key : null;
    }

    public static function shouldShow(?string $routeName): bool
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return false;
        }

        if (in_array($routeName, self::shellExcludedRoutes(), true)) {
            return false;
        }

        return self::resolveWorkspaceKey($routeName) !== null;
    }

    /**
     * Routes that render their own in-page workspace chrome.
     *
     * @return list<string>
     */
    public static function shellExcludedRoutes(): array
    {
        return [
            'loan.book.collections_reports',
        ];
    }

    public static function tabIsActive(array $tab, ?string $routeName = null): bool
    {
        $routeName = trim((string) ($routeName ?? Route::currentRouteName() ?? ''));
        if ($routeName === '') {
            return false;
        }

        if (! LoanNavigation::routeIsActive($routeName, $tab['active'] ?? [])) {
            return false;
        }

        if (! empty($tab['query']) && is_array($tab['query'])) {
            foreach ($tab['query'] as $key => $expected) {
                if ((string) request()->query($key, '') !== (string) $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function workspaceLabel(string $workspaceKey): string
    {
        foreach (LoanNavigation::allAgentWorkspaces() as $workspace) {
            if (($workspace['key'] ?? '') === $workspaceKey) {
                return (string) ($workspace['label'] ?? ucfirst($workspaceKey));
            }
        }

        return ucfirst(str_replace('_', ' ', $workspaceKey));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function navigableTabsFor(string $workspaceKey): array
    {
        return array_values(array_filter(
            self::tabsFor($workspaceKey),
            static function (array $tab): bool {
                $route = trim((string) ($tab['route'] ?? ''));

                return $route !== '' && Route::has($route);
            },
        ));
    }

    /**
     * @return array{all: list<array{key: string, label: string, route?: string|null}>}
     */
    public static function partitionedTabsForUser(?User $user, string $workspace): array
    {
        return ['all' => self::tabsFor($workspace)];
    }

    /**
     * @return list<array{label: string, href: string, keywords?: list<string>}>
     */
    public static function searchCommandsForUser(?User $user, string $workspace): array
    {
        $commands = [];

        foreach (self::tabsFor($workspace) as $tab) {
            $route = (string) ($tab['route'] ?? '');
            if ($route === '' || ! Route::has($route)) {
                continue;
            }

            $commands[] = [
                'label' => (string) $tab['label'],
                'href' => route($route),
                'keywords' => [(string) $tab['key'], strtolower((string) $tab['label'])],
            ];
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    public static function focusModesForWorkspace(string $workspace): array
    {
        return match ($workspace) {
            LoanWorkspaces::COLLECTIONS => ['default'],
            default => ['default'],
        };
    }

    /**
     * @return list<array{key: string, label: string, route?: string|null, active?: list<string>}>
     */
    public static function tabsFor(string $workspaceKey): array
    {
        return match ($workspaceKey) {
            LoanWorkspaces::CLIENTS => [
                ['key' => 'directory', 'label' => 'Client directory', 'route' => 'loan.clients.index', 'active' => ['loan.clients.index', 'loan.clients.show', 'loan.clients.edit', 'loan.clients.update', 'loan.clients.destroy']],
                ['key' => 'add', 'label' => 'Add client', 'route' => 'loan.clients.create', 'active' => ['loan.clients.create', 'loan.clients.store']],
                ['key' => 'leads', 'label' => 'Client leads', 'route' => 'loan.clients.leads', 'active' => ['loan.clients.leads', 'loan.clients.leads.*']],
                ['key' => 'create_lead', 'label' => 'Create lead', 'route' => 'loan.clients.leads.create', 'active' => ['loan.clients.leads.create']],
                ['key' => 'transfer', 'label' => 'Transfer clients', 'route' => 'loan.clients.transfer', 'active' => ['loan.clients.transfer']],
                ['key' => 'groups', 'label' => 'Default groups', 'route' => 'loan.clients.default_groups', 'active' => ['loan.clients.default_groups']],
                ['key' => 'interactions', 'label' => 'Interactions', 'route' => 'loan.clients.interactions', 'active' => ['loan.clients.interactions']],
            ],
            LoanWorkspaces::LOANBOOK => [
                ['key' => 'applications', 'label' => 'Applications', 'route' => 'loan.book.applications.index', 'active' => ['loan.book.applications.*']],
                ['key' => 'create_application', 'label' => 'Create application', 'route' => 'loan.book.applications.create', 'active' => ['loan.book.applications.create']],
                ['key' => 'loans', 'label' => 'Loans', 'route' => 'loan.book.loans.index', 'active' => ['loan.book.loans.*']],
                ['key' => 'disbursements', 'label' => 'Disbursements', 'route' => 'loan.book.disbursements.index', 'active' => ['loan.book.disbursements.*']],
                ['key' => 'checkoff', 'label' => 'Checkoff loans', 'route' => 'loan.book.checkoff_loans', 'active' => ['loan.book.checkoff_loans']],
                ['key' => 'app_report', 'label' => 'App loans report', 'route' => 'loan.book.app_loans_report', 'active' => ['loan.book.app_loans_report']],
            ],
            LoanWorkspaces::COLLECTIONS => self::collectionsTabs(),
            LoanWorkspaces::PAYMENTS => [
                ['key' => 'unposted', 'label' => 'Unposted', 'route' => 'loan.payments.unposted', 'active' => ['loan.payments.unposted', 'loan.payments.create', 'loan.payments.edit', 'loan.payments.post']],
                ['key' => 'processed', 'label' => 'Processed', 'route' => 'loan.payments.processed', 'active' => ['loan.payments.processed', 'loan.payments.processed.print']],
                ['key' => 'prepayments', 'label' => 'Prepayments', 'route' => 'loan.payments.prepayments', 'active' => ['loan.payments.prepayments']],
                ['key' => 'overpayments', 'label' => 'Overpayments', 'route' => 'loan.payments.overpayments', 'active' => ['loan.payments.overpayments']],
                ['key' => 'merged', 'label' => 'Merged', 'route' => 'loan.payments.merged', 'active' => ['loan.payments.merged', 'loan.payments.merge', 'loan.payments.merge.store']],
                ['key' => 'reversals', 'label' => 'C2B reversals', 'route' => 'loan.payments.c2b_reversals', 'active' => ['loan.payments.c2b_reversals', 'loan.payments.reversal.*']],
                ['key' => 'receipts', 'label' => 'Receipts', 'route' => 'loan.payments.receipts', 'active' => ['loan.payments.receipts']],
                ['key' => 'summary', 'label' => 'Pay-in summary', 'route' => 'loan.payments.payin_summary', 'active' => ['loan.payments.payin_summary']],
                ['key' => 'report', 'label' => 'Payments report', 'route' => 'loan.payments.report', 'active' => ['loan.payments.report', 'loan.payments.report.export']],
                ['key' => 'validate', 'label' => 'Validate payment', 'route' => 'loan.payments.validate', 'active' => ['loan.payments.validate', 'loan.payments.validate.store']],
            ],
            LoanWorkspaces::COMMUNICATIONS => [
                ['key' => 'hub', 'label' => 'Hub', 'route' => 'loan.communications.index', 'active' => ['loan.communications.index']],
                ['key' => 'messages', 'label' => 'SMS / email', 'route' => 'loan.communications.messages', 'active' => ['loan.communications.messages*']],
                ['key' => 'bulk', 'label' => 'Bulk messaging', 'route' => 'loan.communications.bulk', 'active' => ['loan.communications.bulk*']],
                ['key' => 'templates', 'label' => 'Templates', 'route' => 'loan.communications.templates', 'active' => ['loan.communications.templates*']],
                ['key' => 'payment_templates', 'label' => 'Payment templates', 'route' => 'loan.communications.payment_templates', 'active' => ['loan.communications.payment_templates*']],
                ['key' => 'provider', 'label' => 'SMS wallet', 'route' => 'loan.communications.sms_provider', 'active' => ['loan.communications.sms_provider']],
                ['key' => 'conversations', 'label' => 'Conversations', 'route' => 'loan.communications.conversations', 'active' => ['loan.communications.conversations*']],
                ['key' => 'schedules', 'label' => 'SMS schedules', 'route' => 'loan.bulksms.schedules', 'active' => ['loan.bulksms.schedules*']],
            ],
            LoanWorkspaces::ACCOUNTING => [
                ['key' => 'books', 'label' => 'Books of account', 'route' => 'loan.accounting.books', 'active' => ['loan.accounting.books', 'loan.accounting.books.*', 'loan.accounting.journal.*', 'loan.accounting.ledger', 'loan.accounting.reports.*']],
                ['key' => 'payroll', 'label' => 'Payroll', 'route' => 'loan.accounting.payroll.hub', 'active' => ['loan.accounting.payroll.*']],
                ['key' => 'requisitions', 'label' => 'Requisitions', 'route' => 'loan.accounting.requisitions.index', 'active' => ['loan.accounting.requisitions.*']],
                ['key' => 'utilities', 'label' => 'Utility payments', 'route' => 'loan.accounting.utilities.index', 'active' => ['loan.accounting.utilities.*']],
                ['key' => 'petty', 'label' => 'Petty cashbook', 'route' => 'loan.accounting.petty.index', 'active' => ['loan.accounting.petty.*']],
                ['key' => 'advances', 'label' => 'Salary advances', 'route' => 'loan.accounting.advances.index', 'active' => ['loan.accounting.advances.*']],
                ['key' => 'expenses', 'label' => 'Expense summary', 'route' => 'loan.accounting.expense_summary', 'active' => ['loan.accounting.expense_summary']],
                ['key' => 'cashflow', 'label' => 'Cashflow', 'route' => 'loan.accounting.cashflow', 'active' => ['loan.accounting.cashflow']],
            ],
            LoanWorkspaces::FINANCIAL => [
                ['key' => 'mpesa', 'label' => 'M-Pesa platform', 'route' => 'loan.financial.mpesa_platform', 'active' => ['loan.financial.mpesa_platform']],
                ['key' => 'payouts', 'label' => 'M-Pesa payouts', 'route' => 'loan.financial.mpesa_payouts', 'active' => ['loan.financial.mpesa_payouts']],
                ['key' => 'wallets', 'label' => 'Client wallets', 'route' => 'loan.financial.account_balances', 'active' => ['loan.financial.account_balances']],
                ['key' => 'control', 'label' => 'Control accounts', 'route' => 'loan.financial.control_accounts', 'active' => ['loan.financial.control_accounts']],
                ['key' => 'teller', 'label' => 'Teller operations', 'route' => 'loan.financial.teller_operations', 'active' => ['loan.financial.teller_operations']],
                ['key' => 'packages', 'label' => 'Investment packages', 'route' => 'loan.financial.investment_packages', 'active' => ['loan.financial.investment_packages']],
                ['key' => 'investors', 'label' => 'Investors list', 'route' => 'loan.financial.investors_list', 'active' => ['loan.financial.investors_list']],
                ['key' => 'investor_reports', 'label' => 'Investors reports', 'route' => 'loan.financial.investors_reports', 'active' => ['loan.financial.investors_reports']],
            ],
            LoanWorkspaces::HR => [
                ['key' => 'dashboard', 'label' => 'HR workspace', 'route' => 'loan.hr.dashboard', 'active' => ['loan.hr.*']],
                ['key' => 'add', 'label' => 'Add employee', 'route' => 'loan.employees.create', 'active' => ['loan.employees.create']],
                ['key' => 'directory', 'label' => 'Employee directory', 'route' => 'loan.employees.index', 'active' => ['loan.employees.index', 'loan.employees.*']],
            ],
            LoanWorkspaces::BRANCHES => [
                ['key' => 'branches', 'label' => 'Branches', 'route' => 'loan.branches.index', 'active' => ['loan.branches.index', 'loan.branches.edit', 'loan.branches.update', 'loan.branches.destroy']],
                ['key' => 'add_branch', 'label' => 'Add branch', 'route' => 'loan.branches.create', 'active' => ['loan.branches.create']],
                ['key' => 'regions', 'label' => 'Regions', 'route' => 'loan.regions.index', 'active' => ['loan.regions.index', 'loan.regions.edit', 'loan.regions.update', 'loan.regions.destroy']],
                ['key' => 'add_region', 'label' => 'Create region', 'route' => 'loan.regions.create', 'active' => ['loan.regions.create']],
                ['key' => 'summary', 'label' => 'Loan summary', 'route' => 'loan.branches.loan_summary', 'active' => ['loan.branches.loan_summary']],
            ],
            LoanWorkspaces::ANALYTICS => [
                ['key' => 'sizes', 'label' => 'Loan sizes', 'route' => 'loan.analytics.loan_sizes', 'active' => ['loan.analytics.loan_sizes*']],
                ['key' => 'targets', 'label' => 'Targets & accruals', 'route' => 'loan.analytics.targets', 'active' => ['loan.analytics.targets*']],
                ['key' => 'performance', 'label' => 'Business performance', 'route' => 'loan.analytics.performance', 'active' => ['loan.analytics.performance*']],
            ],
            LoanWorkspaces::ASSETS => [
                ['key' => 'items', 'label' => 'Asset list', 'route' => 'loan.assets.items.index', 'active' => ['loan.assets.items.index', 'loan.assets.items.edit']],
                ['key' => 'add', 'label' => 'Add asset', 'route' => 'loan.assets.items.create', 'active' => ['loan.assets.items.create']],
                ['key' => 'categories', 'label' => 'Categories', 'route' => 'loan.assets.categories.index', 'active' => ['loan.assets.categories.*']],
                ['key' => 'units', 'label' => 'Measurement units', 'route' => 'loan.assets.units.index', 'active' => ['loan.assets.units.*']],
            ],
            LoanWorkspaces::SETTINGS => [
                ['key' => 'setup', 'label' => 'System setup', 'route' => 'loan.system.setup', 'active' => ['loan.system.setup', 'loan.system.setup.*', 'loan.system.form_setup.*']],
                ['key' => 'tickets', 'label' => 'Support tickets', 'route' => 'loan.system.tickets.index', 'active' => ['loan.system.tickets.*']],
                ['key' => 'create_ticket', 'label' => 'Create ticket', 'route' => 'loan.system.tickets.create', 'active' => ['loan.system.tickets.create']],
                ['key' => 'access_logs', 'label' => 'Access logs', 'route' => 'loan.system.access_logs.index', 'active' => ['loan.system.access_logs.index']],
                ['key' => 'account', 'label' => 'My account', 'route' => 'loan.account.show', 'active' => ['loan.account.*', 'profile.*']],
            ],
            default => [],
        };
    }

    /**
     * @return list<array{key: string, label: string, route?: string|null}>
     */
    private static function collectionsTabs(): array
    {
        return [
            ['key' => 'overview', 'label' => 'Overview', 'route' => 'loan.book.collections_reports', 'active' => ['loan.book.collections_reports']],
            ['key' => 'collection_sheet', 'label' => 'Collection sheet', 'route' => 'loan.book.collection_sheet.index', 'active' => ['loan.book.collection_sheet.*']],
            ['key' => 'mtd', 'label' => 'Collection MTD', 'route' => 'loan.book.collection_mtd', 'active' => ['loan.book.collection_mtd']],
            ['key' => 'arrears', 'label' => 'Loan arrears', 'route' => 'loan.book.loan_arrears', 'active' => ['loan.book.loan_arrears']],
            ['key' => 'rates', 'label' => 'Collection rates', 'route' => 'loan.book.collection_rates.index', 'active' => ['loan.book.collection_rates.*']],
            ['key' => 'reports', 'label' => 'Collection reports', 'route' => 'loan.book.collection_reports', 'active' => ['loan.book.collection_reports']],
            ['key' => 'agents', 'label' => 'Collection agents', 'route' => 'loan.book.collection_agents.index', 'active' => ['loan.book.collection_agents.*']],
            ['key' => 'cashflow', 'label' => 'Cashflow', 'route' => Route::has('loan.accounting.cashflow') ? 'loan.accounting.cashflow' : null, 'active' => ['loan.accounting.cashflow']],
            ['key' => 'lending_capacity', 'label' => 'Lending capacity', 'route' => 'loan.book.disbursements.index', 'active' => ['loan.book.disbursements.index']],
        ];
    }
}
