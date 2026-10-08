<?php

namespace App\Support\Property;

use Illuminate\Http\Request;

/**
 * View / Create / Edit / Delete permissions that sit beside each *.manage key.
 * Manage still grants all four. A direct Deny on one action blocks that action.
 */
final class PropertyCrudPermissions
{
    /** @var array<string, string> prefix => permission group */
    private const MODULES = [
        'properties' => 'properties',
        'tenants' => 'tenants',
        'leases' => 'tenants',
        'maintenance' => 'maintenance',
        'vendors' => 'vendors',
        'invoices' => 'revenue',
        'listings' => 'listings',
        'communications' => 'communications',
        'accounting.entries' => 'accounting',
        'accounting.payroll' => 'accounting',
        'revenue.penalties' => 'revenue',
        'revenue.utilities' => 'revenue',
        'settings' => 'settings',
        'team.users' => 'settings',
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'properties' => 'properties',
        'tenants' => 'tenants',
        'leases' => 'leases',
        'maintenance' => 'maintenance',
        'vendors' => 'vendors',
        'invoices' => 'invoices',
        'listings' => 'listings',
        'communications' => 'communications',
        'accounting.entries' => 'accounting entries',
        'accounting.payroll' => 'payroll',
        'revenue.penalties' => 'penalties',
        'revenue.utilities' => 'utilities',
        'settings' => 'settings',
        'team.users' => 'staff',
    ];

    /**
     * @return list<array{name: string, key: string, group: string, description: string}>
     */
    public static function definitions(): array
    {
        $rows = [];
        foreach (self::MODULES as $prefix => $group) {
            $module = self::MODULE_LABELS[$prefix];
            foreach (self::ACTION_LABELS as $action => $label) {
                $rows[] = [
                    'name' => $label.' '.$module,
                    'key' => $prefix.'.'.$action,
                    'group' => $group,
                    'description' => match ($action) {
                        'view' => 'Open lists and records. Does not allow adding, editing, or deleting.',
                        'create' => 'Add new '.$module.'.',
                        'update' => 'Edit existing '.$module.'.',
                        'delete' => 'Delete or cancel '.$module.'.',
                    },
                ];
            }
        }

        return $rows;
    }

    public static function manageKeyFor(string $permissionKey): ?string
    {
        if (! preg_match('/^(.+)\.(view|create|update|delete)$/', $permissionKey, $matches)) {
            return null;
        }

        $manageKey = $matches[1].'.manage';

        return isset(self::MODULES[$matches[1]]) ? $manageKey : null;
    }

    /**
     * Action key that satisfies a *.manage route for this request.
     */
    public static function sliceFor(string $requiredKey, Request $request): ?string
    {
        if (! str_ends_with($requiredKey, '.manage')) {
            return null;
        }

        $prefix = substr($requiredKey, 0, -strlen('.manage'));
        if (! isset(self::MODULES[$prefix])) {
            return null;
        }

        return $prefix.'.'.self::actionFor($request);
    }

    private static function actionFor(Request $request): string
    {
        $name = (string) ($request->route()?->getName() ?? '');
        $method = strtoupper($request->method());

        if ($method === 'DELETE' || preg_match('/\.(destroy|delete|terminate|cancel)(\.|$)/', $name)) {
            return 'delete';
        }

        if (in_array($method, ['PUT', 'PATCH'], true) || preg_match('/\.(update|edit|restore|reopen|status)(\.|$)/', $name)) {
            return 'update';
        }

        if ($method === 'POST' || preg_match('/\.(store|create|import|generate)(\.|$)/', $name)) {
            return 'create';
        }

        if (preg_match('/\.(create|import)(\.|$)/', $name)) {
            return 'create';
        }

        return 'view';
    }
}
