<?php

namespace App\Services\Property;

use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPropertyTakeonBalance;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class PropertyTakeonBalanceService
{
    public function __construct(
        private readonly PassionPropertyCodeResolver $codeResolver,
    ) {}

    /**
     * @param  array{property_id?: int, landlord_id?: int, search?: string}  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, hint?: string}>
     * }
     */
    public function buildIndex(array $filters): array
    {
        if (! Schema::hasTable('pm_property_takeon_balances')) {
            return [
                'rows' => [],
                'stats' => [
                    ['label' => 'Take-on records', 'value' => '0', 'hint' => 'Run migrations first'],
                ],
            ];
        }

        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = PmPropertyTakeonBalance::query()
            ->with(['property:id,code,name', 'landlord:id,name'])
            ->orderByDesc('balance_date')
            ->orderBy('property_id');

        if ($propertyId > 0) {
            $query->where('property_id', $propertyId);
        }
        if ($landlordId > 0) {
            $query->where('landlord_id', $landlordId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->whereHas('property', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))
                    ->orWhereHas('landlord', fn ($lq) => $lq->where('name', 'like', '%'.$search.'%'));
            });
        }

        $records = $query->get();
        $rows = $records->map(fn (PmPropertyTakeonBalance $row) => $this->presentRow($row))->all();

        $positive = $records->where('balance', '>', 0)->count();
        $negative = $records->where('balance', '<', 0)->count();
        $net = round((float) $records->sum('balance'), 2);

        return [
            'rows' => $rows,
            'stats' => [
                ['label' => 'Take-on records', 'value' => (string) $records->count(), 'hint' => 'Property × landlord opening balances'],
                ['label' => 'Credit (owe landlord)', 'value' => (string) $positive, 'hint' => 'Positive balances'],
                ['label' => 'Debit (overdrawn)', 'value' => (string) $negative, 'hint' => 'Negative balances'],
                ['label' => 'Net take-on', 'value' => PropertyMoney::kes($net), 'hint' => 'Sum of all take-on balances'],
            ],
        ];
    }

    public function recordTakeon(
        int $propertyId,
        int $landlordId,
        float $balance,
        Carbon $balanceDate,
        User $actor,
        ?string $notes = null,
        bool $updateExisting = true,
    ): PmPropertyTakeonBalance {
        $this->assertTableReady();
        $this->assertLandlordLinked($propertyId, $landlordId);

        if (abs($balance) < 0.01) {
            throw new InvalidArgumentException('Take-on balance must be non-zero.');
        }

        $property = Property::query()->findOrFail($propertyId);
        $landlord = User::query()->findOrFail($landlordId);
        $agentUserId = (int) ($property->agent_user_id ?? $actor->id);

        return DB::transaction(function () use (
            $propertyId,
            $landlordId,
            $balance,
            $balanceDate,
            $actor,
            $notes,
            $updateExisting,
            $property,
            $landlord,
            $agentUserId,
        ) {
            $existing = PmPropertyTakeonBalance::query()
                ->where('property_id', $propertyId)
                ->where('landlord_id', $landlordId)
                ->first();

            if ($existing && ! $updateExisting) {
                throw new InvalidArgumentException('Take-on balance already exists for this property and landlord.');
            }

            if ($existing) {
                $this->reverseLedgerEntry($existing, $landlord, $property, $agentUserId);
                $existing->update([
                    'balance' => round($balance, 2),
                    'balance_date' => $balanceDate->toDateString(),
                    'notes' => $notes,
                    'agent_user_id' => $agentUserId,
                ]);
                $takeon = $existing->fresh();
            } else {
                $takeon = PmPropertyTakeonBalance::query()->create([
                    'property_id' => $propertyId,
                    'landlord_id' => $landlordId,
                    'agent_user_id' => $agentUserId,
                    'balance' => round($balance, 2),
                    'balance_date' => $balanceDate->toDateString(),
                    'notes' => $notes,
                    'created_by' => (int) $actor->id,
                ]);
            }

            $entry = $this->postBalanceToLedger($takeon, $landlord, $property, $agentUserId);
            $takeon->update(['ledger_entry_id' => (int) $entry->id]);

            return $takeon->fresh(['property', 'landlord']);
        });
    }

    public function deleteTakeon(PmPropertyTakeonBalance $takeon, User $actor): void
    {
        $this->assertTableReady();

        DB::transaction(function () use ($takeon, $actor): void {
            $takeon->loadMissing(['property', 'landlord']);
            $property = $takeon->property;
            $landlord = $takeon->landlord;
            if (! $property || ! $landlord) {
                throw new RuntimeException('Take-on record is missing property or landlord.');
            }

            $agentUserId = (int) ($property->agent_user_id ?? $actor->id);
            $this->reverseLedgerEntry($takeon, $landlord, $property, $agentUserId);
            $takeon->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, int $agentUserId, User $actor, bool $dryRun = false, bool $updateExisting = true): array
    {
        $this->assertTableReady();

        if (! is_readable($path)) {
            throw new InvalidArgumentException('Import file is not readable: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open import file.');
        }

        $summary = [
            'dry_run' => $dryRun,
            'parsed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new InvalidArgumentException('Import file is empty.');
            }

            $map = $this->mapCsvHeaders($header);
            if (! isset($map['balance'])) {
                throw new InvalidArgumentException('CSV must include a balance column.');
            }

            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $summary['parsed']++;
                $record = $this->parseCsvRow($row, $map);

                try {
                    $property = $this->resolvePropertyFromImportRow($record, $agentUserId);
                    if (! $property) {
                        $summary['skipped']++;
                        $summary['warnings'][] = "Row {$rowNum}: property not found (".($record['property_code'] ?? $record['property_name'] ?? 'unknown').').';

                        continue;
                    }

                    $landlordId = $this->resolveLandlordId($property, $record);
                    if ($landlordId <= 0) {
                        $summary['skipped']++;
                        $summary['warnings'][] = "Row {$rowNum}: no landlord linked to {$property->name}.";

                        continue;
                    }

                    $balance = $this->parseAmount($record['balance'] ?? '');
                    if (abs($balance) < 0.01) {
                        $summary['skipped']++;
                        $summary['warnings'][] = "Row {$rowNum}: zero balance skipped.";

                        continue;
                    }

                    $balanceDate = $this->parseDate($record['balance_date'] ?? '') ?? Carbon::parse('2022-06-01');

                    if ($dryRun) {
                        $exists = PmPropertyTakeonBalance::query()
                            ->where('property_id', $property->id)
                            ->where('landlord_id', $landlordId)
                            ->exists();
                        $exists ? $summary['updated']++ : $summary['created']++;

                        continue;
                    }

                    $existing = PmPropertyTakeonBalance::query()
                        ->where('property_id', $property->id)
                        ->where('landlord_id', $landlordId)
                        ->exists();

                    $this->recordTakeon(
                        (int) $property->id,
                        $landlordId,
                        $balance,
                        $balanceDate,
                        $actor,
                        $record['notes'] ?? null,
                        $updateExisting,
                    );

                    $existing ? $summary['updated']++ : $summary['created']++;
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Row {$rowNum}: ".$e->getMessage();
                }
            }
        } finally {
            fclose($handle);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromUpload(UploadedFile $file, User $actor, bool $updateExisting = true): array
    {
        $agentUserId = AgentWorkspaceScope::shouldApply()
            ? (int) $actor->id
            : (int) (Property::query()->value('agent_user_id') ?? $actor->id);

        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException('Uploaded file could not be read.');
        }

        return $this->importFromPath($path, $agentUserId, $actor, false, $updateExisting);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(PmPropertyTakeonBalance $row): array
    {
        $property = $row->property;
        $landlord = $row->landlord;
        $balance = (float) $row->balance;

        return [
            'id' => (int) $row->id,
            'property_id' => (int) $row->property_id,
            'landlord_id' => (int) $row->landlord_id,
            'property_code' => (string) ($property?->code ?? ''),
            'property_name' => (string) ($property?->name ?? ''),
            'landlord_name' => (string) ($landlord?->name ?? ''),
            'display_property' => trim((($property?->code ?? '') !== '' ? '['.$property->code.'] ' : '').($property?->name ?? '')),
            'balance' => $balance,
            'balance_label' => PropertyMoney::kes($balance),
            'balance_tone' => $balance < 0 ? 'negative' : ($balance > 0 ? 'positive' : 'zero'),
            'balance_date' => $row->balance_date?->format('Y-m-d'),
            'balance_date_label' => $row->balance_date?->format('d/m/Y'),
            'notes' => (string) ($row->notes ?? ''),
            'ledger_entry_id' => $row->ledger_entry_id,
            'posted_at' => optional($row->updated_at)->format('Y-m-d H:i'),
        ];
    }

    private function postBalanceToLedger(
        PmPropertyTakeonBalance $takeon,
        User $landlord,
        Property $property,
        int $agentUserId,
    ): PmLandlordLedgerEntry {
        $amount = abs((float) $takeon->balance);
        $direction = (float) $takeon->balance > 0
            ? PmLandlordLedgerEntry::DIRECTION_CREDIT
            : PmLandlordLedgerEntry::DIRECTION_DEBIT;

        $description = 'Property take-on balance';
        if ($takeon->notes !== null && trim((string) $takeon->notes) !== '') {
            $description .= ' — '.trim((string) $takeon->notes);
        }

        return LandlordLedger::post(
            $landlord,
            $direction,
            $amount,
            $description,
            $property,
            'pm_property_takeon_balance',
            (int) $takeon->id,
            Carbon::parse((string) $takeon->balance_date)->startOfDay(),
            $agentUserId,
        );
    }

    private function reverseLedgerEntry(
        PmPropertyTakeonBalance $takeon,
        User $landlord,
        Property $property,
        int $agentUserId,
    ): void {
        $entry = $takeon->ledgerEntry;
        if (! $entry) {
            return;
        }

        $opposite = $entry->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT
            ? PmLandlordLedgerEntry::DIRECTION_DEBIT
            : PmLandlordLedgerEntry::DIRECTION_CREDIT;

        LandlordLedger::post(
            $landlord,
            $opposite,
            (float) $entry->amount,
            'Reversal — property take-on balance #'.$takeon->id,
            $property,
            'pm_property_takeon_balance_reversal',
            (int) $entry->id,
            now(),
            $agentUserId,
        );
    }

    private function assertLandlordLinked(int $propertyId, int $landlordId): void
    {
        $linked = DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->where('user_id', $landlordId)
            ->exists();

        if (! $linked) {
            throw new InvalidArgumentException('Landlord is not linked to this property.');
        }
    }

    private function assertTableReady(): void
    {
        if (! Schema::hasTable('pm_property_takeon_balances')) {
            throw new RuntimeException('Run migrations to create pm_property_takeon_balances.');
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolvePropertyFromImportRow(array $record, int $agentUserId): ?Property
    {
        if (! empty($record['property_code'])) {
            $property = $this->codeResolver->resolveOne((string) $record['property_code']);
            if ($property && (int) ($property->agent_user_id ?? 0) === $agentUserId) {
                return $property;
            }
        }

        if (! empty($record['property_name'])) {
            $property = $this->codeResolver->resolveByName((string) $record['property_name']);
            if ($property && (int) ($property->agent_user_id ?? 0) === $agentUserId) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveLandlordId(Property $property, array $record): int
    {
        if (! empty($record['landlord_id'])) {
            $landlordId = (int) $record['landlord_id'];
            $linked = DB::table('property_landlord')
                ->where('property_id', $property->id)
                ->where('user_id', $landlordId)
                ->exists();
            if ($linked) {
                return $landlordId;
            }
        }

        $link = DB::table('property_landlord')
            ->where('property_id', $property->id)
            ->orderByDesc('ownership_percent')
            ->orderBy('user_id')
            ->first(['user_id']);

        return (int) ($link->user_id ?? 0);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapCsvHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            $key = str_replace([' ', '-'], '_', $key);
            $aliases = match ($key) {
                'code', 'property_code', 'prop_code' => 'property_code',
                'property', 'property_name', 'prop' => 'property_name',
                'amount', 'balance', 'take_on_balance', 'takeon_balance' => 'balance',
                'date', 'balance_date', 'take_on_date', 'takeon_date' => 'balance_date',
                'landlord', 'landlord_id', 'owner_id' => 'landlord_id',
                'note', 'notes', 'remark', 'remarks' => 'notes',
                default => $key,
            };
            $map[$aliases] = (int) $index;
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    private function parseCsvRow(array $row, array $map): array
    {
        $pick = static function (string $key) use ($row, $map): ?string {
            if (! isset($map[$key])) {
                return null;
            }

            $value = trim((string) ($row[$map[$key]] ?? ''));

            return $value !== '' ? $value : null;
        };

        return [
            'property_code' => $pick('property_code'),
            'property_name' => $pick('property_name'),
            'balance' => $pick('balance'),
            'balance_date' => $pick('balance_date'),
            'landlord_id' => $pick('landlord_id'),
            'notes' => $pick('notes'),
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseAmount(string $raw): float
    {
        $clean = str_replace([',', ' '], '', trim($raw));
        $clean = trim($clean, '"\'');
        if ($clean === '' || ! is_numeric($clean)) {
            throw new InvalidArgumentException('Invalid balance amount: '.$raw);
        }

        return round((float) $clean, 2);
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->startOfDay();
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid balance date: '.$raw);
        }
    }
}
