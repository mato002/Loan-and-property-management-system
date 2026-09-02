<?php

namespace App\Services\Property;

use App\Models\PropertyUnit;

final class PassionLegacyUnitImportService
{
    public function __construct(
        private PassionLegacyUnitRegisterParser $parser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private PassionPropertyCodeResolver $codeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false, bool $updateExisting = true): array
    {
        $records = $this->parser->parse($this->extractor->extract($path));

        $summary = [
            'dry_run' => $dryRun,
            'parsed' => count($records),
            'units_created' => 0,
            'units_updated' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if ($records === []) {
            $summary['errors'][] = 'No unit rows parsed.';

            return $summary;
        }

        $run = function () use ($records, $updateExisting, &$summary): void {
            foreach ($records as $index => $record) {
                try {
                    $this->importRecord($record, $updateExisting, $summary, $index + 1);
                } catch (\Throwable $e) {
                    $summary['errors'][] = 'Row '.($index + 1)." ({$record['unit_label']}): ".$e->getMessage();
                }
            }
        };

        if ($dryRun) {
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $run();
            } finally {
                \Illuminate\Support\Facades\DB::rollBack();
            }
        } else {
            \Illuminate\Support\Facades\DB::transaction($run);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function importRecord(array $record, bool $updateExisting, array &$summary, int $rowNum): void
    {
        $property = $this->codeResolver->resolveByName($record['property_name']);
        if (! $property) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['unit_label']}): property not found — {$record['property_name']}";

            return;
        }

        $label = PassionLegacyTextNormalizer::normalizeUnitLabel($record['unit_label']);
        $unit = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->get()
            ->first(fn (PropertyUnit $candidate) => PassionLegacyTextNormalizer::normalizeUnitLabel($candidate->label) === $label);

        $payload = array_filter([
            'property_id' => $property->id,
            'label' => $label,
            'unit_type' => PassionLegacyTextNormalizer::inferUnitType($record['unit_type_text'] ?? null, (int) $record['bedrooms'])
                ?? PropertyUnit::TYPE_APARTMENT,
            'bedrooms' => (int) $record['bedrooms'],
            'rent_amount' => $record['current_rent'],
            'market_rent' => $record['market_rent'],
            'status' => $record['status'],
            'available_from' => $record['available_from'],
            'legacy_area' => $record['legacy_area'],
            'floor' => $record['floor'],
            'furnished' => (bool) ($record['furnished'] ?? false),
            'vacant_since' => $record['status'] === PropertyUnit::STATUS_VACANT ? ($record['available_from'] ?? now()->toDateString()) : null,
        ], static fn ($value) => $value !== null);

        if ($unit) {
            if ($updateExisting) {
                $unit->update($payload);
                $summary['units_updated']++;
            }

            return;
        }

        PropertyUnit::query()->create($payload);
        $summary['units_created']++;
    }
}
