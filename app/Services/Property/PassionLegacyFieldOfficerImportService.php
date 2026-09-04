<?php

namespace App\Services\Property;

use App\Models\PmFieldOfficer;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PassionLegacyFieldOfficerImportService
{
    /** @var array<string, string> */
    private const LEGACY_OFFICER_PHONES = [
        'ALLAN KIMANI' => '0768511053',
        'ZAKARY NGANGA (MBUI)' => '0710843177',
    ];

    public function __construct(
        private PassionLegacyRegisterParser $parser,
        private PassionLegacyRegisterPdfTextExtractor $extractor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false): array
    {
        $records = $this->parser->parse($this->extractor->extract($path));

        $summary = [
            'dry_run' => $dryRun,
            'parsed' => count($records),
            'officers_created' => 0,
            'officers_updated' => 0,
            'properties_linked' => 0,
            'warnings' => [],
        ];

        $run = function () use ($records, $agentUserId, &$summary): void {
            foreach ($records as $record) {
                $officerName = $this->canonicalOfficerName($record['field_officer'] ?? null);
                if ($officerName === null) {
                    continue;
                }

                $property = Property::query()
                    ->withoutGlobalScopes()
                    ->where('code', (string) ($record['code'] ?? ''))
                    ->first();

                if (! $property) {
                    $summary['warnings'][] = 'Property not found for field officer link: '.($record['code'] ?? '?');

                    continue;
                }

                $officer = PmFieldOfficer::query()
                    ->withoutGlobalScopes()
                    ->firstOrNew([
                        'agent_user_id' => $agentUserId,
                        'name' => $officerName,
                    ]);

                $phone = self::LEGACY_OFFICER_PHONES[$officerName] ?? $officer->phone;
                $isNew = ! $officer->exists;

                if ($isNew) {
                    $officer->phone = $phone;
                    $officer->portal_access = false;
                    $officer->save();
                    $summary['officers_created']++;
                } elseif ($phone !== null && $officer->phone !== $phone) {
                    $officer->phone = $phone;
                    $officer->save();
                    $summary['officers_updated']++;
                }

                if ($this->linkProperty($property, $record, $agentUserId)) {
                    $summary['properties_linked']++;
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
     */
    public function linkProperty(Property $property, array $record, int $agentUserId): bool
    {
        $officerName = self::canonicalOfficerName($record['field_officer'] ?? null);
        if ($officerName === null) {
            return false;
        }

        $officer = PmFieldOfficer::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'agent_user_id' => $agentUserId,
                    'name' => $officerName,
                ],
                [
                    'phone' => self::LEGACY_OFFICER_PHONES[$officerName] ?? null,
                    'portal_access' => false,
                ],
            );

        if ((int) $property->field_officer_id === (int) $officer->id) {
            return false;
        }

        $property->update(['field_officer_id' => $officer->id]);

        return true;
    }

    public static function canonicalOfficerName(?string $name): ?string
    {
        $name = Str::upper(trim((string) $name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        if ($name === '') {
            return null;
        }

        if (str_contains($name, 'ZAKARY') && str_contains($name, 'NGANGA')) {
            return 'ZAKARY NGANGA (MBUI)';
        }

        if (str_contains($name, 'ALLAN') && str_contains($name, 'KIMANI')) {
            return 'ALLAN KIMANI';
        }

        return $name;
    }
}
