<?php

namespace App\Support\Property;

use App\Models\User;

/**
 * Maps property workspace routes to the permission that reveals them.
 * An empty list means every signed-in property user may open the route.
 * Any one matching permission is enough.
 */
final class PropertyModuleAccess
{
    /**
     * @var array<string, list<string>>
     */
    private const WORKSPACES = [
        'portfolio' => ['properties.manage', 'property.archive.view'],
        'tenants' => ['tenants.manage', 'leases.manage'],
        'collections' => ['invoices.manage', 'payments.record', 'payments.settle', 'revenue.utilities.manage', 'utilities.readings.capture', 'revenue.penalties.manage'],
        'maintenance' => ['maintenance.manage', 'maintenance.resolve', 'maintenance.approve_high_value'],
        'hr' => ['team.users.manage'],
        'listings' => ['listings.manage'],
        'reports' => ['properties.manage', 'invoices.manage', 'payments.settle', 'accounting.entries.manage'],
        'accounting' => ['accounting.entries.manage', 'accounting.payroll.manage'],
        'settings' => ['settings.manage', 'settings.access.manage'],
        'vendors' => ['vendors.manage'],
        'communications' => ['communications.manage'],
        'financials' => ['invoices.manage', 'payments.settle', 'accounting.entries.manage'],
        'analytics' => ['properties.manage', 'invoices.manage', 'payments.settle', 'accounting.entries.manage'],
    ];

    /**
     * Longer prefixes are checked first.
     *
     * @var list<array{0: string, 1: list<string>}>
     */
    private const ROUTE_RULES = [
        ['property.hr', ['team.users.manage']],
        ['property.field_officers', ['team.users.manage']],
        ['property.field.readings', ['utilities.readings.capture', 'revenue.utilities.manage']],
        ['property.accounting.payroll', ['accounting.payroll.manage']],
        ['property.accounting', ['accounting.entries.manage', 'accounting.payroll.manage']],
        ['property.settings.forwarder', ['settings.manage', 'payments.record', 'payments.settle']],
        ['property.settings.roles', ['team.users.manage', 'settings.access.manage']],
        ['property.settings.team_users', ['team.users.manage', 'settings.access.manage']],
        ['property.settings.permissions', ['settings.access.manage']],
        ['property.settings.system_setup.access', ['settings.access.manage']],
        ['property.settings', ['settings.manage', 'settings.access.manage']],
        ['property.revenue.utilities', ['revenue.utilities.manage', 'utilities.readings.capture']],
        ['property.revenue.penalties', ['revenue.penalties.manage', 'payments.settle']],
        ['property.revenue.payments', ['payments.record', 'payments.settle']],
        ['property.revenue.mpesa_inbox', ['payments.record', 'payments.settle']],
        ['property.revenue.statements', ['payments.record', 'payments.settle']],
        ['property.revenue', ['invoices.manage', 'payments.record', 'payments.settle']],
        ['property.payments', ['payments.record', 'payments.settle', 'invoices.manage']],
        ['property.equity', ['payments.record', 'payments.settle']],
        ['property.invoices', ['invoices.manage', 'payments.record', 'payments.settle']],
        ['property.properties', ['properties.manage', 'property.archive.view']],
        ['property.landlords', ['properties.manage']],
        ['property.units', ['properties.manage']],
        ['property.tenants', ['tenants.manage', 'leases.manage']],
        ['property.leases', ['leases.manage', 'tenants.manage']],
        ['property.maintenance', ['maintenance.manage', 'maintenance.resolve', 'maintenance.approve_high_value']],
        ['property.listings', ['listings.manage']],
        ['property.reports', ['properties.manage', 'invoices.manage', 'payments.settle', 'accounting.entries.manage']],
        ['property.exports', ['properties.manage', 'invoices.manage', 'payments.settle', 'accounting.entries.manage']],
        ['property.financials', ['invoices.manage', 'payments.settle', 'accounting.entries.manage']],
        ['property.performance', ['properties.manage', 'invoices.manage', 'payments.settle', 'accounting.entries.manage']],
        ['property.vendors', ['vendors.manage']],
        ['property.communications', ['communications.manage']],
        ['property.notifications', ['communications.manage']],
    ];

    /**
     * @return list<string>|null Null when the workspace is available to every property user.
     */
    public static function forWorkspace(string $key): ?array
    {
        return self::WORKSPACES[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function requiredForRoute(string $routeName): array
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return [];
        }

        $match = [];
        $matchLength = -1;
        foreach (self::ROUTE_RULES as [$prefix, $permissions]) {
            if ($routeName !== $prefix && ! str_starts_with($routeName, $prefix.'.')) {
                continue;
            }
            if (strlen($prefix) > $matchLength) {
                $match = $permissions;
                $matchLength = strlen($prefix);
            }
        }

        return $match;
    }

    public static function allows(?User $user, string $routeName): bool
    {
        return self::userHasAny($user, self::requiredForRoute($routeName));
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function userHasAny(?User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }
        if (! $user instanceof User) {
            return false;
        }
        foreach ($permissions as $permission) {
            if ($user->hasPmPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
