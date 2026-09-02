<?php

namespace App\Services\Property;

use App\Models\PropertyUnit;
use Illuminate\Support\Collection;

final class PassionLegacyUnitResolver
{
    /** @var array<string, array{property_id: int, label: string, status: string}>|null */
    private ?array $expectedUnits = null;

    public function __construct(
        private PassionLegacyUnitRegisterParser $unitParser,
        private PassionLegacyRegisterParser $propertyRegisterParser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private PassionPropertyCodeResolver $codeResolver,
    ) {}

    public function findOnProperty(int $propertyId, string $leaseLabel): ?PropertyUnit
    {
        $units = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get();

        if ($units->isEmpty()) {
            return null;
        }

        $exact = $units->first(
            fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $leaseLabel),
        );
        if ($exact) {
            return $this->preferRegisterEnriched($units, $exact, $leaseLabel);
        }

        $fuzzy = $units->first(
            fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::registerUnitLabelMatch($leaseLabel, $unit->label),
        );
        if ($fuzzy) {
            return $this->preferRegisterEnriched($units, $fuzzy, $leaseLabel);
        }

        foreach ($units as $unit) {
            foreach (PassionLegacyTextNormalizer::registerLabelParts($unit->label) as $part) {
                if (PassionLegacyTextNormalizer::registerUnitLabelMatch($leaseLabel, $part)) {
                    return $this->preferRegisterEnriched($units, $unit, $leaseLabel);
                }
            }
        }

        $expectedLabel = $this->expectedLabelForProperty($propertyId, $leaseLabel);
        if ($expectedLabel !== null) {
            $byExpected = $units->first(
                fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $expectedLabel)
                    || PassionLegacyTextNormalizer::registerUnitLabelMatch($leaseLabel, $unit->label),
            );
            if ($byExpected) {
                return $byExpected;
            }
        }

        return null;
    }

    public function expectedLabelForProperty(int $propertyId, string $leaseLabel): ?string
    {
        foreach ($this->expectedUnitsIndex() as $expected) {
            if ($expected['property_id'] !== $propertyId) {
                continue;
            }

            if (PassionLegacyTextNormalizer::registerUnitLabelMatch($expected['label'], $leaseLabel)) {
                return $expected['label'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, PropertyUnit>  $units
     */
    private function preferRegisterEnriched(Collection $units, PropertyUnit $match, string $leaseLabel): PropertyUnit
    {
        $expectedLabel = $this->expectedLabelForProperty($match->property_id, $leaseLabel);
        if ($expectedLabel === null) {
            return $match;
        }

        $canonical = $units
            ->filter(fn (PropertyUnit $unit) => PassionLegacyTextNormalizer::unitLabelsMatch($unit->label, $expectedLabel)
                || PassionLegacyTextNormalizer::registerUnitLabelMatch($unit->label, $expectedLabel))
            ->sortByDesc(fn (PropertyUnit $unit) => $this->isLikelyImportStub($unit) ? 0 : 1)
            ->first();

        return $canonical ?? $match;
    }

    private function isLikelyImportStub(PropertyUnit $unit): bool
    {
        return $unit->legacy_area === null
            && $unit->floor === null
            && $unit->market_rent === null
            && $unit->available_from === null;
    }

    /**
     * @return array<string, array{property_id: int, label: string, status: string}>
     */
    private function expectedUnitsIndex(): array
    {
        if ($this->expectedUnits !== null) {
            return $this->expectedUnits;
        }

        $this->expectedUnits = [];
        $path = storage_path('passion-legacy/property_unit_register.txt');
        if (! is_file($path)) {
            return $this->expectedUnits;
        }

        $propertyRegister = $this->loadPropertyRegister();
        $records = $this->unitParser->parse($this->extractor->extract($path));

        foreach ($records as $record) {
            $property = $this->codeResolver->resolveByNameViaRegister($record['property_name'], $propertyRegister);
            if (! $property) {
                continue;
            }

            $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
            $this->expectedUnits[$property->id.'|'.$label] = [
                'property_id' => $property->id,
                'label' => $label,
                'status' => $record['status'],
            ];
        }

        return $this->expectedUnits;
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
