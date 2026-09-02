<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Support\Facades\DB;

final class PassionLegacyLeaseStubCleanupService
{
    public function __construct(
        private PassionLegacyUnitResolver $unitResolver,
        private PassionLegacyRegisterSpaceFillService $fillService,
    ) {}

    /**
     * Relink leases off import stubs, remove generic fill units, then remove orphan stubs.
     *
     * @return array<string, mixed>
     */
    public function cleanup(int $agentUserId, bool $dryRun = false): array
    {
        $properties = Property::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->get(['id', 'code']);

        $propertyIds = $properties->pluck('id')->map(fn ($id) => (int) $id)->all();

        $summary = [
            'dry_run' => $dryRun,
            'units_before' => PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count(),
            'leases_relinked' => 0,
            'stubs_removed' => 0,
            'fill_units_removed' => 0,
            'warnings' => [],
        ];

        $run = function () use ($properties, $propertyIds, $dryRun, &$summary): void {
            $this->removeErroneousGenericFillUnits($properties, $dryRun, $summary);

            $stubs = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->whereIn('property_id', $propertyIds)
                ->get()
                ->filter(fn (PropertyUnit $unit) => $this->isLikelyLeaseImportStub($unit));

            foreach ($stubs as $stub) {
                $lease = PmLease::query()
                    ->withoutGlobalScopes()
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->whereHas('units', fn ($q) => $q->where('property_units.id', $stub->id))
                    ->first();

                if (! $lease) {
                    if (! $dryRun) {
                        DB::table('pm_lease_unit')->where('property_unit_id', $stub->id)->delete();
                        $stub->delete();
                    }
                    $summary['stubs_removed']++;

                    continue;
                }

                $target = $this->unitResolver->findBestOnProperty(
                    $stub->property_id,
                    $stub->label,
                    $stub->id,
                );

                if (! $target) {
                    $summary['warnings'][] = "Stub kept (no register unit match): property {$stub->property_id} / {$stub->label}";

                    continue;
                }

                if (! $dryRun) {
                    $lease->units()->sync([$target->id]);
                    $target->update([
                        'status' => PropertyUnit::STATUS_OCCUPIED,
                        'vacant_since' => null,
                    ]);

                    DB::table('pm_lease_unit')->where('property_unit_id', $stub->id)->delete();
                    if (! $this->unitHasActiveLease($stub->id)) {
                        $stub->delete();
                    }
                }

                $summary['leases_relinked']++;
                $summary['stubs_removed']++;
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($run);
        }

        $removed = $summary['stubs_removed'] + $summary['fill_units_removed'];
        $summary['units_after'] = $dryRun
            ? $summary['units_before'] - $removed
            : PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count();

        return $summary;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Property>  $properties
     * @param  array<string, mixed>  $summary
     */
    private function removeErroneousGenericFillUnits($properties, bool $dryRun, array &$summary): void
    {
        foreach ($properties as $property) {
            $code = (string) $property->code;
            if ($this->fillService->unitListingCountForCode($code) <= 0) {
                continue;
            }

            $units = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->where('property_id', $property->id)
                ->get()
                ->filter(fn (PropertyUnit $unit) => $this->isGenericFillUnit($unit));

            foreach ($units as $unit) {
                if ($this->unitHasActiveLease($unit->id)) {
                    $summary['warnings'][] = "Generic fill unit kept (active lease): {$code} / {$unit->label}";

                    continue;
                }

                if (! $dryRun) {
                    DB::table('pm_lease_unit')->where('property_unit_id', $unit->id)->delete();
                    $unit->delete();
                }

                $summary['fill_units_removed']++;
            }
        }
    }

    private function isGenericFillUnit(PropertyUnit $unit): bool
    {
        return (bool) preg_match('/^UNIT \d+$/i', trim((string) $unit->label));
    }

    private function isLikelyLeaseImportStub(PropertyUnit $unit): bool
    {
        if ($this->isGenericFillUnit($unit)) {
            return false;
        }

        return $unit->legacy_area === null
            && $unit->floor === null
            && $unit->market_rent === null
            && $unit->available_from === null;
    }

    private function unitHasActiveLease(int $unitId): bool
    {
        return PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unitId))
            ->exists();
    }
}
