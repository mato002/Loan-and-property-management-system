<?php

namespace App\Support\Property;

use App\Models\PmLease;
use App\Services\Property\PropertyMoney;
use Illuminate\Support\HtmlString;

final class LeaseStandingCharges
{
    /**
     * Recurring non-rent charges configured on a lease (utilities, service charge, etc.).
     *
     * @return list<array{type: string, type_label: string, amount: float}>
     */
    public static function lines(PmLease $lease): array
    {
        $lines = [];
        foreach (is_array($lease->utility_expenses) ? $lease->utility_expenses : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['type'] ?? ''));
            $amount = self::resolveRowAmount($row);
            if ($type === '' || $amount <= 0) {
                continue;
            }
            $lines[] = [
                'type' => $type,
                'type_label' => self::typeLabel($type),
                'amount' => $amount,
            ];
        }

        if ($lines !== []) {
            return $lines;
        }

        $amount = (float) ($lease->utility_expense_amount ?? 0);
        if ($amount <= 0) {
            return [];
        }

        $type = trim((string) ($lease->utility_expense_type ?? ''));

        return [[
            'type' => $type !== '' ? $type : 'other',
            'type_label' => self::typeLabel($type !== '' ? $type : 'other'),
            'amount' => $amount,
        ]];
    }

    public static function typeLabel(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $type));
    }

    public static function directoryCell(?PmLease $lease): HtmlString|string
    {
        if (! $lease) {
            return '—';
        }

        $lines = self::lines($lease);
        if ($lines === []) {
            return '—';
        }

        $formatted = array_map(
            fn (array $line): string => $line['type_label'].': '.PropertyMoney::kes($line['amount']),
            $lines,
        );

        return new HtmlString(implode('<br>', array_map(static fn (string $line): string => e($line), $formatted)));
    }

    public static function exportText(?PmLease $lease): string
    {
        if (! $lease) {
            return '';
        }

        $lines = self::lines($lease);
        if ($lines === []) {
            return '';
        }

        return implode('; ', array_map(
            fn (array $line): string => $line['type_label'].' '.PropertyMoney::kes($line['amount']),
            $lines,
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function resolveRowAmount(array $row): float
    {
        $amountRaw = $row['amount'] ?? null;
        if (is_numeric($amountRaw) && (float) $amountRaw > 0) {
            return (float) $amountRaw;
        }

        $fixedRaw = $row['fixed_charge'] ?? $row['fixed'] ?? null;
        if (is_numeric($fixedRaw) && (float) $fixedRaw > 0) {
            return (float) $fixedRaw;
        }

        $rateRaw = $row['rate_per_unit'] ?? null;
        if (is_numeric($rateRaw) && (float) $rateRaw > 0) {
            return (float) $rateRaw;
        }

        return 0.0;
    }
}
