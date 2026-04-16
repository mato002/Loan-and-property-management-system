<?php

namespace App\Support;

use App\Models\User;

/**
 * Loan module sidebar / quick-nav visibility by {@see User::$loan_role}.
 * Aligns navigation with route middleware (e.g. accounting/financial require accountant|admin|manager).
 */
final class LoanNavigation
{
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
     * Top bar quick links (large screens + mobile strip).
     *
     * @return list<array{route: string, label: string, active: bool}>
     */
    public static function quickLinksForUser(?User $user): array
    {
        $templates = [
            ['route' => 'loan.dashboard', 'label' => 'Dashboard'],
            ['route' => 'loan.book.applications.index', 'label' => 'Applications'],
            ['route' => 'loan.payments.unposted', 'label' => 'Pay-ins'],
            ['route' => 'loan.accounting.books', 'label' => 'Books'],
            ['route' => 'loan.clients.index', 'label' => 'Clients'],
        ];

        if ($user && ($user->loanPermissionKeys() !== [])) {
            $permissionRouteMap = [
                'loan.dashboard' => 'dashboard.view',
                'loan.book.applications.index' => 'loanbook.view',
                'loan.payments.unposted' => 'payments.view',
                'loan.accounting.books' => 'accounting.view',
                'loan.clients.index' => 'clients.view',
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
            'accountant' => ['loan.dashboard', 'loan.accounting.books', 'loan.payments.unposted', 'loan.clients.index'],
            'officer', 'user' => ['loan.dashboard', 'loan.book.applications.index', 'loan.payments.unposted', 'loan.clients.index'],
            'applicant' => ['loan.dashboard', 'loan.book.applications.index'],
            default => array_column($templates, 'route'),
        };

        $filtered = array_values(array_filter(
            $templates,
            static fn (array $t): bool => in_array($t['route'], $routes, true)
        ));

        return self::decorateQuickLinksActive($filtered);
    }

    /**
     * @param  list<array{route: string, label: string}>  $links
     * @return list<array{route: string, label: string, active: bool}>
     */
    private static function decorateQuickLinksActive(array $links): array
    {
        return array_map(static function (array $link): array {
            $route = $link['route'];
            $active = match ($route) {
                'loan.dashboard' => request()->routeIs('loan.dashboard'),
                'loan.book.applications.index' => request()->routeIs('loan.book.applications*'),
                'loan.payments.unposted' => request()->routeIs('loan.payments*'),
                'loan.accounting.books' => request()->routeIs('loan.accounting*'),
                'loan.clients.index' => request()->routeIs('loan.clients*'),
                default => false,
            };

            return [...$link, 'active' => $active];
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
}
