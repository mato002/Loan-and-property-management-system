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
            'units_created' => 0,
            'statuses_synced' => 0,
            'missing_active_leases' => [],
            'warnings' => [],
        ];

        /** @var array<string, array{property_id: int, label: string, status: string, rent_amount: float, market_rent: ?float, bedrooms: int, unit_type: string, available_from: ?string, legacy_area: ?float, floor: ?string, furnished: bool}> $expectedUnits */
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
                'rent_amount' => PassionLegacyTextNormalizer::resolveImportedRentAmount(
                    $record['market_rent'] ?? null,
                    $record['current_rent'] ?? null,
                    $record['status'],
                ),
                'market_rent' => isset($record['market_rent']) ? (float) $record['market_rent'] : null,
                'bedrooms' => (int) ($record['bedrooms'] ?? 0),
                'unit_type' => PassionLegacyTextNormalizer::inferUnitType($record['unit_type_text'] ?? null, (int) ($record['bedrooms'] ?? 0))
                    ?? PropertyUnit::TYPE_APARTMENT,
                'available_from' => $record['available_from'] ?? null,
                'legacy_area' => isset($record['legacy_area']) ? (float) $record['legacy_area'] : null,
                'floor' => $record['floor'] ?? null,
                'furnished' => (bool) ($record['furnished'] ?? false),
            ];
        }
        $summary['expected_units'] = count($expectedUnits);

        $run = function () use ($propertyIds, $expectedUnits, $leaseRecords, $dryRun, &$summary): void {
            $this->alignMislabeledUnits($propertyIds, $expectedUnits, $dryRun, $summary);

            $dbUnits = PropertyUnit::query()
                ->withoutGlobalScopes()
                ->whereIn('property_id', $propertyIds)
                ->orderBy('id')
                ->get();

            $this->dedupeUnitsOnSameProperty($dbUnits, $expectedUnits, $dryRun, $summary);
            $this->createMissingUnitsFromRegister($expectedUnits, $dryRun, $summary);
            $this->relinkLeasesFromRegister($leaseRecords, $expectedUnits, $dryRun, $summary);
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
            ? $summary['db_units_before'] - $summary['duplicate_units_removed'] - $summary['orphan_units_removed'] + $summary['units_created']
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
            $expectedLabel = $this->expectedLabelForUnit($property->id, $record['unit_label'], $expectedUnits)
                ?? PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
            $target = $this->findUnitOnProperty($property->id, $expectedLabel) ?? $target;
            $target = $target ? PropertyUnit::query()->withoutGlobalScopes()->find($target->id) : null;

            if (! $target) {
                $summary['warnings'][] = "Lease relink skipped — target unit missing for {$record['property_code']} / {$record['unit_label']}";

                continue;
            }

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
                    $this->vacateUnitIfNoActiveLease($currentUnit);
                }
            }

            $summary['leases_relinked']++;
        }
    }

    /**
     * @param  array<string, array{property_id: int, label: string, status: string, rent_amount: float, market_rent: ?float, bedrooms: int, unit_type: string, available_from: ?string, legacy_area: ?float, floor: ?string, furnished: bool}> $expectedUnits
     * @param  array<string, mixed>  $summary
     */
    private function createMissingUnitsFromRegister(array $expectedUnits, bool $dryRun, array &$summary): void
    {
        foreach ($expectedUnits as $expected) {
            if ($this->findUnitOnProperty($expected['property_id'], $expected['label'])) {
                continue;
            }

            if ($dryRun) {
                $summary['units_created']++;

                continue;
            }

            PropertyUnit::query()->create([
                'property_id' => $expected['property_id'],
                'label' => $expected['label'],
                'unit_type' => $expected['unit_type'],
                'bedrooms' => $expected['bedrooms'],
                'rent_amount' => $expected['rent_amount'],
                'market_rent' => $expected['market_rent'],
                'status' => $expected['status'],
                'available_from' => $expected['available_from'],
                'legacy_area' => $expected['legacy_area'],
                'floor' => $expected['floor'],
                'furnished' => $expected['furnished'],
                'vacant_since' => $expected['status'] === PropertyUnit::STATUS_VACANT
                    ? ($expected['available_from'] ?? now()->toDateString())
                    : null,
            ]);
            $summary['units_created']++;
        }
    }

    private function vacateUnitIfNoActiveLease(PropertyUnit $unit): void
    {
        if ($this->unitHasActiveLease($unit->id)) {
            return;
        }

        $unit->update([
            'status' => PropertyUnit::STATUS_VACANT,
            'vacant_since' => $unit->vacant_since ?? now()->toDateString(),
        ]);
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
            if (! $unit) {
                continue;
            }

            $updates = [];
            if ($unit->status !== $expected['status']) {
                $updates['status'] = $expected['status'];
                $updates['vacant_since'] = $expected['status'] === PropertyUnit::STATUS_VACANT
                    ? ($unit->vacant_since ?? now()->toDateString())
                    : null;
            }

            if ((float) $unit->rent_amount !== (float) $expected['rent_amount']) {
                $updates['rent_amount'] = $expected['rent_amount'];
            }

            if ($expected['market_rent'] !== null && (float) ($unit->market_rent ?? 0) !== (float) $expected['market_rent']) {
                $updates['market_rent'] = $expected['market_rent'];
            }

            if (! PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $expected['label'])) {
                $updates['label'] = $expected['label'];
                $summary['labels_aligned']++;
            }

            if ($updates === []) {
                continue;
            }

            if (! $dryRun) {
                $unit->update($updates);
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
        $bestLabel = null;
        $bestScore = -1;

        foreach ($expectedUnits as $expected) {
            if ($expected['property_id'] !== $propertyId) {
                continue;
            }

            if (! PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $leaseLabel)) {
                continue;
            }

            $score = strlen($expected['label']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLabel = $expected['label'];
            }
        }

        return $bestLabel;
    }

    private function findUnitOnProperty(int $propertyId, string $label): ?PropertyUnit
    {
        return PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get()
            ->first(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::registerUnitLabelMatch($label, $unit->label));
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
            $registerUnit = $this->findUnitOnProperty($propertyId, $bestExpected['label']);
            if ($registerUnit) {
                return $registerUnit;
            }

            $mislabeled = $this->findMislabeledUnit($propertyId, $bestExpected['label']);
            if ($mislabeled && $this->findUnitOnProperty($propertyId, $bestExpected['label']) === null) {
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
            $target = PropertyUnit::query()->withoutGlobalScopes()->find($to->id);
            if (! $target) {
                $summary['warnings'][] = "Lease relink skipped during dedupe — target unit {$to->id} missing";

                continue;
            }

            if (! $dryRun) {
                $lease->units()->sync([$target->id]);
                $target->update([
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
