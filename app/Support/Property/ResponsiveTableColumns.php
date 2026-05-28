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

    /**
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
            ['Name / Code', 'Address / City', 'Units', 'Utility charges', 'Landlord(s)', 'Status', 'Actions'],
            [
                0 => ['is_primary' => true, 'priority' => 1],
                1 => ['is_subtitle' => true, 'priority' => 2],
                2 => ['priority' => 4, 'mobile_label' => 'Units'],
                3 => ['priority' => 6, 'hide_on_mobile' => true, 'mobile_label' => 'Utilities'],
                4 => ['priority' => 5, 'mobile_label' => 'Landlords'],
                5 => ['is_status' => true, 'priority' => 3],
                6 => ['is_action' => true],
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
            ['', 'Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Expense paid', 'Status', 'Actions'],
            [
                0 => ['is_bulk_select' => true, 'hide_on_mobile' => false],
                1 => ['is_primary' => true, 'priority' => 1],
                2 => ['is_subtitle' => true, 'priority' => 2],
                3 => ['priority' => 4],
                4 => ['priority' => 6, 'hide_on_mobile' => true],
                5 => ['priority' => 7, 'mobile_label' => 'End'],
                6 => ['is_amount' => true, 'priority' => 3],
                7 => ['priority' => 8, 'hide_on_mobile' => true],
                8 => ['priority' => 9, 'hide_on_mobile' => true],
                9 => ['is_status' => true, 'priority' => 5],
                10 => ['is_action' => true],
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
}
