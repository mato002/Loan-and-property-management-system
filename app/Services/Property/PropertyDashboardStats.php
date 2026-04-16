<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\PropertyUnit;
use Carbon\Carbon;

final class PropertyDashboardStats
{
    public static function mtdCollected(): float
    {
        return (float) PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');
    }

    public static function mtdBilled(): float
    {
        return (float) PmInvoice::query()
            ->whereBetween('issue_date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->sum('amount');
    }

    /**
     * @return array{target: float, actual: float|null, gap_kes: float}
     */
    public static function collectionRateMtd(float $targetPercent = 95.0): array
    {
        $collected = self::mtdCollected();
        $billed = self::mtdBilled();
        $actual = $billed > 0 ? round(min(100.0, 100.0 * $collected / $billed), 1) : null;
        $gapKes = max(0.0, $billed - $collected);

        return [
            'target' => $targetPercent,
            'actual' => $actual,
            'gap_kes' => $gapKes,
        ];
    }

    public static function outstandingBalance(): float
    {
        return (float) PmInvoice::query()
            ->whereIn('status', [
                PmInvoice::STATUS_SENT,
                PmInvoice::STATUS_PARTIAL,
                PmInvoice::STATUS_OVERDUE,
            ])
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->selectRaw('SUM(amount - amount_paid) as t')
            ->value('t') ?? 0;
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
        $q = PmInvoice::query()
            ->whereIn('status', [PmInvoice::STATUS_OVERDUE, PmInvoice::STATUS_PARTIAL, PmInvoice::STATUS_SENT])
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<=', now()->subDays($minDays));

        if ($maxDays !== null) {
            $q->where('due_date', '>', now()->subDays($maxDays));
        }

        return (float) $q->clone()->selectRaw('SUM(amount - amount_paid) as t')->value('t') ?? 0;
    }
}
