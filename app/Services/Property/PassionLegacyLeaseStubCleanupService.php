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
    ) {}

    /**
     * Relink active leases off import stubs onto register units, then remove orphan stubs.
     *
     * @return array<string, mixed>
     */
    public function cleanup(int $agentUserId, bool $dryRun = false): array
    {
        $propertyIds = Property::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $summary = [
            'dry_run' => $dryRun,
            'units_before' => PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count(),
            'leases_relinked' => 0,
            'stubs_removed' => 0,
            'warnings' => [],
        ];

        $run = function () use ($propertyIds, $dryRun, &$summary): void {
            $stubs = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->whereIn('property_id', $propertyIds)
                ->get()
                ->filter(fn (PropertyUnit $unit) => $this->isLikelyImportStub($unit));

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

        $summary['units_after'] = $dryRun
            ? $summary['units_before'] - $summary['stubs_removed']
            : PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count();

        return $summary;
    }

    private function isLikelyImportStub(PropertyUnit $unit): bool
    {
        if (preg_match('/^UNIT \d+$/i', trim((string) $unit->label))) {
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
