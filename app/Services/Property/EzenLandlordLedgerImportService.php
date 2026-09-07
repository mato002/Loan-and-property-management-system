<?php

namespace App\Services\Property;

use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPropertyTakeonBalance;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenLandlordLedgerImportService
{
    public function __construct(
        private readonly PassionPropertyCodeResolver $codeResolver,
        private readonly PropertyTakeonBalanceService $takeon,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     takeon:int,
     *     posted:int,
     *     skipped:int,
     *     last_balance:?float,
     *     stated_balance:?float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, User $actor, bool $dryRun = false): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open file: '.$path);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('CSV has no header row.');
        }

        $map = [];
        foreach ($header as $i => $name) {
            $map[strtolower(str_replace([' ', '-'], '_', trim((string) $name)))] = (int) $i;
        }
        foreach (['date', 'type', 'debit', 'credit'] as $required) {
            if (! isset($map[$required])) {
                fclose($handle);
                throw new RuntimeException('CSV missing column: '.$required);
            }
        }

        $summary = [
            'parsed' => 0,
            'takeon' => 0,
            'posted' => 0,
            'skipped' => 0,
            'last_balance' => null,
            'stated_balance' => null,
            'warnings' => [],
            'errors' => [],
        ];

        $process = function () use ($handle, $map, $agentUserId, $actor, $dryRun, &$summary): void {
            $rowNum = 1;
            while (($cols = fgetcsv($handle)) !== false) {
                $rowNum++;
                if ($this->empty($cols)) {
                    continue;
                }

                $row = [];
                foreach ($map as $key => $index) {
                    $row[$key] = trim((string) ($cols[$index] ?? ''));
                }
                $summary['parsed']++;

                try {
                    $result = $this->importRow($row, $agentUserId, $actor, $dryRun, $rowNum);
                    $summary['takeon'] += $result['takeon'] ? 1 : 0;
                    $summary['posted'] += $result['posted'] ? 1 : 0;
                    $summary['skipped'] += $result['skipped'] ? 1 : 0;
                    $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
                    if ($result['stated_balance'] !== null) {
                        $summary['stated_balance'] = $result['stated_balance'];
                    }
                } catch (RuntimeException $e) {
                    $summary['errors'][] = 'Row '.$rowNum.': '.$e->getMessage();
                }
            }
        };

        try {
            if ($dryRun) {
                $process();
            } else {
                DB::transaction(function () use ($process, &$summary): void {
                    $process();
                    if ($summary['errors'] !== []) {
                        throw new RuntimeException('Import aborted because of row errors.');
                    }
                });
            }
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'Import aborted because of row errors.') {
                throw $e;
            }
        } finally {
            fclose($handle);
        }

        return $summary;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{takeon:bool, posted:bool, skipped:bool, stated_balance:?float, warnings:list<string>}
     */
    private function importRow(array $row, int $agentUserId, User $actor, bool $dryRun, int $rowNum): array
    {
        $warnings = [];
        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        $property = $this->resolveProperty($row, $agentUserId);
        if ($property === null) {
            throw new RuntimeException('property not found ('.($row['property_name'] ?? $row['property_code'] ?? '').').');
        }

        if (
            $type === 'BAL'
            && Schema::hasColumn('properties', 'agent_user_id')
            && (int) $property->agent_user_id !== $agentUserId
        ) {
            $warnings[] = 'Property '.$property->code
                .' is owned by agent '.(int) $property->agent_user_id
                .' (command used '.$agentUserId.'). Ledger posts use the property owner.';
        }

        $landlord = $this->resolveLandlord($property);
        $postAgentId = Schema::hasColumn('properties', 'agent_user_id')
            ? (int) ($property->agent_user_id ?: $agentUserId)
            : $agentUserId;
        if ($landlord === null) {
            throw new RuntimeException('no landlord linked to '.$property->name.'.');
        }

        $occurred = $this->parseDate((string) ($row['date'] ?? ''));
        $debit = $this->money($row['debit'] ?? 0);
        $credit = $this->money($row['credit'] ?? 0);
        $stated = isset($row['balance']) && $row['balance'] !== '' ? $this->money($row['balance']) : null;
        $txn = strtoupper(trim((string) ($row['txn_no'] ?? '')));
        $desc = trim((string) ($row['description'] ?? ''));

        if ($type === 'BAL') {
            $opening = $credit > 0.009 ? $credit : ($debit > 0.009 ? -1 * $debit : 0.0);
            if (abs($opening) < 0.01) {
                return ['takeon' => false, 'posted' => false, 'skipped' => true, 'stated_balance' => $stated, 'warnings' => $warnings];
            }
            if ($dryRun) {
                return ['takeon' => true, 'posted' => false, 'skipped' => false, 'stated_balance' => $stated, 'warnings' => $warnings];
            }

            $existing = Schema::hasTable('pm_property_takeon_balances')
                ? PmPropertyTakeonBalance::query()
                    ->where('property_id', $property->id)
                    ->where('landlord_id', $landlord->id)
                    ->first()
                : null;

            if ($existing && abs((float) $existing->balance - $opening) < 0.02) {
                $warnings[] = 'Row '.$rowNum.': take-on already '.$opening.' — skipped.';

                return ['takeon' => false, 'posted' => false, 'skipped' => true, 'stated_balance' => $stated, 'warnings' => $warnings];
            }

            if ($existing) {
                throw new RuntimeException(
                    'take-on already exists as '.(float) $existing->balance
                    .' dated '.$existing->balance_date
                    .' — will not replace with EZEN opening '.$opening
                    .'. Reverse the existing take-on first if this ledger replay is intended.'
                );
            }

            $this->takeon->recordTakeon(
                (int) $property->id,
                (int) $landlord->id,
                $opening,
                $occurred,
                $actor,
                $desc !== '' ? 'EZEN '.$desc : 'EZEN opening balance',
                false,
            );

            return ['takeon' => true, 'posted' => false, 'skipped' => false, 'stated_balance' => $stated, 'warnings' => $warnings];
        }

        $amount = $credit > 0.009 ? $credit : $debit;
        $direction = $credit > 0.009
            ? PmLandlordLedgerEntry::DIRECTION_CREDIT
            : PmLandlordLedgerEntry::DIRECTION_DEBIT;
        if ($amount <= 0.009) {
            return ['takeon' => false, 'posted' => false, 'skipped' => true, 'stated_balance' => $stated, 'warnings' => $warnings];
        }

        $marker = '[EZEN '.($txn !== '' && $txn !== '-' ? $txn : $type.'-'.$occurred->format('Ymd').'-'.$rowNum).']';
        $fullDescription = $marker.' '.$desc;
        if (trim((string) ($row['ref_no'] ?? '')) !== '') {
            $fullDescription .= ' ref '.trim((string) $row['ref_no']);
        }

        $already = PmLandlordLedgerEntry::query()
            ->withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->where('user_id', $landlord->id)
            ->where('description', 'like', $marker.'%')
            ->exists();

        if ($already) {
            return ['takeon' => false, 'posted' => false, 'skipped' => true, 'stated_balance' => $stated, 'warnings' => $warnings];
        }

        if ($dryRun) {
            return ['takeon' => false, 'posted' => true, 'skipped' => false, 'stated_balance' => $stated, 'warnings' => $warnings];
        }

        DB::transaction(function () use ($landlord, $direction, $amount, $fullDescription, $property, $type, $txn, $occurred, $postAgentId): void {
            LandlordLedger::post(
                $landlord,
                $direction,
                $amount,
                $fullDescription,
                $property,
                'ezen_'.strtolower($type),
                $this->txnNumericId($txn),
                $occurred->startOfDay(),
                $postAgentId,
            );
        });

        return ['takeon' => false, 'posted' => true, 'skipped' => false, 'stated_balance' => $stated, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveProperty(array $row, int $agentUserId): ?Property
    {
        $code = strtoupper(trim((string) ($row['property_code'] ?? '')));
        if ($code !== '') {
            $matches = $this->codeResolver->resolveMany($code);
            $scoped = $matches->first(function (Property $property) use ($agentUserId): bool {
                return ! Schema::hasColumn('properties', 'agent_user_id')
                    || (int) $property->agent_user_id === $agentUserId;
            });

            return $scoped ?? $matches->first();
        }

        $name = trim((string) ($row['property_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return $this->codeResolver->resolveByName($name);
    }

    private function resolveLandlord(Property $property): ?User
    {
        $property->loadMissing('landlords');
        $first = $property->landlords->first();

        return $first instanceof User ? $first : null;
    }

    private function parseDate(string $value): Carbon
    {
        $value = trim($value);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
            return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return Carbon::parse($value)->startOfDay();
        }

        throw new RuntimeException('Invalid date: '.$value);
    }

    private function txnNumericId(string $txn): ?int
    {
        if (preg_match('/(\d+)/', $txn, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function money(mixed $value): float
    {
        $raw = str_replace([',', ' '], '', (string) $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function empty(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }
}
