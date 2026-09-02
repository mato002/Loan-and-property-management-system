<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PassionLegacyImportReconciliationService
{
    public function __construct(
        private PassionLegacyUnitRegisterParser $unitParser,
        private PassionLegacyLeasesRegisterParser $leasesParser,
        private PassionLegacyRegisterParser $propertyRegisterParser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private PassionPropertyCodeResolver $codeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reconcile(int $agentUserId, string $unitsPath, string $leasesPath, bool $dryRun = false): array
    {
        $propertyRegister = $this->loadPropertyRegister();
        $unitRecords = $this->unitParser->parse($this->extractor->extract($unitsPath));
        $leaseRecords = $this->leasesParser->parse($this->extractor->extract($leasesPath));

        $propertyIds = Property::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->pluck('id')
            ->all();

        $summary = [
            'dry_run' => $dryRun,
            'expected_units' => 0,
            'db_units_before' => PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count(),
            'leases_relinked' => 0,
            'labels_aligned' => 0,
            'duplicate_units_removed' => 0,
            'orphan_units_removed' => 0,
            'statuses_synced' => 0,
            'missing_active_leases' => [],
            'warnings' => [],
        ];

        /** @var array<string, array{property_id: int, label: string, status: string}> $expectedUnits */
        $expectedUnits = [];
        foreach ($unitRecords as $record) {
            $property = $this->codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
            if (! $property) {
                $summary['warnings'][] = 'Register unit skipped — property not found: '.$record['property_name'].' / '.$record['unit_label'];

                continue;
            }

            $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
            $key = $property->id.'|'.$label;
            $expectedUnits[$key] = [
                'property_id' => $property->id,
                'label' => $label,
                'status' => $record['status'],
            ];
        }
        $summary['expected_units'] = count($expectedUnits);

        $run = function () use ($agentUserId, $propertyIds, $expectedUnits, $leaseRecords, $dryRun, &$summary): void {
            $this->relinkLeasesFromRegister($leaseRecords, $expectedUnits, $dryRun, $summary);
            $this->alignMislabeledUnits($propertyIds, $expectedUnits, $dryRun, $summary);

            $dbUnits = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->whereIn('property_id', $propertyIds)
                ->orderBy('id')
                ->get();

            $this->dedupeUnitsOnSameProperty($dbUnits, $expectedUnits, $dryRun, $summary);
            $this->removeExtraUnits($propertyIds, $expectedUnits, $dryRun, $summary);
            $this->syncStatusesFromRegister($expectedUnits, $dryRun, $summary);
            $this->reportMissingLeases($leaseRecords, $summary);
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

        $summary['db_units_after'] = $dryRun
            ? $summary['db_units_before'] - $summary['duplicate_units_removed'] - $summary['orphan_units_removed']
            : PropertyUnit::query()->withoutGlobalScopes()->whereIn('property_id', $propertyIds)->count();

        return $summary;
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function dedupeUnitsOnSameProperty(Collection $dbUnits, array $expectedUnits, bool $dryRun, array &$summary): void
    {
        foreach ($dbUnits->groupBy('property_id') as $propertyUnits) {
            $groups = [];
            foreach ($propertyUnits as $unit) {
                $label = PassionLegacyTextNormalizer::normalizeUnitLabel($unit->label);
                $groups[$label][] = $unit;
            }

            foreach ($groups as $label => $units) {
                if (count($units) < 2) {
                    continue;
                }

                $keeper = $this->pickKeeper($units, $expectedUnits);
                foreach ($units as $unit) {
                    if ($unit->id === $keeper->id) {
                        continue;
                    }

                    $this->relinkActiveLeases($unit, $keeper, $dryRun, $summary);
                    $this->removeUnitIfSafe($unit, $dryRun, $summary, 'duplicate_units_removed');
                }
            }
        }
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function relinkLeasesFromRegister(array $leaseRecords, array $expectedUnits, bool $dryRun, array &$summary): void
    {
        foreach ($leaseRecords as $record) {
            $property = $this->codeResolver->resolveOne($record['property_code']);
            if (! $property) {
                continue;
            }

            $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
            $canonical = $this->findCanonicalUnit($property->id, $label, $expectedUnits);
            if (! $canonical) {
                continue;
            }

            $tenant = PmTenant::query()
                ->withoutGlobalScopes()
                ->where('account_number', strtoupper($record['account_number']))
                ->first();

            if (! $tenant) {
                continue;
            }

            $lease = PmLease::query()
                ->withoutGlobalScopes()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmLease::STATUS_ACTIVE)
                ->with('units')
                ->first();

            if (! $lease) {
                continue;
            }

            $currentUnit = $lease->units->first();
            $target = $this->resolveTargetUnit($property->id, $record['unit_label'], $canonical, $expectedUnits, $dryRun, $summary);

            if ($currentUnit && $currentUnit->id === $target->id) {
                continue;
            }

            if (! $dryRun) {
                $lease->units()->sync([$target->id]);
                $target->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);

                if ($currentUnit && $currentUnit->id !== $target->id) {
                    $this->removeUnitIfSafe($currentUnit, false, $summary, 'orphan_units_removed');
                }
            }

            $summary['leases_relinked']++;
        }
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function resolveTargetUnit(
        int $propertyId,
        string $leaseLabel,
        PropertyUnit $canonical,
        array $expectedUnits,
        bool $dryRun,
        array &$summary,
    ): PropertyUnit {
        $expectedLabel = $this->expectedLabelForUnit($propertyId, $leaseLabel, $expectedUnits);
        if (! $expectedLabel) {
            return $canonical;
        }

        $existing = $this->findUnitOnProperty($propertyId, $expectedLabel);
        if ($existing) {
            return $existing;
        }

        if (! PassionLegacyTextNormalizer::unitLabelsMatch($canonical->label, $expectedLabel) && ! $dryRun) {
            $canonical->update(['label' => $expectedLabel]);
            $summary['labels_aligned']++;
        } elseif ($dryRun && ! PassionLegacyTextNormalizer::unitLabelsMatch($canonical->label, $expectedLabel)) {
            $summary['labels_aligned']++;
        }

        return $canonical;
    }

    /**
     * @param  list<int>  $propertyIds
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function removeExtraUnits(array $propertyIds, array $expectedUnits, bool $dryRun, array &$summary): void
    {
        $units = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->whereIn('property_id', $propertyIds)
            ->get();

        foreach ($units as $unit) {
            if (! $this->isExtraUnit($unit, $expectedUnits)) {
                continue;
            }

            if ($this->unitHasActiveLease($unit->id)) {
                $summary['warnings'][] = "Extra unit kept (active lease): {$unit->label} on property {$unit->property_id}";

                continue;
            }

            $this->removeUnitIfSafe($unit, $dryRun, $summary, 'orphan_units_removed');
        }
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function syncStatusesFromRegister(array $expectedUnits, bool $dryRun, array &$summary): void
    {
        foreach ($expectedUnits as $expected) {
            $unit = $this->findUnitOnProperty($expected['property_id'], $expected['label']);
            if (! $unit || $unit->status === $expected['status']) {
                continue;
            }

            if (! $dryRun) {
                $unit->update([
                    'status' => $expected['status'],
                    'vacant_since' => $expected['status'] === PropertyUnit::STATUS_VACANT
                        ? ($unit->vacant_since ?? now()->toDateString())
                        : null,
                ]);
            }

            $summary['statuses_synced']++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $leaseRecords
     * @param  array<string, mixed>  $summary
     */
    private function reportMissingLeases(array $leaseRecords, array &$summary): void
    {
        foreach ($leaseRecords as $record) {
            $tenant = PmTenant::query()
                ->withoutGlobalScopes()
                ->where('account_number', strtoupper($record['account_number']))
                ->first();

            if (! $tenant) {
                $summary['missing_active_leases'][] = $record['account_number'].' (no tenant)';

                continue;
            }

            $hasActive = PmLease::query()
                ->withoutGlobalScopes()
                ->where('pm_tenant_id', $tenant->id)
                ->where('status', PmLease::STATUS_ACTIVE)
                ->exists();

            if (! $hasActive) {
                $summary['missing_active_leases'][] = $record['account_number'];
            }
        }
    }

    /**
     * @param  array<int, PropertyUnit>  $units
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     */
    private function pickKeeper(array $units, array $expectedUnits): PropertyUnit
    {
        usort($units, function (PropertyUnit $a, PropertyUnit $b) use ($expectedUnits): int {
            $scoreA = $this->keeperScore($a, $expectedUnits);
            $scoreB = $this->keeperScore($b, $expectedUnits);

            return $scoreB <=> $scoreA ?: $a->id <=> $b->id;
        });

        return $units[0];
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     */
    private function keeperScore(PropertyUnit $unit, array $expectedUnits): int
    {
        $key = $unit->property_id.'|'.PassionLegacyTextNormalizer::normalizeUnitLabel($unit->label);
        $score = isset($expectedUnits[$key]) ? 100 : 0;

        if (! $this->isLikelyImportStub($unit)) {
            $score += 50;
        }

        if ($this->unitHasActiveLease($unit->id)) {
            $score += 20;
        }

        return $score;
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     */
    private function isExtraUnit(PropertyUnit $unit, array $expectedUnits): bool
    {
        $label = PassionLegacyTextNormalizer::normalizeUnitLabel($unit->label);

        foreach ($expectedUnits as $expected) {
            if ($expected['property_id'] !== $unit->property_id) {
                continue;
            }

            if (PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $label)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     */
    private function expectedLabelForUnit(int $propertyId, string $leaseLabel, array $expectedUnits): ?string
    {
        foreach ($expectedUnits as $expected) {
            if ($expected['property_id'] !== $propertyId) {
                continue;
            }

            if (PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $leaseLabel)) {
                return $expected['label'];
            }
        }

        return null;
    }

    private function findUnitOnProperty(int $propertyId, string $label): ?PropertyUnit
    {
        return PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get()
            ->first(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $label));
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     */
    private function findCanonicalUnit(int $propertyId, string $leaseLabel, array $expectedUnits): ?PropertyUnit
    {
        $bestExpected = null;
        $bestScore = -1;

        foreach ($expectedUnits as $expected) {
            if ($expected['property_id'] !== $propertyId) {
                continue;
            }

            if (! PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $leaseLabel)) {
                continue;
            }

            $score = 100 + strlen($expected['label']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestExpected = $expected;
            }
        }

        if ($bestExpected !== null) {
            $registerUnit = $this->findRegisterEnrichedUnit($propertyId, $bestExpected['label']);
            if ($registerUnit) {
                return $registerUnit;
            }

            $mislabeled = $this->findMislabeledUnit($propertyId, $bestExpected['label']);
            if ($mislabeled) {
                return $mislabeled;
            }
        }

        return $this->findUnitOnProperty($propertyId, $leaseLabel);
    }

    /**
     * @param  list<int>  $propertyIds
     * @param  array<string, array{property_id: int, label: string, status: string}>  $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function alignMislabeledUnits(array $propertyIds, array $expectedUnits, bool $dryRun, array &$summary): void
    {
        foreach ($expectedUnits as $expected) {
            $matches = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->where('property_id', $expected['property_id'])
                ->get()
                ->filter(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $unit->label));

            if ($matches->isEmpty()) {
                continue;
            }

            $keeper = $matches
                ->sortByDesc(fn (PropertyUnit $unit) => $this->keeperScore($unit, $expectedUnits))
                ->first();

            if ($keeper && ! PassionLegacyTextNormalizer::unitLabelsMatch($keeper->label, $expected['label'])) {
                $existing = $this->findUnitOnProperty($expected['property_id'], $expected['label']);
                if ($existing && $existing->id !== $keeper->id) {
                    $this->relinkActiveLeases($keeper, $existing, $dryRun, $summary);
                    $this->removeUnitIfSafe($keeper, $dryRun, $summary, 'duplicate_units_removed');
                } elseif (! $dryRun) {
                    $keeper->update(['label' => $expected['label']]);
                    $summary['labels_aligned']++;
                } elseif ($dryRun) {
                    $summary['labels_aligned']++;
                }
            }

            foreach ($matches as $unit) {
                if ($unit->id === $keeper?->id) {
                    continue;
                }

                $this->relinkActiveLeases($unit, $keeper, $dryRun, $summary);
                $this->removeUnitIfSafe($unit, $dryRun, $summary, 'duplicate_units_removed');
            }
        }
    }

    private function findMislabeledUnit(int $propertyId, string $registerLabel): ?PropertyUnit
    {
        return PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get()
            ->first(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::registerUnitLabelMatch($registerLabel, $unit->label));
    }

    private function findRegisterEnrichedUnit(int $propertyId, string $label): ?PropertyUnit
    {
        return PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get()
            ->filter(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $label))
            ->sortByDesc(fn (PropertyUnit $unit) => $this->isLikelyImportStub($unit) ? 0 : 1)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function relinkActiveLeases(PropertyUnit $from, PropertyUnit $to, bool $dryRun, array &$summary): void
    {
        $leases = PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $from->id))
            ->get();

        foreach ($leases as $lease) {
            if (! $dryRun) {
                $lease->units()->sync([$to->id]);
                $to->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
            }
            $summary['leases_relinked']++;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function removeUnitIfSafe(PropertyUnit $unit, bool $dryRun, array &$summary, string $counter): void
    {
        if ($this->unitHasActiveLease($unit->id)) {
            return;
        }

        if (! $dryRun) {
            DB::table('pm_lease_unit')->where('property_unit_id', $unit->id)->delete();
            $unit->delete();
        }

        $summary[$counter]++;
    }

    private function unitHasActiveLease(int $unitId): bool
    {
        return PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unitId))
            ->exists();
    }

    private function isLikelyImportStub(PropertyUnit $unit): bool
    {
        return $unit->legacy_area === null
            && $unit->floor === null
            && $unit->market_rent === null
            && $unit->available_from === null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPropertyRegister(): array
    {
        foreach ([
            storage_path('passion-legacy/property_register.txt'),
            storage_path('passion-legacy/property_register.pdf'),
        ] as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                return $this->propertyRegisterParser->parse($this->extractor->extract($path));
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }
}
