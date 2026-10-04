<?php

namespace App\Support\Property;

use Carbon\Carbon;

final class LandlordMonthlyShareTotals
{
    /**
     * Last month included in a running FY total: current month this year, otherwise December of that FY.
     */
    public static function uptoMonth(?int $fy = null): string
    {
        $fy = $fy ?? (int) now()->year;
        if ((int) now()->year === $fy) {
            return now()->format('Y-m');
        }

        return sprintf('%04d-12', $fy);
    }

    public static function periodHint(?int $fy = null, ?string $uptoMonth = null): string
    {
        $fy = $fy ?? (int) now()->year;
        $upto = $uptoMonth ?: self::uptoMonth($fy);
        $end = Carbon::createFromFormat('Y-m', $upto)->format('M Y');

        return 'FY '.$fy.' to '.$end;
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $months
     * @return array{gross_collected: float, paid_share: float, pending_share: float, owner_share: float, agent_earning: float, landlord_net: float}
     */
    public static function toDate(array $months, ?string $uptoMonth = null): array
    {
        $upto = $uptoMonth ?: now()->format('Y-m');
        $totals = [
            'gross_collected' => 0.0,
            'paid_share' => 0.0,
            'pending_share' => 0.0,
            'owner_share' => 0.0,
            'agent_earning' => 0.0,
            'expenses' => 0.0,
        ];

        foreach ($months as $month) {
            if (! is_array($month)) {
                continue;
            }
            $ym = (string) ($month['month'] ?? '');
            if ($ym === '' || $ym > $upto) {
                continue;
            }
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) ($month[$key] ?? 0);
            }
        }

        $totals['landlord_net'] = max(0.0, $totals['owner_share'] - $totals['agent_earning'] - $totals['expenses']);

        return $totals;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $propertyBreakdown
     * @return array{gross_collected: float, paid_share: float, pending_share: float, owner_share: float, agent_earning: float, landlord_net: float}
     */
    public static function toDateForProperties(iterable $propertyBreakdown, ?string $uptoMonth = null): array
    {
        $combined = [
            'gross_collected' => 0.0,
            'paid_share' => 0.0,
            'pending_share' => 0.0,
            'owner_share' => 0.0,
            'agent_earning' => 0.0,
            'expenses' => 0.0,
            'landlord_net' => 0.0,
        ];

        foreach ($propertyBreakdown as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ytd = self::toDate($row['monthly_shares'] ?? [], $uptoMonth);
            foreach ($combined as $key => $value) {
                $combined[$key] = $value + (float) ($ytd[$key] ?? 0);
            }
        }

        $combined['landlord_net'] = max(0.0, $combined['owner_share'] - $combined['agent_earning'] - $combined['expenses']);

        return $combined;
    }
}
