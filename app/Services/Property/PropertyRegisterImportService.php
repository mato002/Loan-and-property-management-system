<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PropertyRegisterImportService
{
    /** @var array<string, string> */
    private const HEADER_ALIASES = [
        'property' => 'property_name',
        'property_name' => 'property_name',
        'building' => 'property_name',
        'building_name' => 'property_name',
        'estate' => 'property_name',
        'property_code' => 'property_code',
        'code' => 'property_code',
        'ref' => 'property_code',
        'reference' => 'property_code',
        'old_reference' => 'property_code',
        'legacy_code' => 'property_code',
        'address' => 'address_line',
        'address_line' => 'address_line',
        'location' => 'address_line',
        'street' => 'address_line',
        'city' => 'city',
        'town' => 'city',
        'unit' => 'unit_label',
        'unit_label' => 'unit_label',
        'unit_no' => 'unit_label',
        'unit_number' => 'unit_label',
        'flat' => 'unit_label',
        'door' => 'unit_label',
        'shop' => 'unit_label',
        'unit_type' => 'unit_type',
        'type' => 'unit_type',
        'bedrooms' => 'bedrooms',
        'beds' => 'bedrooms',
        'bedroom' => 'bedrooms',
        'rent' => 'rent_amount',
        'rent_amount' => 'rent_amount',
        'monthly_rent' => 'rent_amount',
        'amount' => 'rent_amount',
        'status' => 'status',
        'occupancy' => 'status',
        'occupancy_status' => 'status',
        'rent_due_day' => 'rent_due_day',
        'due_day' => 'rent_due_day',
        'tenant' => 'tenant_name',
        'tenant_name' => 'tenant_name',
        'notes' => 'notes',
    ];

    /**
     * @return list<string>
     */
    public function templateColumns(): array
    {
        return [
            'property_name',
            'property_code',
            'address_line',
            'city',
            'unit_label',
            'unit_type',
            'bedrooms',
            'rent_amount',
            'status',
            'rent_due_day',
            'tenant_name',
            'notes',
        ];
    }

    public function templateCsv(): string
    {
        $columns = $this->templateColumns();
        $sample = [
            'Sunrise Apartments',
            'PASS-SUN-001',
            'Ngong Road, Nairobi',
            'Nairobi',
            'A1',
            'apartment',
            '2',
            '25000',
            'occupied',
            '5',
            'Jane Wanjiku',
            'Imported from legacy register',
        ];

        return implode(',', $columns)."\n".implode(',', $sample)."\n";
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     properties_created: int,
     *     properties_updated: int,
     *     units_created: int,
     *     units_updated: int,
     *     skipped: int,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false, bool $updateExisting = true): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'properties_created' => 0,
            'properties_updated' => 0,
            'units_created' => 0,
            'units_updated' => 0,
            'skipped' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $summary['errors'][] = 'Could not read the import file.';

            return $summary;
        }

        $header = fgetcsv($fh);
        if (! is_array($header) || $header === []) {
            fclose($fh);
            $summary['errors'][] = 'CSV is empty or missing a header row.';

            return $summary;
        }

        $colIndex = $this->mapHeaderIndexes($header);
        $missing = array_values(array_diff(['property_name', 'unit_label'], array_keys($colIndex)));
        if ($missing !== []) {
            fclose($fh);
            $summary['errors'][] = 'Missing required columns: '.implode(', ', $missing)
                .'. Expected at least property_name and unit_label (aliases: building, unit, flat, etc.).';

            return $summary;
        }

        $rowNum = 1;
        /** @var array<string, Property> $propertyCache */
        $propertyCache = [];

        $run = function () use (
            $fh,
            &$rowNum,
            $colIndex,
            $agentUserId,
            $updateExisting,
            &$summary,
            &$propertyCache,
        ): void {
            while (($row = fgetcsv($fh)) !== false) {
                $rowNum++;

                if (! is_array($row) || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                    $summary['skipped']++;

                    continue;
                }

                $propertyName = $this->cell($row, $colIndex, 'property_name');
                $unitLabel = $this->cell($row, $colIndex, 'unit_label');

                if ($propertyName === '') {
                    $summary['errors'][] = "Row {$rowNum}: property_name is required.";

                    continue;
                }
                if ($unitLabel === '') {
                    $summary['errors'][] = "Row {$rowNum}: unit_label is required.";

                    continue;
                }

                $propertyCode = $this->cell($row, $colIndex, 'property_code');
                $addressLine = $this->cell($row, $colIndex, 'address_line');
                $city = $this->cell($row, $colIndex, 'city');
                $unitType = $this->normalizeUnitType($this->cell($row, $colIndex, 'unit_type'));
                $bedrooms = $this->parseBedrooms($this->cell($row, $colIndex, 'bedrooms'), $unitType);
                $rentAmount = $this->parseMoney($this->cell($row, $colIndex, 'rent_amount'));
                $status = $this->normalizeStatus($this->cell($row, $colIndex, 'status'));
                $rentDueDay = $this->parseRentDueDay($this->cell($row, $colIndex, 'rent_due_day'));
                $tenantName = $this->cell($row, $colIndex, 'tenant_name');
                $notes = $this->cell($row, $colIndex, 'notes');

                if ($tenantName !== '') {
                    $summary['warnings'][] = "Row {$rowNum}: tenant \"{$tenantName}\" noted — import tenants separately after properties/units exist.";
                }

                $propertyKey = Str::lower($propertyCode !== '' ? 'code:'.$propertyCode : 'name:'.$propertyName);
                $property = $propertyCache[$propertyKey] ?? null;

                if (! $property) {
                    $property = $this->findProperty($propertyName, $propertyCode);
                }

                $propertyPayload = array_filter([
                    'name' => $propertyName,
                    'code' => $propertyCode !== '' ? $propertyCode : null,
                    'address_line' => $addressLine !== '' ? $addressLine : null,
                    'city' => $city !== '' ? $city : null,
                    'rent_due_day' => $rentDueDay,
                    'agent_user_id' => $agentUserId,
                    'management_status' => Property::MANAGEMENT_ACTIVE,
                ], static fn ($v) => $v !== null);

                if (! $property) {
                    if (($propertyPayload['code'] ?? null) === null) {
                        $propertyPayload['code'] = $this->generateUniquePropertyCode($propertyName);
                    }
                    $property = Property::query()->create($propertyPayload);
                    $propertyCache[$propertyKey] = $property;
                    $summary['properties_created']++;
                } elseif ($updateExisting) {
                    $updates = [];
                    foreach (['address_line', 'city', 'rent_due_day'] as $field) {
                        if (($propertyPayload[$field] ?? null) !== null && (string) ($property->{$field} ?? '') === '') {
                            $updates[$field] = $propertyPayload[$field];
                        }
                    }
                    if ($updates !== []) {
                        $property->update($updates);
                        $summary['properties_updated']++;
                    }
                    $propertyCache[$propertyKey] = $property->fresh();
                    $property = $propertyCache[$propertyKey];
                } else {
                    $propertyCache[$propertyKey] = $property;
                }

                $unit = PropertyUnit::query()
                    ->where('property_id', $property->id)
                    ->whereRaw('LOWER(label) = ?', [Str::lower($unitLabel)])
                    ->first();

                $unitPayload = [
                    'property_id' => $property->id,
                    'label' => $unitLabel,
                    'unit_type' => $unitType,
                    'bedrooms' => $bedrooms,
                    'rent_amount' => $rentAmount,
                    'status' => $status,
                ];

                if ($notes !== '' && ! $unit) {
                    $unitPayload['public_listing_description'] = $notes;
                }

                if (! $unit) {
                    PropertyUnit::query()->create($unitPayload);
                    $summary['units_created']++;
                } elseif ($updateExisting) {
                    $unit->update($unitPayload);
                    $summary['units_updated']++;
                } else {
                    $summary['skipped']++;
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
                fclose($fh);
            }
        } else {
            DB::transaction($run);
            fclose($fh);
        }

        return $summary;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapHeaderIndexes(array $header): array
    {
        $colIndex = [];
        foreach ($header as $i => $col) {
            $key = $this->normalizeHeaderKey((string) $col);
            if ($key === '') {
                continue;
            }
            $mapped = self::HEADER_ALIASES[$key] ?? $key;
            $colIndex[$mapped] = (int) $i;
        }

        return $colIndex;
    }

    private function normalizeHeaderKey(string $column): string
    {
        $key = Str::of($column)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $key;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $colIndex
     */
    private function cell(array $row, array $colIndex, string $key): string
    {
        if (! isset($colIndex[$key])) {
            return '';
        }

        return trim((string) ($row[$colIndex[$key]] ?? ''));
    }

    private function findProperty(string $name, string $code): ?Property
    {
        if ($code !== '') {
            $byCode = Property::query()->withoutGlobalScopes()->where('code', $code)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        return Property::query()
            ->withoutGlobalScopes()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();
    }

    private function generateUniquePropertyCode(string $name): string
    {
        $base = strtoupper(Str::of($name)->slug('')->substr(0, 6)->toString());
        $base = $base !== '' ? $base : 'PROP';

        for ($i = 0; $i < 25; $i++) {
            $candidate = $base.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! Property::query()->withoutGlobalScopes()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'PROP-'.strtoupper(Str::random(8));
    }

    private function normalizeStatus(string $status): string
    {
        $value = Str::lower(trim($status));
        if ($value === '') {
            return PropertyUnit::STATUS_VACANT;
        }

        if (in_array($value, ['occupied', 'tenanted', 'leased', 'active', 'rented', 'o'], true)) {
            return PropertyUnit::STATUS_OCCUPIED;
        }

        if (in_array($value, ['notice', 'on_notice', 'quitting', 'vacating', 'n'], true)) {
            return PropertyUnit::STATUS_NOTICE;
        }

        if (in_array($value, ['vacant', 'empty', 'available', 'v'], true)) {
            return PropertyUnit::STATUS_VACANT;
        }

        return PropertyUnit::STATUS_VACANT;
    }

    private function normalizeUnitType(string $type): string
    {
        $value = Str::lower(trim(str_replace([' ', '-'], '_', $type)));
        if ($value === '') {
            return PropertyUnit::TYPE_APARTMENT;
        }

        $map = [
            'apt' => PropertyUnit::TYPE_APARTMENT,
            'apartment' => PropertyUnit::TYPE_APARTMENT,
            'flat' => PropertyUnit::TYPE_APARTMENT,
            'single' => PropertyUnit::TYPE_SINGLE_ROOM,
            'single_room' => PropertyUnit::TYPE_SINGLE_ROOM,
            'bedsitter' => PropertyUnit::TYPE_BEDSITTER,
            'bedsit' => PropertyUnit::TYPE_BEDSITTER,
            'studio' => PropertyUnit::TYPE_STUDIO,
            'bungalow' => PropertyUnit::TYPE_BUNGALOW,
            'maisonette' => PropertyUnit::TYPE_MAISONETTE,
            'villa' => PropertyUnit::TYPE_VILLA,
            'townhouse' => PropertyUnit::TYPE_TOWNHOUSE,
            'commercial' => PropertyUnit::TYPE_COMMERCIAL,
            'shop' => PropertyUnit::TYPE_COMMERCIAL,
            'office' => PropertyUnit::TYPE_COMMERCIAL,
        ];

        return $map[$value] ?? PropertyUnit::TYPE_APARTMENT;
    }

    private function parseBedrooms(string $raw, string $unitType): int
    {
        if ($raw === '') {
            return in_array($unitType, [PropertyUnit::TYPE_BEDSITTER, PropertyUnit::TYPE_STUDIO, PropertyUnit::TYPE_SINGLE_ROOM], true)
                ? 0
                : 1;
        }

        if (! is_numeric($raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }

    private function parseMoney(string $raw): float
    {
        if ($raw === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw)) ?? '';

        return round(max(0, (float) $clean), 2);
    }

    private function parseRentDueDay(string $raw): ?int
    {
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $day = (int) $raw;
        if ($day < 1 || $day > 31) {
            return null;
        }

        return RentDueDayResolver::normalizeDueDay($day);
    }
}
