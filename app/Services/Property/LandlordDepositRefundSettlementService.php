<?php

namespace App\Services\Property;

use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmTenantDeposit;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * When a tenant deposit is refunded to the tenant, post a matching debit on each
 * landlord's property ledger (pro-rata ownership) so monthly settlement reflects it.
 */
final class LandlordDepositRefundSettlementService
{
    /**
     * @return array{posted: int, skipped: int}
     */
    public function postDeductionForDepositRefund(PmTenantDeposit $deposit, ?int $actorId = null): array
    {
        if ((float) $deposit->amount <= 0) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $propertyId = $this->resolvePropertyIdForTenant((int) $deposit->tenant_id);
        if ($propertyId <= 0) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $property = Property::query()->find($propertyId);
        if (! $property) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $tenant = PmTenant::query()->find((int) $deposit->tenant_id);
        $tenantLabel = trim((string) ($tenant?->name ?? '')) ?: ('Tenant #'.$deposit->tenant_id);
        $description = 'Deposit refund — '.$tenantLabel.' (DEP-'.$deposit->id.')';

        $links = DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->get(['user_id', 'ownership_percent']);

        $posted = 0;
        $skipped = 0;

        foreach ($links as $link) {
            $landlordId = (int) ($link->user_id ?? 0);
            $ownershipPct = (float) ($link->ownership_percent ?? 0);
            if ($landlordId <= 0 || $ownershipPct <= 0) {
                continue;
            }

            $share = round((float) $deposit->amount * ($ownershipPct / 100), 2);
            if ($share <= 0) {
                continue;
            }

            if ($this->hasDeductionForDeposit($deposit->id, $landlordId, $propertyId)) {
                $skipped++;

                continue;
            }

            $landlord = User::query()->find($landlordId);
            if (! $landlord) {
                continue;
            }

            LandlordLedger::post(
                $landlord,
                PmLandlordLedgerEntry::DIRECTION_DEBIT,
                $share,
                $description,
                $property,
                'pm_tenant_deposit',
                (int) $deposit->id,
            );

            $posted++;
        }

        return ['posted' => $posted, 'skipped' => $skipped];
    }

    private function resolvePropertyIdForTenant(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        $fromActiveLease = (int) DB::table('pm_leases as l')
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
            ->where('l.pm_tenant_id', $tenantId)
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->orderByDesc('l.id')
            ->value('pu.property_id');

        if ($fromActiveLease > 0) {
            return $fromActiveLease;
        }

        return (int) DB::table('pm_leases as l')
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
            ->where('l.pm_tenant_id', $tenantId)
            ->orderByDesc('l.id')
            ->value('pu.property_id');
    }

    private function hasDeductionForDeposit(int $depositId, int $landlordId, int $propertyId): bool
    {
        return PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_tenant_deposit')
            ->where('reference_id', $depositId)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)
            ->where('user_id', $landlordId)
            ->where('property_id', $propertyId)
            ->exists();
    }
}
