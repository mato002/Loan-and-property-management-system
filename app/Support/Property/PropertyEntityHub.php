<?php

namespace App\Support\Property;

use Illuminate\Http\Request;

/**
 * Phase 3 — entity hub tab definitions and URL helpers.
 */
final class PropertyEntityHub
{
    public const TENANT_TABS = [
        ['key' => 'overview', 'label' => 'Overview'],
        ['key' => 'leases', 'label' => 'Leases'],
        ['key' => 'invoices', 'label' => 'Invoices'],
        ['key' => 'payments', 'label' => 'Payments'],
        ['key' => 'notices', 'label' => 'Notices'],
        ['key' => 'utilities', 'label' => 'Utilities'],
        ['key' => 'statement', 'label' => 'Statement'],
    ];

    public const PROPERTY_TABS = [
        ['key' => 'overview', 'label' => 'Overview'],
        ['key' => 'units', 'label' => 'Units'],
        ['key' => 'occupancy', 'label' => 'Occupancy'],
        ['key' => 'landlords', 'label' => 'Landlords'],
        ['key' => 'utilities', 'label' => 'Utilities'],
        ['key' => 'maintenance', 'label' => 'Maintenance'],
        ['key' => 'performance', 'label' => 'Performance'],
        ['key' => 'revenue', 'label' => 'Revenue'],
        ['key' => 'offboarding', 'label' => 'Offboarding'],
    ];

    public const UNIT_TABS = [
        ['key' => 'overview', 'label' => 'Overview'],
        ['key' => 'tenant', 'label' => 'Tenant & lease'],
        ['key' => 'invoices', 'label' => 'Invoices'],
        ['key' => 'utilities', 'label' => 'Utilities'],
        ['key' => 'maintenance', 'label' => 'Maintenance'],
        ['key' => 'history', 'label' => 'Occupancy'],
    ];

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function tabsFor(string $entity): array
    {
        return match ($entity) {
            'tenant' => self::TENANT_TABS,
            'property' => self::PROPERTY_TABS,
            'unit' => self::UNIT_TABS,
            default => [],
        };
    }

    public static function normalizeTab(string $entity, ?string $tab): string
    {
        $tab = trim((string) $tab);
        $keys = array_column(self::tabsFor($entity), 'key');

        return in_array($tab, $keys, true) ? $tab : 'overview';
    }

    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $query
     */
    public static function tabUrl(string $routeName, array $routeParams, string $tab, array $query = []): string
    {
        $query['tab'] = $tab;

        return route($routeName, $routeParams, false).'?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $preserveQuery
     */
    public static function activeTabFromRequest(Request $request, string $entity, array $preserveQuery = []): string
    {
        return self::normalizeTab($entity, $request->query('tab'));
    }
}
