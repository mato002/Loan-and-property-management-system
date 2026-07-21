<?php

namespace App\Services\Property;

use App\Models\PmMaintenanceJob;
use App\Models\PropertyUnit;
use Carbon\Carbon;

final class PropertyDashboardStats
{
    public static function mtdCollected(): float
    {
        return app(FinancialReportingFormulaService::class)->collectionsMtd();
    }

    public static function mtdBilled(): float
    {
        return app(FinancialReportingFormulaService::class)->billedMtd();
    }

    /**
     * @return array{target: float, actual: float|null, gap_kes: float}
     */
    public static function collectionRateMtd(float $targetPercent = 95.0): array
    {
        return app(FinancialReportingFormulaService::class)->collectionRateMtd($targetPercent);
    }

    public static function outstandingBalance(): float
    {
        return app(FinancialReportingFormulaService::class)->outstandingGlobal();
    }

    public static function occupancyRate(): ?float
    {
        $total = PropertyUnit::query()->count();
        if ($total === 0) {
            return null;
        }
        $occ = PropertyUnit::query()->where('status', PropertyUnit::STATUS_OCCUPIED)->count();

        return round(100 * $occ / $total, 1);
    }

    public static function maintenanceSpendMtd(): float
    {
        return (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('quote_amount');
    }

    public static function arrearsBucket(int $minDays, ?int $maxDays = null): float
    {
        return app(FinancialReportingFormulaService::class)->agingBucket($minDays, $maxDays);
    }
}
