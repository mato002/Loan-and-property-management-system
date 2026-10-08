<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PassionTerminatedLeasesImportService
{
    public function __construct(
        private PassionTerminatedLeasesSpreadsheetParser $parser,
        private PassionPropertyCodeResolver $codeResolver,
        private PassionLegacyUnitResolver $unitResolver,
        private LoanClientIdentifierNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false, bool $updateExisting = true): array
    {
        $records = $this->parser->parse($path);
        $summary = [
            'dry_run' => $dryRun,
            'parsed' => count($records),
            'tenants_created' => 0,
            'tenants_updated' => 0,
            'leases_created' => 0,
            'leases_updated' => 0,
            'leases_terminated' => 0,
            'units_linked' => 0,
            'units_vacated' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if ($records === []) {
            $summary['errors'][] = 'No terminated lease rows parsed.';

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
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function importRecord(array $record, int $agentUserId, bool $updateExisting, array &$summary, int $rowNum): void
    {
        $property = $this->codeResolver->resolveOne($record['property_code']);
        if (! $property && $record['property_name'] !== '') {
            $property = $this->codeResolver->resolveByName((string) $record['property_name']);
        }
        if (! $property) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['account_number']}): property {$record['property_code']} not found.";

            return;
        }

        $unit = $this->resolveUnit((int) $property->id, (string) $record['unit_label'], $record, $summary, $rowNum);
        if (! $unit) {
            return;
        }

        $tenant = $this->resolveTenant($record, $agentUserId, $updateExisting, $summary);

        $lease = PmLease::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenant->id)
            ->whereHas('units', fn ($query) => $query->where('property_units.id', $unit->id))
            ->orderByRaw("case when status = 'terminated' then 0 when status = 'active' then 1 else 2 end")
            ->orderByDesc('id')
            ->first();

        $wasActive = $lease && $lease->status === PmLease::STATUS_ACTIVE;
        $balanceNote = $this->balanceNote($record);
        $payload = [
            'pm_tenant_id' => $tenant->id,
            'status' => PmLease::STATUS_TERMINATED,
            'monthly_rent' => $record['monthly_rent'] ?? 0,
        ];
        if (! empty($record['lease_start'])) {
            $payload['start_date'] = $record['lease_start'];
        } elseif (! $lease) {
            $payload['start_date'] = now()->toDateString();
        }
        if (! empty($record['lease_end'])) {
            $payload['end_date'] = $record['lease_end'];
        } elseif ($wasActive) {
            $payload['end_date'] = now()->toDateString();
        }

        if ($lease) {
            if ($updateExisting) {
                if ($balanceNote !== '' && ! str_contains((string) $lease->terms_summary, 'Terminated register')) {
                    $payload['terms_summary'] = trim((string) $lease->terms_summary."\n".$balanceNote);
                }
                $lease->update($payload);
                $summary[$wasActive ? 'leases_terminated' : 'leases_updated']++;
            }
        } else {
            $payload['terms_summary'] = $balanceNote;
            $payload['deposit_amount'] = 0;
            $lease = PmLease::query()->create($payload);
            $summary['leases_created']++;
        }

        if (! $lease->units()->where('property_units.id', $unit->id)->exists()) {
            $lease->units()->syncWithoutDetaching([$unit->id]);
            $summary['units_linked']++;
        }

        if ($this->vacateUnitIfFree((int) $unit->id, (int) $lease->id)) {
            $summary['units_vacated']++;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveUnit(int $propertyId, string $unitLabel, array $record, array &$summary, int $rowNum): ?PropertyUnit
    {
        if ($unitLabel === '') {
            $summary['warnings'][] = "Row {$rowNum} ({$record['account_number']}): unit is blank.";

            return null;
        }

        $canonicalLabel = PassionLegacyTextNormalizer::canonicalizeLeaseUnitLabel($unitLabel);
        $unit = $this->unitResolver->findBestOnProperty($propertyId, $canonicalLabel);
        if ($unit) {
            return $unit;
        }

        $expectedLabel = $this->unitResolver->expectedLabelForProperty($propertyId, $canonicalLabel);
        $normalized = PassionLegacyTextNormalizer::normalizeUnitLabel($expectedLabel ?? $canonicalLabel);
        $summary['warnings'][] = "Row {$rowNum}: unit {$unitLabel} not found — creating a vacant stub.";

        return PropertyUnit::query()->create([
            'property_id' => $propertyId,
            'label' => $normalized,
            'unit_type' => PropertyUnit::TYPE_APARTMENT,
            'bedrooms' => 0,
            'rent_amount' => $record['monthly_rent'] ?? 0,
            'status' => PropertyUnit::STATUS_VACANT,
            'vacant_since' => now()->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveTenant(array $record, int $agentUserId, bool $updateExisting, array &$summary): PmTenant
    {
        $accountNumber = strtoupper((string) $record['account_number']);
        $phone = isset($record['phone']) ? $this->normalizer->normalizePhone((string) $record['phone']) : '';
        $phone = $phone !== '' ? $phone : null;
        $email = $record['email'] ?? null;

        $existing = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if ($existing) {
            if ($updateExisting) {
                $payload = [];
                if (PassionLegacyTextNormalizer::isPlaceholderTenantName($existing->name)) {
                    $payload['name'] = $record['tenant_name'];
                }
                if ($phone && trim((string) $existing->phone) === '' && ! $this->phoneTaken($phone, $agentUserId, (int) $existing->id)) {
                    $payload['phone'] = $phone;
                }
                if ($email && trim((string) $existing->email) === '' && ! $this->emailTaken($email, (int) $existing->id)) {
                    $payload['email'] = $email;
                }
                $note = $this->balanceNote($record);
                if ($note !== '' && ! str_contains((string) $existing->notes, 'Terminated register')) {
                    $payload['notes'] = trim((string) $existing->notes."\n".$note);
                }
                if ($payload !== []) {
                    $existing->update($payload);
                    $summary['tenants_updated']++;
                }
            }

            return $existing;
        }

        if ($phone && $this->phoneTaken($phone, $agentUserId, null)) {
            $phone = null;
            $summary['warnings'][] = "{$accountNumber}: phone already used by another tenant — imported without phone.";
        }
        if ($email && $this->emailTaken($email, null)) {
            $email = null;
            $summary['warnings'][] = "{$accountNumber}: email already used by another tenant — imported without email.";
        }

        $tenant = PmTenant::query()->create(array_filter([
            'name' => $record['tenant_name'],
            'phone' => $phone,
            'email' => $email,
            'account_number' => $accountNumber,
            'agent_user_id' => Schema::hasColumn('pm_tenants', 'agent_user_id') ? $agentUserId : null,
            'notes' => $this->balanceNote($record),
        ], static fn ($value) => $value !== null && $value !== ''));
        $summary['tenants_created']++;

        return $tenant;
    }

    private function vacateUnitIfFree(int $unitId, int $excludeLeaseId): bool
    {
        $stillOccupied = PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->where('id', '!=', $excludeLeaseId)
            ->whereHas('units', fn ($query) => $query->where('property_units.id', $unitId))
            ->exists();

        if ($stillOccupied) {
            return false;
        }

        $updated = PropertyUnit::query()
            ->where('id', $unitId)
            ->where('status', '!=', PropertyUnit::STATUS_VACANT)
            ->update([
                'status' => PropertyUnit::STATUS_VACANT,
                'vacant_since' => now()->toDateString(),
            ]);

        return $updated > 0;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function balanceNote(array $record): string
    {
        if (! isset($record['account_balance']) || $record['account_balance'] === null) {
            return 'Terminated register.';
        }

        return 'Terminated register. Closing balance: '.number_format((float) $record['account_balance'], 2, '.', ',');
    }

    private function phoneTaken(string $phone, int $agentUserId, ?int $exceptId): bool
    {
        return PmTenant::query()
            ->withoutGlobalScopes()
            ->when(Schema::hasColumn('pm_tenants', 'agent_user_id'), fn ($query) => $query->where('agent_user_id', $agentUserId))
            ->where('phone', $phone)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    private function emailTaken(string $email, ?int $exceptId): bool
    {
        if (! Schema::hasColumn('pm_tenants', 'email')) {
            return false;
        }

        return PmTenant::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }
}
