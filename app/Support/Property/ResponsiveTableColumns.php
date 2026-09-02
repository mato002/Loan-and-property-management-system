<?php

namespace App\Support\Property;

/**
 * Column metadata for workspace dual table / mobile card rendering.
 *
 * @phpstan-type ColumnMeta array{
 *     label: string,
 *     mobile_label?: string|null,
 *     priority?: int,
 *     hide_on_mobile?: bool,
 *     is_primary?: bool,
 *     is_subtitle?: bool,
 *     is_status?: bool,
 *     is_amount?: bool,
 *     is_action?: bool,
 *     is_bulk_select?: bool,
 * }
 */
final class ResponsiveTableColumns
{
    /**
     * @param  list<string>|list<ColumnMeta>  $columns
     * @return list<ColumnMeta>
     */
    public static function normalize(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $index => $column) {
            if (is_string($column)) {
                $normalized[] = self::inferFromLabel($column, $index);
                continue;
            }

            $label = (string) ($column['label'] ?? '');
            $normalized[] = array_merge(self::inferFromLabel($label, $index), $column, [
                'label' => $label !== '' ? $label : (string) ($column['label'] ?? ''),
            ]);
        }

        return $normalized;
    }

    /**
     * @return ColumnMeta
     */
    private static function inferFromLabel(string $label, int $index): array
    {
        $lower = mb_strtolower(trim($label));

        $isAction = $lower === 'actions' || $lower === 'action';
        $isBulk = $label === '' || $lower === 'select';
        $isStatus = $lower === 'status' || str_contains($lower, 'status');
        $isAmount = in_array($lower, ['amount', 'balance', 'rent', 'current rent', 'deposit held'], true)
            || str_contains($lower, 'amount')
            || str_contains($lower, 'balance');

        return [
            'label' => $label,
            'mobile_label' => $label !== '' ? $label : null,
            'priority' => $index + 1,
            'hide_on_mobile' => $isBulk,
            'is_primary' => false,
            'is_subtitle' => false,
            'is_status' => $isStatus,
            'is_amount' => $isAmount,
            'is_action' => $isAction,
            'is_bulk_select' => $isBulk,
        ];
    }

    /** @return list<ColumnMeta> */
    public static function landlords(): array
    {
        return self::build(
            ['Landlord', 'Links', 'Shares (KES)', 'Last collection', 'Buildings', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 3, 'mobile_label' => 'Links'],
                2 => ['is_amount' => true, 'priority' => 2, 'mobile_label' => 'My share'],
                3 => ['priority' => 5, 'mobile_label' => 'Last paid'],
                4 => ['priority' => 4, 'mobile_label' => 'Buildings'],
                5 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function landlordPortfolio(): array
    {
        return self::build(
            ['Property', 'Ownership', 'Units', 'Tenants', 'Owner share', 'Pending', 'Your earnings', 'Last collection', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 3, 'mobile_label' => 'Ownership'],
                2 => ['priority' => 5, 'mobile_label' => 'Units'],
                3 => ['priority' => 6, 'hide_on_mobile' => true],
                4 => ['is_amount' => true, 'priority' => 2, 'mobile_label' => 'Owner share'],
                5 => ['priority' => 7, 'mobile_label' => 'Pending'],
                6 => ['priority' => 8, 'mobile_label' => 'Your earnings'],
                7 => ['priority' => 9, 'hide_on_mobile' => true],
                8 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function landlordCollections(): array
    {
        return self::build(
            ['Date', 'Tenant', 'Channel', 'Reference', 'Amount'],
            [
                0 => ['priority' => 4, 'mobile_label' => 'Date'],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['is_subtitle' => true, 'priority' => 2],
                3 => ['priority' => 5, 'hide_on_mobile' => true],
                4 => ['is_amount' => true, 'priority' => 3],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function landlordStatementBreakdown(): array
    {
        return self::build(
            ['Property', 'Ownership %', 'Owner share', 'Pending share', 'Agent earning', 'Last collection'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 3, 'mobile_label' => 'Ownership'],
                2 => ['is_amount' => true, 'priority' => 2, 'mobile_label' => 'Owner share'],
                3 => ['priority' => 4, 'mobile_label' => 'Pending'],
                4 => ['priority' => 5, 'mobile_label' => 'Agent earning'],
                5 => ['priority' => 6, 'mobile_label' => 'Last paid'],
            ]
        );
    }

    /**
     * @param  list<string>  $labels
     * @param  list<ColumnMeta>  $overrides
     * @return list<ColumnMeta>
     */
    private static function build(array $labels, array $overrides): array
    {
        $base = self::normalize($labels);

        foreach ($overrides as $index => $patch) {
            if (! isset($base[$index])) {
                continue;
            }
            $base[$index] = array_merge($base[$index], $patch);
        }

        return $base;
    }

    /** @return list<ColumnMeta> */
    public static function propertyList(): array
    {
        return self::build(
            ['Name / Code', 'Address / City', 'Units', 'Utility charges', 'Landlord(s)', 'Management', 'Occupancy', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['is_subtitle' => true, 'priority' => 2],
                2 => ['priority' => 4, 'mobile_label' => 'Units'],
                3 => ['priority' => 6, 'hide_on_mobile' => true, 'mobile_label' => 'Utilities'],
                4 => ['priority' => 5, 'mobile_label' => 'Landlords'],
                5 => ['priority' => 7, 'hide_on_mobile' => true],
                6 => ['is_status' => true, 'priority' => 3],
                7 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function units(): array
    {
        return self::build(
            ['Unit', 'Property', 'Type', 'Beds', 'Rent', 'Status', 'Tenant', 'Vacant since', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['is_subtitle' => true, 'priority' => 2],
                4 => ['is_amount' => true, 'priority' => 3],
                5 => ['is_status' => true, 'priority' => 4],
                6 => ['priority' => 5, 'mobile_label' => 'Tenant'],
                7 => ['priority' => 7, 'hide_on_mobile' => true],
                8 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function leases(string $tab = 'leases'): array
    {
        if ($tab === 'expiry') {
            return self::build(
                ['Tenant', 'Unit', 'End date', 'Days left', 'Current rent', 'Renewal offer', 'Status', 'Owner'],
                [
                    0 => ['is_primary' => true, 'priority' => 1],
                    1 => ['is_subtitle' => true, 'priority' => 2],
                    2 => ['priority' => 4, 'mobile_label' => 'Ends'],
                    3 => ['priority' => 5, 'mobile_label' => 'Days left'],
                    4 => ['is_amount' => true, 'priority' => 3],
                    5 => ['priority' => 6, 'hide_on_mobile' => true],
                    6 => ['is_status' => true, 'priority' => 7],
                    7 => ['is_action' => true, 'label' => 'Actions', 'mobile_label' => 'Actions'],
                ]
            );
        }

        return self::build(
            ['', 'Lease #', 'Unit(s)', 'Ac/No', 'Tenant', 'Phone', 'Email', 'Rent', 'A/c balance', 'Start', 'End', 'Variation', 'Status', 'Actions'],
            [
                0 => ['is_bulk_select' => true, 'hide_on_mobile' => false],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['priority' => 4, 'mobile_label' => 'Unit'],
                3 => ['priority' => 8, 'hide_on_mobile' => true, 'mobile_label' => 'Ac/No'],
                4 => ['is_subtitle' => true, 'priority' => 2],
                5 => ['priority' => 9, 'hide_on_mobile' => true],
                6 => ['priority' => 10, 'hide_on_mobile' => true],
                7 => ['is_amount' => true, 'priority' => 3],
                8 => ['is_amount' => true, 'priority' => 5, 'mobile_label' => 'Balance'],
                9 => ['priority' => 6, 'hide_on_mobile' => true],
                10 => ['priority' => 7, 'mobile_label' => 'End'],
                11 => ['priority' => 11, 'hide_on_mobile' => true],
                12 => ['is_status' => true, 'priority' => 12],
                13 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function invoices(): array
    {
        return self::build(
            ['Select', 'Invoice #', 'Type', 'Tenant', 'Unit', 'Period', 'Amount', 'Balance', 'Issued', 'Due', 'Status', 'Actions'],
            [
                0 => ['is_bulk_select' => true],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['priority' => 8, 'hide_on_mobile' => true],
                3 => ['is_subtitle' => true, 'priority' => 2],
                4 => ['priority' => 4, 'mobile_label' => 'Unit'],
                5 => ['priority' => 7],
                6 => ['is_amount' => true, 'priority' => 3],
                7 => ['priority' => 9, 'mobile_label' => 'Balance'],
                8 => ['priority' => 10, 'hide_on_mobile' => true],
                9 => ['priority' => 6, 'mobile_label' => 'Due'],
                10 => ['is_status' => true, 'priority' => 5],
                11 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function payments(): array
    {
        return self::build(
            ['Select', 'Ref', 'Source', 'Channel', 'Amount', 'Received at', 'Payer phone / ref', 'Allocated to', 'Status', 'Actions'],
            [
                0 => ['is_bulk_select' => true],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['priority' => 8, 'hide_on_mobile' => true],
                3 => ['priority' => 7, 'hide_on_mobile' => true],
                4 => ['is_amount' => true, 'priority' => 3],
                5 => ['priority' => 6, 'mobile_label' => 'Received'],
                6 => ['priority' => 9, 'hide_on_mobile' => true],
                7 => ['is_subtitle' => true, 'priority' => 4],
                8 => ['is_status' => true, 'priority' => 5],
                9 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function tenants(): array
    {
        return self::build(
            ['Tenant', 'Phone', 'Email', 'ID / ref', 'Leases', 'Lease end', 'Risk', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 4, 'mobile_label' => 'Phone'],
                2 => ['is_subtitle' => true, 'priority' => 2, 'hide_on_mobile' => true],
                3 => ['priority' => 6, 'hide_on_mobile' => true],
                4 => ['priority' => 5, 'mobile_label' => 'Leases'],
                5 => ['priority' => 7, 'mobile_label' => 'Lease end'],
                6 => ['is_status' => true, 'priority' => 3],
                7 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function maintenanceRequests(): array
    {
        return self::build(
            ['ID', 'Unit', 'Category', 'Summary', 'Reported', 'Priority', 'Status', 'Assignee', 'Actions'],
            [
                0 => ['priority' => 8, 'hide_on_mobile' => true],
                1 => ['is_subtitle' => true, 'priority' => 2],
                2 => ['priority' => 5, 'mobile_label' => 'Category'],
                3 => ['is_primary' => true, 'priority' => 1],
                4 => ['priority' => 6, 'mobile_label' => 'Reported'],
                5 => ['is_status' => true, 'priority' => 4],
                6 => ['priority' => 7],
                7 => ['priority' => 9, 'hide_on_mobile' => true],
                8 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function vendors(): array
    {
        return self::build(
            ['Vendor', 'Category', 'Contact', 'Payment terms', 'Insurance until', 'Rating', 'Status', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 4, 'mobile_label' => 'Category'],
                2 => ['is_subtitle' => true, 'priority' => 2],
                3 => ['priority' => 7, 'hide_on_mobile' => true],
                4 => ['priority' => 8, 'hide_on_mobile' => true],
                5 => ['priority' => 5, 'mobile_label' => 'Rating'],
                6 => ['is_status' => true, 'priority' => 3],
                7 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function arrears(): array
    {
        return self::build(
            ['Pick', 'Tenant', 'Phone', 'Unit(s)', 'Invoices', 'Arrears types', 'Oldest due', 'Aging', 'Balance', 'Last contact', 'Workflow', 'Actions'],
            [
                0 => ['is_bulk_select' => true],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['priority' => 8, 'hide_on_mobile' => true],
                3 => ['is_subtitle' => true, 'priority' => 2],
                4 => ['priority' => 6, 'mobile_label' => 'Invoices'],
                5 => ['priority' => 9, 'hide_on_mobile' => true],
                6 => ['priority' => 5, 'mobile_label' => 'Oldest due'],
                7 => ['priority' => 7, 'mobile_label' => 'Aging'],
                8 => ['is_amount' => true, 'priority' => 3],
                9 => ['priority' => 10, 'hide_on_mobile' => true],
                10 => ['is_status' => true, 'priority' => 4],
                11 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function rentRoll(): array
    {
        return self::build(
            ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['is_subtitle' => true, 'priority' => 2],
                2 => ['priority' => 6, 'mobile_label' => 'Period'],
                3 => ['is_amount' => true, 'priority' => 3],
                4 => ['priority' => 7, 'hide_on_mobile' => true],
                5 => ['priority' => 8, 'mobile_label' => 'Paid'],
                6 => ['is_amount' => true, 'priority' => 4, 'mobile_label' => 'Balance'],
                7 => ['is_status' => true, 'priority' => 5],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function receipts(): array
    {
        return self::build(
            ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['priority' => 4, 'mobile_label' => 'Invoice'],
                2 => ['is_subtitle' => true, 'priority' => 2],
                3 => ['is_amount' => true, 'priority' => 3],
                4 => ['priority' => 7, 'hide_on_mobile' => true],
                5 => ['priority' => 6, 'mobile_label' => 'Submitted'],
                6 => ['is_status' => true, 'priority' => 5],
                7 => ['is_action' => true],
            ]
        );
    }

    /** @return list<ColumnMeta> */
    public static function uninvoicedLeases(): array
    {
        return self::build(
            ['', 'Tenant', 'Property', 'Unit', 'Bill amount', 'Due date', 'Status', 'Action'],
            [
                0 => ['is_bulk_select' => true],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['priority' => 5, 'hide_on_mobile' => true],
                3 => ['is_subtitle' => true, 'priority' => 2],
                4 => ['is_amount' => true, 'priority' => 3],
                5 => ['priority' => 6, 'mobile_label' => 'Due'],
                6 => ['is_status' => true, 'priority' => 4],
                7 => ['is_action' => true],
            ]
        );
    }

    /**
     * Resolve column metadata preset for a named route.
     *
     * @return list<ColumnMeta>|null
     */
    public static function forRoute(?string $routeName): ?array
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return match (true) {
            $routeName === 'property.landlords.index' => self::landlords(),
            $routeName === 'property.properties.list' => self::propertyList(),
            $routeName === 'property.properties.units' => self::units(),
            $routeName === 'property.tenants.directory' => self::tenants(),
            $routeName === 'property.tenants.leases' => self::leases('leases'),
            $routeName === 'property.tenants.expiry' => self::leases('expiry'),
            $routeName === 'property.revenue.invoices' => self::invoices(),
            $routeName === 'property.revenue.payments' => self::payments(),
            $routeName === 'property.revenue.receipts' => self::receipts(),
            $routeName === 'property.revenue.arrears' => self::arrears(),
            $routeName === 'property.revenue.rent_roll' => self::rentRoll(),
            $routeName === 'property.revenue.uninvoiced_leases' => self::uninvoicedLeases(),
            $routeName === 'property.maintenance.requests' => self::maintenanceRequests(),
            $routeName === 'property.vendors.directory' => self::vendors(),
            default => null,
        };
    }
}
