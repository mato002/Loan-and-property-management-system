<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Page-size choices that follow the current result count, including "All".
 */
final class ListPageSize
{
    public const ALL = 'all';

    public const HARD_CAP = 20000;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(?int $total, mixed $current = null): array
    {
        $currentRaw = is_scalar($current) ? strtolower(trim((string) $current)) : '';
        $currentNum = ctype_digit($currentRaw) ? (int) $currentRaw : null;

        $steps = [10, 20, 30, 50, 100, 200, 500, 1000];
        if ($total !== null && $total > 1000) {
            $steps[] = 2000;
            $steps[] = 5000;
        }

        $chosen = [];
        foreach ($steps as $size) {
            $chosen[$size] = true;
        }
        if ($currentNum !== null && $currentNum > 0) {
            $chosen[$currentNum] = true;
        }
        ksort($chosen);

        $options = [];
        foreach (array_keys($chosen) as $size) {
            $options[] = [
                'value' => (string) $size,
                'label' => number_format($size).' / page',
            ];
        }

        if ($total !== null && $total > 0) {
            $options[] = [
                'value' => self::ALL,
                'label' => 'All ('.number_format($total).')',
            ];
        } else {
            $options[] = [
                'value' => self::ALL,
                'label' => 'All',
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $viewVars
     * @return list<array{value: string, label: string}>
     */
    public static function fieldOptions(array $viewVars, mixed $current = null): array
    {
        return self::options(self::detectTotal($viewVars), $current ?? request()->query('per_page'));
    }

    /**
     * @param  array<string, mixed>  $viewVars
     */
    public static function detectTotal(array $viewVars): ?int
    {
        foreach (['tenantPager', 'paginator', 'rows', 'applicationsPager', 'leasesPager', 'leasePager'] as $key) {
            $total = self::totalFrom($viewVars[$key] ?? null);
            if ($total !== null) {
                return $total;
            }
        }

        foreach ($viewVars as $value) {
            $total = self::totalFrom($value);
            if ($total !== null) {
                return $total;
            }
        }

        return null;
    }

    public static function resolve(mixed $requested, int $default = 30, ?int $total = null): int
    {
        $raw = is_scalar($requested) ? strtolower(trim((string) $requested)) : '';
        if ($raw === '' || $raw === 'null') {
            $size = $default;
        } elseif ($raw === self::ALL || $raw === '0') {
            $size = ($total !== null && $total > 0) ? $total : self::HARD_CAP;
        } else {
            $size = (int) $raw;
            if ($size < 1) {
                $size = $default;
            }
        }

        return min(max($size, 1), self::HARD_CAP);
    }

    private static function totalFrom(mixed $value): ?int
    {
        if ($value instanceof LengthAwarePaginator) {
            return (int) $value->total();
        }

        return null;
    }
}
