<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PassionLegacyRegisterImportService
{
    public function __construct(
        private PassionLegacyRegisterParser $parser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private LoanClientIdentifierNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     parsed: int,
     *     properties_created: int,
     *     properties_updated: int,
     *     units_created: int,
     *     landlords_created: int,
     *     landlords_linked: int,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        bool $dryRun = false,
        bool $updateExisting = true,
        bool $withUnits = false,
        bool $withLandlords = false,
        bool $withCommission = true,
    ): array {
        $text = $this->extractor->extract($path);
        $records = $this->parser->parse($text);

        $summary = [
            'dry_run' => $dryRun,
            'parsed' => count($records),
            'properties_created' => 0,
            'properties_updated' => 0,
            'units_created' => 0,
            'landlords_created' => 0,
            'landlords_linked' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if ($records === []) {
            $summary['errors'][] = 'No property rows were parsed from the register file.';

            return $summary;
        }

        $run = function () use (
            $records,
            $agentUserId,
            $updateExisting,
            $withUnits,
            $withLandlords,
            $withCommission,
            &$summary,
        ): void {
            foreach ($records as $index => $record) {
                $rowNum = $index + 1;

                try {
                    $this->importRecord(
                        $record,
                        $agentUserId,
                        $updateExisting,
                        $withUnits,
                        $withLandlords,
                        $withCommission,
                        $summary,
                        $rowNum,
                    );
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Row {$rowNum} ({$record['code']}): ".$e->getMessage();
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

        return $summary;
    }

    /**
     * Property-level CSV (phase 1 — one row per property, no units or landlords).
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function recordsToPropertiesCsv(array $records): string
    {
        $columns = [
            'property_name',
            'property_code',
            'address_line',
            'city',
            'category',
            'commission_percent',
            'occupied_count',
            'vacant_count',
            'field_officer',
            'date_acquired',
        ];

        $lines = [implode(',', $columns)];

        foreach ($records as $record) {
            $lines[] = $this->csvRow([
                'property_name' => (string) $record['name'],
                'property_code' => (string) $record['code'],
                'address_line' => trim((string) ($record['location'] ?? '')),
                'city' => $this->cityFromLocation((string) ($record['location'] ?? '')),
                'category' => (string) ($record['category'] ?? ''),
                'commission_percent' => (string) ($record['commission_percent'] ?? ''),
                'occupied_count' => (string) ($record['occupied_count'] ?? 0),
                'vacant_count' => (string) ($record['vacant_count'] ?? 0),
                'field_officer' => (string) ($record['field_officer'] ?? ''),
                'date_acquired' => (string) ($record['date_acquired'] ?? ''),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Expand Passion property-level rows into unit-level CSV (later phase — tenants/units register).
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function recordsToCsv(array $records): string
    {
        $columns = [
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

        $lines = [implode(',', $columns)];

        foreach ($records as $record) {
            $unitType = $this->mapCategoryToUnitType((string) ($record['category'] ?? ''));
            $city = $this->cityFromLocation((string) ($record['location'] ?? ''));
            $address = trim((string) ($record['location'] ?? ''));
            $notes = $this->buildNotes($record);

            $units = $this->buildUnitLabels(
                (int) ($record['occupied_count'] ?? 0),
                (int) ($record['vacant_count'] ?? 0),
            );

            foreach ($units as $unit) {
                $lines[] = $this->csvRow([
                    'property_name' => (string) $record['name'],
                    'property_code' => (string) $record['code'],
                    'address_line' => $address,
                    'city' => $city,
                    'unit_label' => $unit['label'],
                    'unit_type' => $unitType,
                    'bedrooms' => '1',
                    'rent_amount' => '0',
                    'status' => $unit['status'],
                    'rent_due_day' => '',
                    'tenant_name' => '',
                    'notes' => $notes,
                ]);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function recordsToSql(array $records, int $agentUserId, bool $withUnits = false): string
    {
        $now = now()->format('Y-m-d H:i:s');
        $chunks = [
            '-- Passion legacy property register — phase 1 (properties only)',
            '-- Review before running. Replace @agent_user_id if needed.',
            'SET @agent_user_id := '.max(1, $agentUserId).';',
            'START TRANSACTION;',
            '',
        ];

        foreach ($records as $record) {
            $code = $this->sqlQuote((string) $record['code']);
            $name = $this->sqlQuote((string) $record['name']);
            $city = $this->sqlQuote($this->cityFromLocation((string) ($record['location'] ?? '')));
            $address = $this->sqlQuote(trim((string) ($record['location'] ?? '')));

            $chunks[] = "-- Property {$record['code']}: {$record['name']}";
            $chunks[] = "INSERT INTO properties (name, code, address_line, city, agent_user_id, management_status, created_at, updated_at)";
            $chunks[] = "SELECT {$name}, {$code}, {$address}, {$city}, @agent_user_id, 'active', '{$now}', '{$now}'";
            $chunks[] = "WHERE NOT EXISTS (SELECT 1 FROM properties WHERE code = {$code});";
            $chunks[] = "SET @property_id := (SELECT id FROM properties WHERE code = {$code} LIMIT 1);";

            if ($withUnits) {
                $units = $this->buildUnitLabels(
                    (int) ($record['occupied_count'] ?? 0),
                    (int) ($record['vacant_count'] ?? 0),
                );

                foreach ($units as $unit) {
                    $label = $this->sqlQuote($unit['label']);
                    $status = $this->sqlQuote($unit['status']);
                    $chunks[] = "INSERT INTO property_units (property_id, label, unit_type, bedrooms, rent_amount, status, created_at, updated_at)";
                    $chunks[] = "SELECT @property_id, {$label}, 'apartment', 1, 0, {$status}, '{$now}', '{$now}'";
                    $chunks[] = "WHERE @property_id IS NOT NULL";
                    $chunks[] = "  AND NOT EXISTS (SELECT 1 FROM property_units WHERE property_id = @property_id AND label = {$label});";
                }
            }

            if (($record['commission_percent'] ?? null) !== null) {
                $pct = (float) $record['commission_percent'];
                $chunks[] = "-- Commission {$pct}%: set via artisan import or property_portal_settings JSON after import.";
            }

            $chunks[] = '';
        }

        $chunks[] = 'COMMIT;';
        $chunks[] = '-- Landlords: import from landlords register in phase 2.';
        $chunks[] = '-- Units/tenants: import from tenants register in phase 3.';

        return implode("\n", $chunks)."\n";
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function importRecord(
        array $record,
        int $agentUserId,
        bool $updateExisting,
        bool $withUnits,
        bool $withLandlords,
        bool $withCommission,
        array &$summary,
        int $rowNum,
    ): void {
        $property = Property::query()
            ->withoutGlobalScopes()
            ->where('code', $record['code'])
            ->first();

        $city = $this->cityFromLocation((string) ($record['location'] ?? ''));
        $payload = [
            'name' => (string) $record['name'],
            'code' => (string) $record['code'],
            'address_line' => trim((string) ($record['location'] ?? '')) ?: null,
            'city' => $city !== '' ? $city : null,
            'agent_user_id' => $agentUserId,
            'management_status' => Property::MANAGEMENT_ACTIVE,
        ];

        if (! $property) {
            $property = Property::query()->create($payload);
            $summary['properties_created']++;
        } elseif ($updateExisting) {
            $updates = [];
            foreach (['name', 'address_line', 'city'] as $field) {
                if (($payload[$field] ?? null) === null) {
                    continue;
                }
                if ($field === 'name' || trim((string) ($property->{$field} ?? '')) === '') {
                    $updates[$field] = $payload[$field];
                }
            }
            if ($updates !== []) {
                $property->update($updates);
                $summary['properties_updated']++;
            }
        }

        if ($withCommission && ($record['commission_percent'] ?? null) !== null) {
            $this->setPropertyCommissionOverride((int) $property->id, (float) $record['commission_percent']);
        }

        if ($withLandlords) {
            $landlord = $this->resolveLandlord($record, $agentUserId, $summary);
            if ($landlord && ! $property->landlords()->where('users.id', $landlord->id)->exists()) {
                $property->landlords()->attach($landlord->id, ['ownership_percent' => 100]);
                $summary['landlords_linked']++;
            }
        }

        if (! $withUnits) {
            return;
        }

        if (($record['occupied_count'] ?? 0) === 0 && ($record['vacant_count'] ?? 0) === 0) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['code']}): no occupied/vacant counts found.";
        }

        $units = $this->buildUnitLabels(
            (int) ($record['occupied_count'] ?? 0),
            (int) ($record['vacant_count'] ?? 0),
        );

        if ($units === []) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['code']}): zero units — add units manually or fix occupied/vacant counts.";

            return;
        }

        $unitType = $this->mapCategoryToUnitType((string) ($record['category'] ?? ''));
        $notes = $this->buildNotes($record);

        foreach ($units as $unit) {
            $exists = PropertyUnit::query()
                ->where('property_id', $property->id)
                ->whereRaw('LOWER(label) = ?', [Str::lower($unit['label'])])
                ->exists();

            if ($exists) {
                if (! $updateExisting) {
                    continue;
                }

                PropertyUnit::query()
                    ->where('property_id', $property->id)
                    ->whereRaw('LOWER(label) = ?', [Str::lower($unit['label'])])
                    ->update([
                        'status' => $unit['status'],
                        'unit_type' => $unitType,
                    ]);

                continue;
            }

            PropertyUnit::query()->create([
                'property_id' => $property->id,
                'label' => $unit['label'],
                'unit_type' => $unitType,
                'bedrooms' => 1,
                'rent_amount' => 0,
                'status' => $unit['status'],
                'public_listing_description' => $notes !== '' ? $notes : null,
            ]);
            $summary['units_created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveLandlord(array $record, int $agentUserId, array &$summary): ?User
    {
        $email = isset($record['email']) ? Str::lower(trim((string) $record['email'])) : null;
        $phone = isset($record['phone']) ? $this->normalizer->normalizePhone((string) $record['phone']) : null;
        $name = trim((string) ($record['landlord_name'] ?? ''));

        if ($email === '' && $phone === '' && $name === '') {
            return null;
        }

        $existing = null;
        if ($email !== null && $email !== '') {
            $existing = User::query()->where('email', $email)->first();
        }
        if (! $existing && $phone !== null && $phone !== '') {
            $existing = User::query()->where('phone', $phone)->first();
        }

        if ($existing) {
            return $existing;
        }

        if ($email === null && $phone === null) {
            return null;
        }

        $attributes = [
            'name' => $name !== '' ? $name : 'Landlord',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(Str::password(16, symbols: false)),
            'property_portal_role' => 'landlord',
            'email_verified_at' => $email !== null ? now() : null,
        ];

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $attributes['agent_user_id'] = $agentUserId;
        }

        $landlord = User::query()->create($attributes);
        $summary['landlords_created']++;

        return $landlord;
    }

    /**
     * @return list<array{label: string, status: string}>
     */
    private function buildUnitLabels(int $occupied, int $vacant): array
    {
        $units = [];
        $counter = 1;

        for ($i = 0; $i < $occupied; $i++) {
            $units[] = [
                'label' => 'Unit '.$counter,
                'status' => PropertyUnit::STATUS_OCCUPIED,
            ];
            $counter++;
        }

        for ($i = 0; $i < $vacant; $i++) {
            $units[] = [
                'label' => 'Unit '.$counter,
                'status' => PropertyUnit::STATUS_VACANT,
            ];
            $counter++;
        }

        return $units;
    }

    private function mapCategoryToUnitType(string $category): string
    {
        return match ($category) {
            'commercial' => PropertyUnit::TYPE_COMMERCIAL,
            'commercial_residential' => PropertyUnit::TYPE_APARTMENT,
            'residential' => PropertyUnit::TYPE_APARTMENT,
            default => PropertyUnit::TYPE_APARTMENT,
        };
    }

    private function cityFromLocation(string $location): string
    {
        if (stripos($location, 'NAKURU') !== false) {
            return 'Nakuru';
        }

        return trim($location) !== '' ? trim($location) : 'Kenya';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function buildNotes(array $record): string
    {
        $parts = array_filter([
            isset($record['field_officer']) ? 'Field officer: '.$record['field_officer'] : null,
            isset($record['date_acquired']) ? 'Acquired: '.$record['date_acquired'] : null,
            ! empty($record['lpf_exempted']) ? 'LPF exempted' : null,
            'Imported from Passion legacy register',
        ]);

        return implode(' | ', $parts);
    }

    private function setPropertyCommissionOverride(int $propertyId, ?float $percent): void
    {
        $raw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($raw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        if ($percent === null) {
            unset($overrides[(string) $propertyId]);
        } else {
            $overrides[(string) $propertyId] = max(0.0, round($percent, 2));
        }

        PropertyPortalSetting::setValue(
            'commission_property_overrides_json',
            json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function csvRow(array $row): string
    {
        return implode(',', array_map(function (string $value): string {
            $value = str_replace('"', '""', $value);

            return '"'.$value.'"';
        }, $row));
    }

    private function sqlQuote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
