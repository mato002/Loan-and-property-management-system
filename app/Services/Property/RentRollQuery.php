<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmUnitUtilityCharge;
use App\Models\PropertyUnit;

final class RentRollQuery
{
    /**
     * @return list<list<string>>
     */
    public static function tableRows(): array
    {
        $utilityTotals = PmUnitUtilityCharge::query()
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

        $rows = [];
        foreach ($units as $unit) {
            $lease = $unit->leases->first();
            $tenant = $lease?->pmTenant;
            $balance = (float) PmInvoice::query()
                ->where('property_unit_id', $unit->id)
                ->selectRaw('COALESCE(SUM(amount - amount_paid),0) as b')
                ->value('b');

            $period = now()->format('Y-m');
            $due = $lease ? PropertyMoney::kes((float) $lease->monthly_rent) : PropertyMoney::kes((float) $unit->rent_amount);
            $other = (float) ($utilityTotals[$unit->id] ?? 0);
            $otherLabel = $other > 0 ? PropertyMoney::kes($other) : '—';

            $rows[] = [
                $unit->property->name.' / '.$unit->label,
                $tenant?->name ?? '—',
                $period,
                $due,
                $otherLabel,
                PropertyMoney::kes(max(0, (float) PmInvoice::query()->where('property_unit_id', $unit->id)->sum('amount_paid'))),
                PropertyMoney::kes($balance),
                ucfirst($unit->status),
            ];
        }

        return $rows;
    }
}
