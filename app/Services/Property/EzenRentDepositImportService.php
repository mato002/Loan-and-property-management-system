<?php

namespace App\Services\Property;

use App\Models\LeaseDepositLine;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmTenantDeposit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenRentDepositImportService
{
    /**
     * @return array{
     *     parsed:int,
     *     tenants_updated:int,
     *     skipped_former:int,
     *     skipped:int,
     *     held_applied:float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false, bool $updateExisting = true): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open file: '.$path);
        }

        $header = fgetcsv($handle);
        if (! is_array($header) || $header === []) {
            fclose($handle);
            throw new RuntimeException('CSV has no header row.');
        }

        $map = $this->headerMap($header);
        foreach (['tnt_account', 'type'] as $required) {
            if (! isset($map[$required])) {
                fclose($handle);
                throw new RuntimeException('CSV missing required column: '.$required);
            }
        }

        $summary = [
            'parsed' => 0,
            'tenants_updated' => 0,
            'skipped_former' => 0,
            'skipped' => 0,
            'held_applied' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        /** @var array<string, list<array<string, string>>> $groups */
        $groups = [];
        $rowNum = 1;
        while (($cols = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->isEmptyRow($cols)) {
                continue;
            }

            $row = $this->rowFromMap($map, $cols);
            $summary['parsed']++;
            $groupKey = strtoupper(trim((string) ($row['match_account'] ?: $row['tnt_account'])));
            if ($groupKey === '') {
                $summary['errors'][] = 'Row '.$rowNum.': tnt_account is required.';
                continue;
            }

            $row['_row'] = (string) $rowNum;
            $groups[$groupKey][] = $row;
        }
        fclose($handle);

        foreach ($groups as $account => $rows) {
            try {
                $result = $this->applyTenantGroup($account, $rows, $agentUserId, $dryRun, $updateExisting);
                $summary['tenants_updated'] += $result['updated'] ? 1 : 0;
                $summary['skipped_former'] += $result['former'] ? 1 : 0;
                $summary['skipped'] += ($result['updated'] || $result['former']) ? 0 : 1;
                $summary['held_applied'] += $result['held'];
                $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
            } catch (RuntimeException $e) {
                $summary['errors'][] = $account.': '.$e->getMessage();
            }
        }

        $summary['held_applied'] = round($summary['held_applied'], 2);

        return $summary;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{updated:bool, former:bool, held:float, warnings:list<string>}
     */
    private function applyTenantGroup(
        string $account,
        array $rows,
        int $agentUserId,
        bool $dryRun,
        bool $updateExisting,
    ): array {
        $warnings = [];
        $sample = $rows[0];
        $tenant = $this->resolveTenant($account, $agentUserId);
        if ($tenant === null) {
            $warnings[] = $account.' '.$sample['tenant_name'].' / '.$sample['unit_label']
                .': no current tenant — former occupant deposit skipped.';

            return ['updated' => false, 'former' => true, 'held' => 0.0, 'warnings' => $warnings];
        }

        $lease = $this->resolveActiveLease($tenant);
        if ($lease === null) {
            throw new RuntimeException('tenant '.$tenant->account_number.' has no active lease.');
        }

        if (! $this->namesLooselyMatch($sample['tenant_name'], (string) $tenant->name)) {
            $warnings[] = $account.': register "'.$sample['tenant_name'].'" vs system "'.$tenant->name.'" — applied to TNT '.$tenant->account_number.'.';
        }

        $buckets = [];
        $heldByLandlord = false;
        $asOf = null;
        foreach ($rows as $row) {
            $key = $this->depositKey((string) $row['type']);
            $held = $this->heldAmount($row);
            if ($held <= 0.009) {
                continue;
            }
            $buckets[$key] = ($buckets[$key] ?? 0.0) + $held;
            if ($this->isLandlordHeld((string) ($row['held_by'] ?? ''))) {
                $heldByLandlord = true;
            }
            $asOf = $asOf ?: $this->parseDate((string) ($row['date'] ?? ''));
        }

        if ($buckets === []) {
            return ['updated' => false, 'former' => false, 'held' => 0.0, 'warnings' => $warnings];
        }

        $rent = round((float) ($buckets['rent_deposit'] ?? 0), 2);
        $additional = [];
        foreach ($buckets as $key => $amount) {
            if ($key === 'rent_deposit') {
                continue;
            }
            $additional[] = [
                'label' => $this->depositLabel($key),
                'amount' => round($amount, 2),
            ];
        }

        $totalHeld = round(array_sum($buckets), 2);
        $existingRent = round((float) ($lease->deposit_amount ?? 0), 2);
        if (! $updateExisting && $existingRent > 0.009) {
            $warnings[] = $account.': lease already has rent deposit '.$existingRent.' — skipped (--no-update).';

            return ['updated' => false, 'former' => false, 'held' => 0.0, 'warnings' => $warnings];
        }

        if ($dryRun) {
            return ['updated' => true, 'former' => false, 'held' => $totalHeld, 'warnings' => $warnings];
        }

        DB::transaction(function () use ($lease, $tenant, $rent, $additional, $buckets, $heldByLandlord, $asOf, $sample, $agentUserId): void {
            $payload = [
                'deposit_amount' => $rent,
            ];
            if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
                $payload['additional_deposits'] = $additional;
            }
            $lease->fill($payload);
            $lease->save();

            $this->syncDepositLines($lease, $buckets, $heldByLandlord, $asOf, $sample);
            $this->syncTenantDepositRecord($tenant, round(array_sum($buckets), 2), $heldByLandlord, $agentUserId);
        });

        return ['updated' => true, 'former' => false, 'held' => $totalHeld, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, float>  $buckets
     * @param  array<string, string>  $sample
     */
    private function syncDepositLines(PmLease $lease, array $buckets, bool $heldByLandlord, ?string $asOf, array $sample): void
    {
        if (! Schema::hasTable('lease_deposit_lines')) {
            return;
        }

        $keys = array_keys($buckets);
        LeaseDepositLine::query()
            ->where('pm_lease_id', $lease->id)
            ->whereIn('deposit_key', $keys)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($buckets as $key => $amount) {
            $amount = round($amount, 2);
            $meta = [
                'source' => 'ezen_rent_deposit',
                'held_by' => $heldByLandlord ? 'landlord' : null,
                'received_by_agent' => false,
                'as_of' => $asOf,
                'register_tnt' => strtoupper((string) ($sample['tnt_account'] ?? '')),
            ];
            $rows[] = [
                'pm_lease_id' => (int) $lease->id,
                'deposit_definition_id' => null,
                'deposit_key' => $key,
                'label' => $this->depositLabel($key),
                'expected_amount' => $amount,
                'paid_amount' => $amount,
                'balance_amount' => 0.0,
                'is_refundable' => true,
                'refund_status' => 'not_refunded',
                'meta' => json_encode($meta),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            LeaseDepositLine::query()->insert($rows);
        }
    }

    private function syncTenantDepositRecord(PmTenant $tenant, float $amount, bool $heldByLandlord, int $agentUserId): void
    {
        if (! Schema::hasTable('pm_tenant_deposits') || $amount <= 0.009) {
            return;
        }

        $status = $heldByLandlord ? 'held_by_landlord' : 'held';
        $existing = PmTenantDeposit::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'tenant_id' => (int) $tenant->id,
            'amount' => $amount,
            'status' => $status,
        ];
        if (Schema::hasColumn('pm_tenant_deposits', 'agent_user_id')) {
            $payload['agent_user_id'] = $agentUserId;
        }

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        PmTenantDeposit::query()->create($payload);
    }

    private function resolveTenant(string $account, int $agentUserId): ?PmTenant
    {
        $matches = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('account_number', $account)
            ->orderByDesc('id')
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $scoped = $matches->first(fn (PmTenant $tenant) => (int) $tenant->agent_user_id === $agentUserId);
            if ($scoped) {
                return $scoped;
            }
        }

        return $matches->first();
    }

    private function resolveActiveLease(PmTenant $tenant): ?PmLease
    {
        return PmLease::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenant->id)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('id')
            ->first();
    }

    private function depositKey(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return match ($normalized) {
            'rent deposit' => 'rent_deposit',
            'water deposit' => 'water_deposit',
            'electricity deposit' => 'electricity_deposit',
            'garbage deposit' => 'garbage_deposit',
            default => (string) preg_replace('/[^a-z0-9]+/', '_', $normalized),
        };
    }

    private function depositLabel(string $key): string
    {
        return match ($key) {
            'rent_deposit' => 'Rent deposit',
            'water_deposit' => 'Water deposit',
            'electricity_deposit' => 'Electricity deposit',
            'garbage_deposit' => 'Garbage deposit',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private function heldAmount(array $row): float
    {
        $held = $this->money($row['held'] ?? $row['held_posted'] ?? null);
        if ($held > 0.009) {
            return $held;
        }

        return $this->money($row['amount'] ?? 0);
    }

    private function isLandlordHeld(string $heldBy): bool
    {
        return str_contains(strtolower($heldBy), 'landlord');
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $value;
    }

    private function namesLooselyMatch(string $a, string $b): bool
    {
        $na = preg_replace('/[^A-Z]/', '', strtoupper($a)) ?? '';
        $nb = preg_replace('/[^A-Z]/', '', strtoupper($b)) ?? '';
        if ($na === '' || $nb === '') {
            return true;
        }

        return str_contains($na, $nb) || str_contains($nb, $na)
            || similar_text($na, $nb) / max(strlen($na), strlen($nb)) >= 0.55;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $raw = str_replace([',', ' '], '', (string) $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = strtolower(trim((string) $name));
            $key = str_replace([' ', '-'], '_', $key);
            $map[$key] = (int) $index;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string|null>  $cols
     * @return array<string, string>
     */
    private function rowFromMap(array $map, array $cols): array
    {
        $row = [];
        foreach ($map as $key => $index) {
            $row[$key] = trim((string) ($cols[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function isEmptyRow(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }
}
