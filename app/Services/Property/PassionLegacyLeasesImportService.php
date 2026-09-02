<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Support\Facades\Schema;

final class PassionLegacyLeasesImportService
{
    public function __construct(
        private PassionLegacyLeasesRegisterParser $parser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
        private PassionPropertyCodeResolver $codeResolver,
        private LoanClientIdentifierNormalizer $normalizer,
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
            'tenants_created' => 0,
            'tenants_updated' => 0,
            'leases_created' => 0,
            'leases_updated' => 0,
            'units_linked' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if ($records === []) {
            $summary['errors'][] = 'No lease rows parsed.';

            return $summary;
        }

        $run = function () use ($records, $agentUserId, $updateExisting, &$summary): void {
            foreach ($records as $index => $record) {
                try {
                    $this->importRecord($record, $agentUserId, $updateExisting, $summary, $index + 1);
                } catch (\Throwable $e) {
                    $summary['errors'][] = 'Row '.($index + 1)." ({$record['account_number']}): ".$e->getMessage();
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
    private function importRecord(array $record, int $agentUserId, bool $updateExisting, array &$summary, int $rowNum): void
    {
        $property = $this->codeResolver->resolveOne($record['property_code']);
        if (! $property) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['account_number']}): property {$record['property_code']} not found.";

            return;
        }

        $unit = $this->resolveUnit($property->id, $record['unit_label'], $record, $summary, $rowNum);
        if (! $unit) {
            return;
        }

        $tenant = $this->resolveTenant($record, $agentUserId, $updateExisting, $summary);
        $lease = PmLease::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenant->id)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unit->id))
            ->first();

        $payload = array_filter([
            'pm_tenant_id' => $tenant->id,
            'start_date' => $record['lease_start'] ?? now()->toDateString(),
            'end_date' => $record['lease_end'],
            'monthly_rent' => $record['monthly_rent'] ?? 0,
            'deposit_amount' => 0,
            'status' => PmLease::STATUS_ACTIVE,
            'lease_variation_type' => $record['lease_variation_type'],
            'lease_period_days' => $record['lease_period_days'],
            'days_to_expire' => $record['days_to_expire'],
            'escalation_review_start' => $record['escalation_review_start'],
        ], static fn ($value) => $value !== null && $value !== '');

        if ($lease) {
            if ($updateExisting) {
                $lease->update($payload);
                $summary['leases_updated']++;
            }
        } else {
            $lease = PmLease::query()->create($payload);
            $summary['leases_created']++;
        }

        if (! $lease->units()->where('property_units.id', $unit->id)->exists()) {
            $lease->units()->sync([$unit->id]);
            $summary['units_linked']++;
        }

        $unit->update([
            'status' => PropertyUnit::STATUS_OCCUPIED,
            'rent_amount' => $record['monthly_rent'] ?? $unit->rent_amount,
            'vacant_since' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveUnit(int $propertyId, string $unitLabel, array $record, array &$summary, int $rowNum): ?PropertyUnit
    {
        $normalized = PassionLegacyTextNormalizer::normalizeUnitLabel($unitLabel);
        $unit = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->get()
            ->first(fn (PropertyUnit $candidate) => PassionLegacyTextNormalizer::normalizeUnitLabel($candidate->label) === $normalized);

        if ($unit) {
            return $unit;
        }

        $summary['warnings'][] = "Row {$rowNum}: unit {$unitLabel} not found — creating stub.";

        return PropertyUnit::query()->create([
            'property_id' => $propertyId,
            'label' => $normalized,
            'unit_type' => PropertyUnit::TYPE_APARTMENT,
            'bedrooms' => 0,
            'rent_amount' => $record['monthly_rent'] ?? 0,
            'status' => PropertyUnit::STATUS_OCCUPIED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveTenant(array $record, int $agentUserId, bool $updateExisting, array &$summary): PmTenant
    {
        $accountNumber = strtoupper((string) $record['account_number']);
        $phone = isset($record['phone']) ? $this->normalizer->normalizePhone((string) $record['phone']) : null;

        $existing = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if (! $existing && $phone) {
            $existing = PmTenant::query()->withoutGlobalScopes()->where('phone', $phone)->first();
        }

        $payload = array_filter([
            'name' => $record['tenant_name'],
            'phone' => $phone,
            'account_number' => $accountNumber,
            'opening_arrears_amount' => $record['account_balance'],
            'opening_arrears_as_of' => now()->toDateString(),
            'opening_arrears_status' => ((float) ($record['account_balance'] ?? 0)) > 0 ? 'pending' : 'none',
            'agent_user_id' => Schema::hasColumn('pm_tenants', 'agent_user_id') ? $agentUserId : null,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($existing) {
            if ($updateExisting) {
                $existing->update($payload);
                $summary['tenants_updated']++;
            }

            return $existing;
        }

        $tenant = PmTenant::query()->create($payload);
        $summary['tenants_created']++;

        return $tenant;
    }
}
