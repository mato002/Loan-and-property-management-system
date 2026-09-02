<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\PropertyUnit;

final class PassionLegacyRegisterSpaceFillService
{
    public function __construct(
        private PassionLegacyRegisterParser $propertyRegisterParser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private PassionPropertyCodeResolver $codeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fillFromRegisterPath(string $path, bool $dryRun = false): array
    {
        $records = $this->propertyRegisterParser->parse($this->extractor->extract($path));

        $summary = [
            'dry_run' => $dryRun,
            'properties_checked' => 0,
            'target_spaces' => 0,
            'units_before' => PropertyUnit::query()->withoutGlobalScopes()->count(),
            'units_created' => 0,
            'warnings' => [],
        ];

        foreach ($records as $index => $record) {
            $property = $this->codeResolver->resolveOne($record['code']);
            if (! $property) {
                $summary['warnings'][] = 'Row '.($index + 1)." ({$record['code']}): property not found in DB.";

                continue;
            }

            $summary['properties_checked']++;
            $created = $this->fillPropertySpaces($property, $record, $dryRun);
            $summary['units_created'] += $created;
            $summary['target_spaces'] += (int) ($record['occupied_count'] ?? 0) + (int) ($record['vacant_count'] ?? 0);
        }

        $summary['units_after'] = $dryRun
            ? $summary['units_before'] + $summary['units_created']
            : PropertyUnit::query()->withoutGlobalScopes()->count();

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function fillPropertySpaces(Property $property, array $record, bool $dryRun): int
    {
        $target = (int) ($record['occupied_count'] ?? 0) + (int) ($record['vacant_count'] ?? 0);
        if ($target <= 0) {
            return 0;
        }

        $units = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->get();

        $shortfall = $target - $units->count();
        if ($shortfall <= 0) {
            return 0;
        }

        $occupiedShortfall = max(0, (int) ($record['occupied_count'] ?? 0) - $units->where('status', PropertyUnit::STATUS_OCCUPIED)->count());
        $vacantShortfall = $shortfall - $occupiedShortfall;

        $nextNumber = $this->nextGenericUnitNumber($units);
        $created = 0;
        $unitType = $this->mapCategoryToUnitType((string) ($record['category'] ?? ''));

        for ($i = 0; $i < $occupiedShortfall; $i++) {
            $this->createSpace($property->id, 'Unit '.$nextNumber, PropertyUnit::STATUS_OCCUPIED, $unitType, $dryRun);
            $nextNumber++;
            $created++;
        }

        for ($i = 0; $i < $vacantShortfall; $i++) {
            $this->createSpace($property->id, 'Unit '.$nextNumber, PropertyUnit::STATUS_VACANT, $unitType, $dryRun);
            $nextNumber++;
            $created++;
        }

        return $created;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PropertyUnit>  $units
     */
    private function nextGenericUnitNumber($units): int
    {
        $max = 0;
        foreach ($units as $unit) {
            if (preg_match('/^UNIT\s+(\d+)$/i', (string) $unit->label, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return max(1, $max + 1);
    }

    private function createSpace(int $propertyId, string $label, string $status, ?string $unitType, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        PropertyUnit::query()->create([
            'property_id' => $propertyId,
            'label' => PassionLegacyTextNormalizer::normalizeUnitLabel($label),
            'unit_type' => $unitType ?? PropertyUnit::TYPE_APARTMENT,
            'bedrooms' => 0,
            'rent_amount' => 0,
            'status' => $status,
            'vacant_since' => $status === PropertyUnit::STATUS_VACANT ? now()->toDateString() : null,
        ]);
    }

    private function mapCategoryToUnitType(string $category): ?string
    {
        $category = strtolower($category);

        return match (true) {
            str_contains($category, 'commercial') => PropertyUnit::TYPE_COMMERCIAL,
            str_contains($category, 'residential') => PropertyUnit::TYPE_APARTMENT,
            default => PropertyUnit::TYPE_APARTMENT,
        };
    }
}
