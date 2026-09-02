<?php

namespace App\Console\Commands;

use App\Models\PmLease;
use App\Models\PropertyUnit;
use App\Services\Property\PassionLegacyTextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupPassionImportDuplicatesCommand extends Command
{
    protected $signature = 'property:cleanup-passion-import-duplicates
                            {--dry-run : Report only; do not change data}
                            {--delete-orphan-stubs : Remove stub units with no active lease}
                            {--delete-duplicate-stubs : Remove stub units when a register-imported unit exists on the same property with the same label}';

    protected $description = 'Remove duplicate active leases and orphan stub units after out-of-order Passion legacy imports';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleteOrphans = (bool) $this->option('delete-orphan-stubs');

        $leasesTerminated = 0;
        $unitsVacated = 0;
        $unitsDeleted = 0;

        $run = function () use ($dryRun, $deleteOrphans, &$leasesTerminated, &$unitsVacated, &$unitsDeleted): void {
            $tenantIds = PmLease::query()
                ->withoutGlobalScopes()
                ->where('status', PmLease::STATUS_ACTIVE)
                ->select('pm_tenant_id')
                ->groupBy('pm_tenant_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('pm_tenant_id');

            foreach ($tenantIds as $tenantId) {
                $leases = PmLease::query()
                    ->withoutGlobalScopes()
                    ->where('pm_tenant_id', $tenantId)
                    ->where('status', PmLease::STATUS_ACTIVE)
                    ->with('units')
                    ->orderByDesc('id')
                    ->get();

                $keeper = $leases->sortByDesc(fn (PmLease $lease) => $this->leaseScore($lease))->first();
                if (! $keeper) {
                    continue;
                }

                foreach ($leases as $lease) {
                    if ($lease->id === $keeper->id) {
                        continue;
                    }

                    if (! $dryRun) {
                        $lease->update(['status' => PmLease::STATUS_TERMINATED]);
                    }
                    $leasesTerminated++;

                    foreach ($lease->units as $unit) {
                        if (! $this->isLikelyImportStub($unit)) {
                            continue;
                        }

                        if ($this->unitHasOtherActiveLease($unit->id, $keeper->id)) {
                            continue;
                        }

                        if (! $dryRun) {
                            $unit->update([
                                'status' => PropertyUnit::STATUS_VACANT,
                                'vacant_since' => now()->toDateString(),
                            ]);
                        }
                        $unitsVacated++;
                    }
                }
            }

            if ($deleteOrphans) {
                $orphans = PropertyUnit::query()
                    ->withoutGlobalScopes()
                    ->whereNull('legacy_area')
                    ->whereNull('floor')
                    ->whereNull('market_rent')
                    ->whereNull('available_from')
                    ->whereDoesntHave('leases', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE))
                    ->get();

                foreach ($orphans as $unit) {
                    if (! $dryRun) {
                        DB::table('pm_lease_unit')->where('property_unit_id', $unit->id)->delete();
                        $unit->delete();
                    }
                    $unitsDeleted++;
                }
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

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Duplicate leases terminated={$leasesTerminated} | Stub units vacated={$unitsVacated} | Orphan stubs deleted={$unitsDeleted}");

        return self::SUCCESS;
    }

    private function leaseScore(PmLease $lease): int
    {
        $score = 0;
        foreach ($lease->units as $unit) {
            if (! $this->isLikelyImportStub($unit)) {
                $score += 10;
            }
        }

        return $score + (int) $lease->id;
    }

    private function isLikelyImportStub(PropertyUnit $unit): bool
    {
        return $unit->legacy_area === null
            && $unit->floor === null
            && $unit->market_rent === null
            && $unit->available_from === null;
    }

    private function unitHasOtherActiveLease(int $unitId, int $exceptLeaseId): bool
    {
        return PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->where('id', '!=', $exceptLeaseId)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unitId))
            ->exists();
    }
}
