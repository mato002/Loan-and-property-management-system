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

    public static function outstandingBalance(): float
    {
        return (float) PmInvoice::query()
            ->whereIn('status', [
                PmInvoice::STATUS_SENT,
                PmInvoice::STATUS_PARTIAL,
                PmInvoice::STATUS_OVERDUE,
            ])
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
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<=', now()->subDays($minDays));

        if ($maxDays !== null) {
            $q->where('due_date', '>', now()->subDays($maxDays));
        }

        return (float) $q->clone()->selectRaw('SUM(amount - amount_paid) as t')->value('t') ?? 0;
    }
}
