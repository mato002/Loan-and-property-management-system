<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PropertyUnit;

/**
 * Keep lease contract rent (what monthly invoices bill) aligned with unit rent
 * when operators update either side.
 */
final class LeaseBillingRentSync
{
    /**
     * After a unit rent change, push the new amount onto active lease(s)
     * so the next rent:generate-invoices run bills the updated figure.
     */
    public function syncFromUnitRent(PropertyUnit $unit): int
    {
        $unitRent = round((float) ($unit->rent_amount ?? 0), 2);
        $updated = 0;

        $leases = $unit->leases()
            ->where('pm_leases.status', PmLease::STATUS_ACTIVE)
            ->with(['units:id,rent_amount'])
            ->get();

        foreach ($leases as $lease) {
            $units = $lease->units;
            if ($units->isEmpty()) {
                continue;
            }

            $target = $units->count() === 1
                ? $unitRent
                : round((float) $units->sum(fn (PropertyUnit $u) => (float) ($u->rent_amount ?? 0)), 2);

            if ($target <= 0.009) {
                continue;
            }

            if (abs(round((float) $lease->monthly_rent, 2) - $target) <= 0.009) {
                continue;
            }

            $lease->update(['monthly_rent' => $target]);
            $updated++;
        }

        return $updated;
    }

    /**
     * After a lease monthly_rent change, mirror onto a single linked unit so
     * unit/profile rent displays match what invoices will bill.
     */
    public function syncFromLeaseRent(PmLease $lease): int
    {
        $lease->loadMissing(['units:id,rent_amount']);
        $units = $lease->units;
        if ($units->count() !== 1) {
            return 0;
        }

        $unit = $units->first();
        if (! $unit) {
            return 0;
        }

        $target = round((float) ($lease->monthly_rent ?? 0), 2);
        if ($target < 0) {
            return 0;
        }

        if (abs(round((float) $unit->rent_amount, 2) - $target) <= 0.009) {
            return 0;
        }

        $unit->update(['rent_amount' => $target]);

        return 1;
    }
}
