<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmUnitUtilityCharge;
use App\Models\PropertyUnit;
use Carbon\Carbon;

final class RentRollQuery
{
    public static function normalizeMonth(?string $month): string
    {
        $month = trim((string) $month);
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return now()->format('Y-m');
        }

        $parsed = Carbon::createFromFormat('!Y-m', $month);
        if ($parsed === false || $parsed->format('Y-m') !== $month) {
            return now()->format('Y-m');
        }

        return $month;
    }

    /**
     * @return list<array{property_id: int, unit_id: int, tenant_id: int|null, cells: list<string>}>
     */
    public static function rowRecords(?string $month = null): array
    {
        $month = self::normalizeMonth($month);
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('!Y-m', $month)->endOfMonth()->toDateString();

        $utilityTotals = PmUnitUtilityCharge::query()
            ->where('billing_month', $month)
            ->selectRaw('property_unit_id, SUM(amount) as total')
            ->groupBy('property_unit_id')
            ->pluck('total', 'property_unit_id');

        $units = PropertyUnit::query()
            ->with([
                'property',
                'leases' => fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE),
                'leases.pmTenant',
            ])
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();

        $balanceByUnit = collect();
        $paidByUnit = collect();
        if ($unitIds !== []) {
            $balanceSnapshot = app(FinanceBalanceSnapshotService::class);
            $balanceByUnit = $balanceSnapshot
                ->billableArQuery()
                ->whereIn('property_unit_id', $unitIds)
                ->selectRaw('property_unit_id, '.FinanceBalanceSnapshotService::OUTSTANDING_SUM_SQL.' as balance')
                ->groupBy('property_unit_id')
                ->pluck('balance', 'property_unit_id');

            $paidByUnit = PmInvoice::query()
                ->billableAr()
                ->whereIn('property_unit_id', $unitIds)
                ->whereBetween('issue_date', [$start, $end])
                ->selectRaw('property_unit_id, COALESCE(SUM(amount_paid), 0) as paid')
                ->groupBy('property_unit_id')
                ->pluck('paid', 'property_unit_id');
        }

        $period = $month;
        $rows = [];
        foreach ($units as $unit) {
            $lease = $unit->leases->first();
            $tenant = $lease?->pmTenant;
            $balance = (float) ($balanceByUnit[$unit->id] ?? 0);
            $paid = (float) ($paidByUnit[$unit->id] ?? 0);
            $other = (float) ($utilityTotals[$unit->id] ?? 0);
            $otherLabel = $other > 0 ? PropertyMoney::kes($other) : '—';

            $rows[] = [
                'property_id' => (int) $unit->property_id,
                'unit_id' => (int) $unit->id,
                'tenant_id' => $tenant ? (int) $tenant->id : null,
                'cells' => [
                    $unit->property->name.' / '.$unit->label,
                    $tenant?->name ?? '—',
                    $period,
                    $lease ? PropertyMoney::kes((float) $lease->monthly_rent) : PropertyMoney::kes((float) $unit->rent_amount),
                    $otherLabel,
                    PropertyMoney::kes(max(0, $paid)),
                    PropertyMoney::kes($balance),
                    ucfirst($unit->status),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    public static function tableRows(?string $month = null): array
    {
        return array_map(static fn (array $row) => $row['cells'], self::rowRecords($month));
    }
}
