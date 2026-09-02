<?php

namespace App\Services\Property;

use App\Models\PmLandlordPortalProfile;
use App\Models\User;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PassionLegacyLandlordImportService
{
    public function __construct(
        private PassionLegacyLandlordRegisterParser $parser,
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
            'landlords_created' => 0,
            'landlords_updated' => 0,
            'links_created' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if ($records === []) {
            $summary['errors'][] = 'No landlord rows parsed.';

            return $summary;
        }

        $run = function () use ($records, $agentUserId, $updateExisting, &$summary): void {
            foreach ($records as $index => $record) {
                try {
                    $this->importRecord($record, $agentUserId, $updateExisting, $summary, $index + 1);
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Row {$index}: {$record['code']} — ".$e->getMessage();
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
        $properties = $this->codeResolver->resolveMany($record['code']);
        if ($properties->isEmpty()) {
            $summary['warnings'][] = "Row {$rowNum} ({$record['code']}): no matching property — import properties first.";

            return;
        }

        $landlord = $this->resolveLandlord($record, $agentUserId, $updateExisting, $summary);

        foreach ($properties as $property) {
            if ($landlord->landlordProperties()->where('properties.id', $property->id)->exists()) {
                continue;
            }

            $landlord->landlordProperties()->attach($property->id, ['ownership_percent' => 100]);
            $summary['links_created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $summary
     */
    private function resolveLandlord(array $record, int $agentUserId, bool $updateExisting, array &$summary): User
    {
        $email = isset($record['email']) ? Str::lower(trim((string) $record['email'])) : null;
        $phone = isset($record['phone']) ? $this->normalizer->normalizePhone((string) $record['phone']) : null;
        $name = trim((string) ($record['name'] ?? ''));

        $existing = null;
        if ($email) {
            $existing = User::query()->where('email', $email)->first();
        }
        if (! $existing && $phone) {
            $existing = User::query()->where('phone', $phone)->first();
        }

        if ($existing) {
            if ($updateExisting) {
                $updates = array_filter([
                    'name' => $name !== '' ? $name : null,
                    'email' => $email,
                    'phone' => $phone,
                ]);
                if ($updates !== []) {
                    $existing->update($updates);
                    $summary['landlords_updated']++;
                }

                $this->syncLandlordProfile($existing, $record);
            }

            return $existing;
        }

        $attributes = [
            'name' => $name !== '' ? $name : 'Landlord',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(Str::password(16, symbols: false)),
            'property_portal_role' => 'landlord',
            'email_verified_at' => $email ? now() : null,
        ];

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $attributes['agent_user_id'] = $agentUserId;
        }

        $landlord = User::query()->create($attributes);
        $this->syncLandlordProfile($landlord, $record);
        $summary['landlords_created']++;

        return $landlord;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function syncLandlordProfile(User $landlord, array $record): void
    {
        if (! Schema::hasTable('pm_landlord_portal_profiles')) {
            return;
        }

        $profile = PmLandlordPortalProfile::forUser($landlord);
        $profile->update(array_filter([
            'kra_pin' => ($record['pin'] ?? null) !== '0' ? ($record['pin'] ?? null) : null,
            'address_line' => $record['address'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));
    }
}
