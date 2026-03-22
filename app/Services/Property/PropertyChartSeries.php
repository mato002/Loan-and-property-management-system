<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\User;
use Carbon\Carbon;

final class PropertyChartSeries
{
    /**
     * @return list<array{label: string, collected: float, billed: float, rate: float|null}>
     */
    public static function monthlyCollectionTrend(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            $collected = (float) PmPayment::query()
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $billed = (float) PmInvoice::query()
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $rate = $billed > 0 ? round(min(100.0, 100.0 * $collected / $billed), 1) : null;

            $out[] = [
                'label' => $start->format('M y'),
                'collected' => $collected,
                'billed' => $billed,
                'rate' => $rate,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    /**
     * Magnitudes for bar display (all non-negative).
     *
     * @return list<array{label: string, value: float}>
     */
    public static function incomeExpenseWaterfall(float $income, float $maintenance, float $utilities): array
    {
        $noi = max(0.0, $income - $maintenance - $utilities);

        return [
            ['label' => 'Billed rent', 'value' => $income],
            ['label' => 'Maintenance', 'value' => $maintenance],
            ['label' => 'Utilities', 'value' => $utilities],
            ['label' => 'NOI proxy', 'value' => $noi],
        ];
    }

    /**
     * @return list<array{label: string, in: float, out: float, net: float}>
     */
    public static function landlordLedgerMonthlyNet(User $user, int $months = 6): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            $base = PmLandlordLedgerEntry::query()
                ->where('user_id', $user->id)
                ->whereBetween('occurred_at', [$start, $end]);

            $in = (float) (clone $base)->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)->sum('amount');
            $outAmt = (float) (clone $base)->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)->sum('amount');

            $series[] = [
                'label' => $start->format('M y'),
                'in' => $in,
                'out' => $outAmt,
                'net' => $in - $outAmt,
            ];
        }

        return $series;
    }

    /**
     * Cumulative ledger balance at end of each month (last N months).
     *
     * @return list<array{label: string, balance: float}>
     */
    public static function landlordCumulativeCash(User $user, int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $last = PmLandlordLedgerEntry::query()
                ->where('user_id', $user->id)
                ->where('occurred_at', '<=', $end)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first();

            $out[] = [
                'label' => $end->copy()->startOfMonth()->format('M y'),
                'balance' => $last ? (float) $last->balance_after : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, in: float, out: float}>
     */
    public static function agentCashFlowMonthly(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            $in = (float) PmPayment::query()
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $outAmt = (float) PmMaintenanceJob::query()
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$start, $end])
                ->sum('quote_amount');

            $out[] = [
                'label' => $start->format('M y'),
                'in' => $in,
                'out' => $outAmt,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{label: string, x: float, y: float, jobs: int}>
     */
    public static function vendorScatterPoints(): array
    {
        $jobs = PmMaintenanceJob::query()
            ->whereNotNull('pm_vendor_id')
            ->with('vendor')
            ->orderByDesc('id')
            ->limit(800)
            ->get();

        $points = [];
        foreach ($jobs->groupBy('pm_vendor_id') as $group) {
            /** @var \Illuminate\Support\Collection<int, PmMaintenanceJob> $group */
            $vendor = $group->first()->vendor;
            $name = $vendor?->name ?? 'Vendor #'.$group->first()->pm_vendor_id;
            $total = $group->count();
            $done = $group->where('status', 'done')->count();
            $x = $total > 0 ? round(100 * $done / $total, 1) : 0.0;
            $sumQuotes = (float) $group->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0));
            $y = round($sumQuotes / 1000, 2);

            $points[] = [
                'label' => $name,
                'x' => $x,
                'y' => $y,
                'jobs' => $total,
            ];
        }

        usort($points, static fn ($a, $b) => $b['jobs'] <=> $a['jobs']);

        return array_slice($points, 0, 24);
    }
}
