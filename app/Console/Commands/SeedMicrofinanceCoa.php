<?php

namespace App\Console\Commands;

use App\Models\AccountingChartAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedMicrofinanceCoa extends Command
{
    protected $signature = 'loan:seed-microfinance-coa
                            {--dry-run : Show changes without writing to the database}
                            {--prune-unused : Mark completely unused non-seed COA accounts as inactive}';

    protected $description = 'Safely seed/import microfinance chart of accounts without touching balances or history.';

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $duplicateWarnings = [];

    /** @var array<int, string> */
    private array $missingParentWarnings = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pruneUnused = (bool) $this->option('prune-unused');

        if (! Schema::hasTable('accounting_chart_accounts')) {
            $this->error('Table accounting_chart_accounts was not found.');

            return self::FAILURE;
        }

        $existingByCode = AccountingChartAccount::query()
            ->get()
            ->groupBy(fn (AccountingChartAccount $account): string => (string) $account->code);

        [$rows, $legacyRootLinks] = $this->prepareSeedRows($this->seedRows(), $existingByCode);
        $this->collectInputDuplicateWarnings($rows);
        $this->collectDatabaseDuplicateWarnings();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $pruneCandidates = 0;

        if (! $dryRun) {
            DB::transaction(function () use (
                $rows,
                &$created,
                &$updated,
                &$skipped,
                $existingByCode,
                $legacyRootLinks,
                $pruneUnused,
                &$pruneCandidates
            ): void {
                $createdByCode = $this->applyUpserts($rows, $existingByCode, $created, $updated, $skipped, false);
                $this->applyLegacyRootLinks($legacyRootLinks, $existingByCode, $createdByCode, $updated, $skipped, false);
                if ($pruneUnused) {
                    $pruneCandidates = $this->runPrune(false);
                }
            });
        } else {
            $createdByCode = $this->applyUpserts($rows, $existingByCode, $created, $updated, $skipped, true);
            $this->applyLegacyRootLinks($legacyRootLinks, $existingByCode, $createdByCode, $updated, $skipped, true);
            if ($pruneUnused) {
                $pruneCandidates = $this->runPrune(true);
            }
        }

        $this->printSummary($created, $updated, $skipped, $pruneCandidates, $dryRun, $pruneUnused);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<string, Collection<int, AccountingChartAccount>>  $existingByCode
     * @return array<string, AccountingChartAccount|null>
     */
    private function applyUpserts(
        array $rows,
        Collection $existingByCode,
        int &$created,
        int &$updated,
        int &$skipped,
        bool $dryRun
    ): array {
        $createdByCode = [];

        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $existingMatches = $existingByCode->get($code, collect());

            if ($existingMatches->count() > 1) {
                $this->duplicateWarnings[] = "Code {$code} already exists multiple times in DB; skipped.";
                $skipped++;
                continue;
            }

            $existing = $existingMatches->first();

            $parentId = null;
            $parentCode = (string) ($row['parent_code'] ?? '');
            $parentWillExistAfterCreate = false;
            if ($parentCode !== '') {
                $parent = $this->findParent($parentCode, $existingByCode, $createdByCode);
                if (! $parent) {
                    if ($dryRun && array_key_exists($parentCode, $createdByCode)) {
                        $parentWillExistAfterCreate = true;
                    } else {
                        $this->missingParentWarnings[] = "Missing parent code {$parentCode} for child {$code}.";
                    }
                } else {
                    $parentId = (int) $parent->id;
                }
            }
            if ($dryRun && $parentWillExistAfterCreate && $parentId === null) {
                // Sentinel for dry-run reporting only; never persisted.
                $parentId = PHP_INT_MAX;
            }

            $payload = $this->buildSafePayload($row, $parentId);
            if (! $existing) {
                if ($dryRun) {
                    $this->line("[dry-run] CREATE code {$code} ({$row['name']})");
                    $createdByCode[$code] = null;
                    $created++;
                    continue;
                }

                $createdModel = AccountingChartAccount::query()->create($payload);
                $createdByCode[$code] = $createdModel;
                $created++;
                continue;
            }

            $dirty = $this->getMissingSafeFields($existing, $payload);
            if ($dirty === []) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] UPDATE code {$code}: ".implode(', ', array_keys($dirty)));
                $updated++;
                continue;
            }

            $existing->fill($dirty);
            $existing->save();
            $updated++;
        }

        return $createdByCode;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildSafePayload(array $row, ?int $parentId): array
    {
        $payload = [
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
        ];

        if (Schema::hasColumn('accounting_chart_accounts', 'account_type')) {
            $payload['account_type'] = (string) $row['account_type'];
        }
        if (Schema::hasColumn('accounting_chart_accounts', 'parent_id')) {
            $payload['parent_id'] = $parentId;
        }
        if (Schema::hasColumn('accounting_chart_accounts', 'account_class')) {
            $payload['account_class'] = $this->normalizedAccountClass((string) $row['account_class']);
        }
        if (Schema::hasColumn('accounting_chart_accounts', 'is_active')) {
            $payload['is_active'] = true;
        }
        if (Schema::hasColumn('accounting_chart_accounts', 'is_cash_account')) {
            $payload['is_cash_account'] = (bool) $row['is_cash_account'];
        }

        return $payload;
    }

    private function normalizedAccountClass(string $accountClass): string
    {
        $class = trim($accountClass);
        if (strcasecmp($class, AccountingChartAccount::CLASS_PARENT) !== 0) {
            return $class;
        }

        $type = DB::selectOne("
            SELECT COLUMN_TYPE AS ct
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'accounting_chart_accounts'
              AND COLUMN_NAME = 'account_class'
            LIMIT 1
        ");

        $columnType = strtolower((string) ($type->ct ?? ''));
        if ($columnType !== '' && ! str_contains($columnType, "'parent'")) {
            return AccountingChartAccount::CLASS_HEADER;
        }

        return $class;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getMissingSafeFields(AccountingChartAccount $existing, array $payload): array
    {
        $dirty = [];
        foreach ($payload as $field => $value) {
            if ($field === 'code') {
                continue;
            }

            $current = $existing->getAttribute($field);

            // Preserve existing semantics: only fill truly missing metadata.
            if (! $this->isFieldMissing($current)) {
                continue;
            }
            if ($this->isFieldMissing($value)) {
                continue;
            }

            if ($field === 'parent_id' && (int) $value <= 0) {
                continue;
            }

            if ((string) $current !== (string) $value) {
                $dirty[$field] = $value;
            }
        }

        return $dirty;
    }

    private function isFieldMissing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  Collection<string, Collection<int, AccountingChartAccount>>  $existingByCode
     * @param  array<string, AccountingChartAccount>  $createdByCode
     */
    private function findParent(string $parentCode, Collection $existingByCode, array $createdByCode): ?AccountingChartAccount
    {
        if (array_key_exists($parentCode, $createdByCode)) {
            return $createdByCode[$parentCode] instanceof AccountingChartAccount
                ? $createdByCode[$parentCode]
                : null;
        }

        $matches = $existingByCode->get($parentCode, collect());
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            $this->duplicateWarnings[] = "Parent code {$parentCode} has duplicate rows in DB.";
        }

        return null;
    }

    /**
     * @param  Collection<string, Collection<int, AccountingChartAccount>>  $existingByCode
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, string>}
     */
    private function prepareSeedRows(array $rows, Collection $existingByCode): array
    {
        $usedCodes = collect($rows)->pluck('code')->flip();
        foreach ($existingByCode->keys() as $code) {
            $usedCodes->put((string) $code, true);
        }

        $rootRows = collect($rows)
            ->filter(fn (array $row): bool => (string) ($row['parent_code'] ?? '') === '')
            ->values();

        $rootRemap = [];
        $legacyRootLinks = [];

        foreach ($rootRows as $rootRow) {
            $code = (string) $rootRow['code'];
            $matches = $existingByCode->get($code, collect());
            if ($matches->count() !== 1) {
                continue;
            }

            $existing = $matches->first();
            if (! $existing instanceof AccountingChartAccount) {
                continue;
            }

            $existingName = trim((string) ($existing->name ?? ''));
            $existingType = trim((string) ($existing->account_type ?? ''));
            $existingClass = trim((string) ($existing->account_class ?? ''));
            $targetName = trim((string) $rootRow['name']);
            $targetType = trim((string) $rootRow['account_type']);
            $targetClass = trim((string) $rootRow['account_class']);

            $semanticsConflict = ($existingName !== '' && strcasecmp($existingName, $targetName) !== 0)
                || ($existingType !== '' && strcasecmp($existingType, $targetType) !== 0)
                || ($existingClass !== '' && strcasecmp($existingClass, $targetClass) !== 0);

            if (! $semanticsConflict) {
                continue;
            }

            $replacement = $this->findReusableHeaderCode($existingByCode, $rootRow);
            if ($replacement !== null) {
                $rootRemap[$code] = $replacement;
                $legacyRootLinks[$code] = $replacement;
                $usedCodes->put($replacement, true);
                $this->warnings[] = "Header code {$code} is already a posting account; reusing existing header {$replacement}.";
                continue;
            }

            $replacement = $this->allocateHeaderCode($targetType, $usedCodes);
            $rootRemap[$code] = $replacement;
            $legacyRootLinks[$code] = $replacement;
            $usedCodes->put($replacement, true);
            $this->warnings[] = "Header code {$code} is already a posting account; new header will use {$replacement}.";
        }

        if ($rootRemap === []) {
            return [$rows, $legacyRootLinks];
        }

        $prepared = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $parentCode = (string) ($row['parent_code'] ?? '');
            if (array_key_exists($code, $rootRemap)) {
                $row['code'] = $rootRemap[$code];
                $code = (string) $row['code'];
            }
            if ($parentCode !== '' && array_key_exists($parentCode, $rootRemap)) {
                $row['parent_code'] = $rootRemap[$parentCode];
            }
            $prepared[] = $row;
        }

        return [$prepared, $legacyRootLinks];
    }

    private function allocateHeaderCode(string $accountType, Collection $usedCodes): string
    {
        $ranges = [
            'asset' => [1000, 1999],
            'liability' => [2000, 2999],
            'equity' => [3000, 3999],
            'income' => [4000, 4999],
            'expense' => [5000, 5999],
        ];
        [$from, $to] = $ranges[$accountType] ?? [9000, 9999];

        for ($candidate = $to; $candidate >= $from; $candidate--) {
            $code = (string) $candidate;
            if (! $usedCodes->has($code)) {
                return $code;
            }
        }

        throw new \RuntimeException("No safe unused header code available for account_type={$accountType}.");
    }

    /**
     * @param  Collection<string, Collection<int, AccountingChartAccount>>  $existingByCode
     * @param  array<string, mixed>  $rootRow
     */
    private function findReusableHeaderCode(Collection $existingByCode, array $rootRow): ?string
    {
        $targetName = trim((string) ($rootRow['name'] ?? ''));
        $targetType = trim((string) ($rootRow['account_type'] ?? ''));
        $targetClass = $this->normalizedAccountClass((string) ($rootRow['account_class'] ?? ''));

        if ($targetName === '' || $targetType === '' || $targetClass === '') {
            return null;
        }

        foreach ($existingByCode as $code => $group) {
            /** @var AccountingChartAccount|null $candidate */
            $candidate = $group->first();
            if (! $candidate instanceof AccountingChartAccount) {
                continue;
            }

            $candidateName = trim((string) ($candidate->name ?? ''));
            $candidateType = trim((string) ($candidate->account_type ?? ''));
            $candidateClass = $this->normalizedAccountClass((string) ($candidate->account_class ?? ''));

            if (strcasecmp($candidateName, $targetName) !== 0) {
                continue;
            }
            if (strcasecmp($candidateType, $targetType) !== 0) {
                continue;
            }
            if (strcasecmp($candidateClass, $targetClass) !== 0) {
                continue;
            }

            return (string) $code;
        }

        return null;
    }

    /**
     * @param  Collection<string, Collection<int, AccountingChartAccount>>  $existingByCode
     * @param  array<string, AccountingChartAccount|null>  $createdByCode
     * @param  array<string, string>  $legacyRootLinks
     */
    private function applyLegacyRootLinks(
        array $legacyRootLinks,
        Collection $existingByCode,
        array $createdByCode,
        int &$updated,
        int &$skipped,
        bool $dryRun
    ): void {
        if (! Schema::hasColumn('accounting_chart_accounts', 'parent_id')) {
            return;
        }

        foreach ($legacyRootLinks as $legacyCode => $newHeaderCode) {
            $legacyMatches = $existingByCode->get($legacyCode, collect());
            if ($legacyMatches->count() !== 1) {
                continue;
            }

            $legacy = $legacyMatches->first();
            if (! $legacy instanceof AccountingChartAccount) {
                continue;
            }

            if ((int) ($legacy->parent_id ?? 0) > 0) {
                $skipped++;
                continue;
            }

            $newHeader = $this->findParent($newHeaderCode, $existingByCode, $createdByCode);
            if (! $newHeader) {
                if ($dryRun && array_key_exists($newHeaderCode, $createdByCode)) {
                    $this->line("[dry-run] UPDATE code {$legacyCode}: parent_id");
                    $updated++;
                    continue;
                }
                $this->missingParentWarnings[] = "Unable to link {$legacyCode} under new header {$newHeaderCode}.";
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] UPDATE code {$legacyCode}: parent_id");
                $updated++;
                continue;
            }

            $legacy->parent_id = (int) $newHeader->id;
            $legacy->save();
            $updated++;
        }
    }

    private function runPrune(bool $dryRun): int
    {
        $accounts = AccountingChartAccount::query()
            ->orderBy('code')
            ->get();

        $seedCodes = collect($this->seedRows())->pluck('code')->flip();
        $candidates = 0;

        foreach ($accounts as $account) {
            if ($seedCodes->has((string) $account->code)) {
                continue;
            }

            if (! $this->isPrunable($account)) {
                continue;
            }

            $candidates++;
            if ($dryRun) {
                $this->line("[dry-run] PRUNE candidate code {$account->code} ({$account->name})");
                continue;
            }

            $this->applySafePrune($account);
        }

        return $candidates;
    }

    private function isPrunable(AccountingChartAccount $account): bool
    {
        if ((bool) ($account->is_controlled_account ?? false)) {
            return false;
        }

        if ((float) ($account->current_balance ?? 0) !== 0.0) {
            return false;
        }

        if ($this->hasChildren($account->id)) {
            return false;
        }

        if ($this->hasJournalLines($account->id)) {
            return false;
        }

        if ($this->hasLedgerEntries($account->id)) {
            return false;
        }

        if ($this->hasLoanProductPaymentMappings($account->id, (string) $account->code)) {
            return false;
        }

        if ($this->hasWalletOrAccountingMappings($account->id, (string) $account->code)) {
            return false;
        }

        if ($this->isRequiredSystemControlAccount($account->id, (string) $account->code)) {
            return false;
        }

        return true;
    }

    private function hasChildren(int $accountId): bool
    {
        if (! Schema::hasColumn('accounting_chart_accounts', 'parent_id')) {
            return false;
        }

        return AccountingChartAccount::query()->where('parent_id', $accountId)->exists();
    }

    private function hasJournalLines(int $accountId): bool
    {
        if (! Schema::hasTable('accounting_journal_lines')) {
            return false;
        }

        return DB::table('accounting_journal_lines')
            ->where('accounting_chart_account_id', $accountId)
            ->exists();
    }

    private function hasLedgerEntries(int $accountId): bool
    {
        foreach (['accounting_ledger_entries', 'general_ledger_entries', 'loan_ledger_entries'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'accounting_chart_account_id')) {
                if (DB::table($table)->where('accounting_chart_account_id', $accountId)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasLoanProductPaymentMappings(int $accountId, string $code): bool
    {
        if (Schema::hasTable('loan_products')) {
            foreach (['principal_account_id', 'interest_account_id', 'penalty_account_id', 'fee_account_id', 'accounting_chart_account_id'] as $col) {
                if (Schema::hasColumn('loan_products', $col) && DB::table('loan_products')->where($col, $accountId)->exists()) {
                    return true;
                }
            }
        }

        if (Schema::hasTable('loan_product_charges')) {
            foreach (['income_account_id', 'receivable_account_id', 'accounting_chart_account_id'] as $col) {
                if (Schema::hasColumn('loan_product_charges', $col) && DB::table('loan_product_charges')->where($col, $accountId)->exists()) {
                    return true;
                }
            }
        }

        if (Schema::hasTable('loan_system_settings') && Schema::hasColumn('loan_system_settings', 'key') && Schema::hasColumn('loan_system_settings', 'value')) {
            if (DB::table('loan_system_settings')
                ->where('key', 'like', 'loan_account_code_%')
                ->where('value', $code)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasWalletOrAccountingMappings(int $accountId, string $code): bool
    {
        if (Schema::hasTable('accounting_wallet_slot_settings') && Schema::hasColumn('accounting_wallet_slot_settings', 'accounting_chart_account_id')) {
            if (DB::table('accounting_wallet_slot_settings')->where('accounting_chart_account_id', $accountId)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('accounting_posting_rules')) {
            foreach (['debit_account_id', 'credit_account_id'] as $col) {
                if (Schema::hasColumn('accounting_posting_rules', $col) && DB::table('accounting_posting_rules')->where($col, $accountId)->exists()) {
                    return true;
                }
            }
        }

        if (Schema::hasTable('accounting_budget_lines') && Schema::hasColumn('accounting_budget_lines', 'accounting_chart_account_id')) {
            if (DB::table('accounting_budget_lines')->where('accounting_chart_account_id', $accountId)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('accounting_bank_reconciliations') && Schema::hasColumn('accounting_bank_reconciliations', 'accounting_chart_account_id')) {
            if (DB::table('accounting_bank_reconciliations')->where('accounting_chart_account_id', $accountId)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('loan_system_settings') && Schema::hasColumn('loan_system_settings', 'key') && Schema::hasColumn('loan_system_settings', 'value')) {
            if (DB::table('loan_system_settings')
                ->where('key', 'like', '%account%')
                ->where('value', $code)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function isRequiredSystemControlAccount(int $accountId, string $code): bool
    {
        $requiredCodes = [
            '1000', '2000', '3000', '4000', '5000',
            '1004', '1200', '2003', // existing seeded defaults used by system settings in this app.
        ];

        if (in_array($code, $requiredCodes, true)) {
            return true;
        }

        if (Schema::hasTable('accounting_controlled_account_approvers') && Schema::hasColumn('accounting_controlled_account_approvers', 'accounting_chart_account_id')) {
            if (DB::table('accounting_controlled_account_approvers')->where('accounting_chart_account_id', $accountId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function applySafePrune(AccountingChartAccount $account): void
    {
        if (Schema::hasColumn('accounting_chart_accounts', 'deleted_at')) {
            DB::table('accounting_chart_accounts')
                ->where('id', $account->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        if (Schema::hasColumn('accounting_chart_accounts', 'is_active')) {
            DB::table('accounting_chart_accounts')
                ->where('id', $account->id)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            return;
        }

        $this->warnings[] = "Cannot prune {$account->code} because neither deleted_at nor is_active exists.";
    }

    private function collectInputDuplicateWarnings(array $rows): void
    {
        $dupes = collect($rows)
            ->groupBy('code')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->keys()
            ->values();

        foreach ($dupes as $code) {
            $this->duplicateWarnings[] = "Input contains duplicate account code {$code}.";
        }
    }

    private function collectDatabaseDuplicateWarnings(): void
    {
        $duplicates = DB::table('accounting_chart_accounts')
            ->select('code', DB::raw('COUNT(*) as c'))
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $this->duplicateWarnings[] = "Database has duplicate code {$row->code} ({$row->c} rows).";
        }
    }

    private function printSummary(
        int $created,
        int $updated,
        int $skipped,
        int $pruneCandidates,
        bool $dryRun,
        bool $pruneUnused
    ): void {
        $this->newLine();
        $this->info($dryRun ? 'Dry run summary' : 'Execution summary');
        $this->line('Created: '.$created);
        $this->line('Updated: '.$updated);
        $this->line('Skipped: '.$skipped);
        $this->line('Prune candidates: '.$pruneCandidates);
        $this->line('Prune mode: '.($pruneUnused ? 'enabled' : 'disabled'));

        $allWarnings = array_values(array_unique(array_merge(
            $this->warnings,
            $this->duplicateWarnings,
            $this->missingParentWarnings
        )));

        if ($allWarnings === []) {
            $this->line('Warnings: 0');
            return;
        }

        $this->line('Warnings: '.count($allWarnings));
        foreach ($allWarnings as $warning) {
            $this->warn('- '.$warning);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seedRows(): array
    {
        return [
            ['code' => '1000', 'name' => 'Assets', 'account_type' => 'asset', 'account_class' => 'Header', 'parent_code' => '', 'is_cash_account' => false],
            ['code' => '1100', 'name' => 'Cash & Bank Accounts', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1101', 'name' => 'Cash on Hand', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1100', 'is_cash_account' => true],
            ['code' => '1102', 'name' => 'M-Pesa Float / Paybill', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1100', 'is_cash_account' => true],
            ['code' => '1103', 'name' => 'Bank - Operating Account', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1100', 'is_cash_account' => true],
            ['code' => '1104', 'name' => 'Bank - Collection Account', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1100', 'is_cash_account' => true],
            ['code' => '1105', 'name' => 'Bank - Disbursement Account', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1100', 'is_cash_account' => true],
            ['code' => '1200', 'name' => 'Loan Portfolio', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1201', 'name' => 'Loan Portfolio - Performing', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1200', 'is_cash_account' => false],
            ['code' => '1202', 'name' => 'Loan Portfolio - Watchlist', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1200', 'is_cash_account' => false],
            ['code' => '1203', 'name' => 'Loan Portfolio - Non-Performing (NPL)', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1200', 'is_cash_account' => false],
            ['code' => '1204', 'name' => 'Loan Portfolio - Restructured', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1200', 'is_cash_account' => false],
            ['code' => '1205', 'name' => 'Loan Portfolio - Written Off', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1200', 'is_cash_account' => false],
            ['code' => '1300', 'name' => 'Accrued / Receivable Accounts', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1301', 'name' => 'Accrued Interest Receivable', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1300', 'is_cash_account' => false],
            ['code' => '1302', 'name' => 'Fees Receivable', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1300', 'is_cash_account' => false],
            ['code' => '1303', 'name' => 'Penalties Receivable', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1300', 'is_cash_account' => false],
            ['code' => '1400', 'name' => 'Other Receivables', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1401', 'name' => 'Staff Advances', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1400', 'is_cash_account' => false],
            ['code' => '1402', 'name' => 'Supplier Advances', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1400', 'is_cash_account' => false],
            ['code' => '1403', 'name' => 'Customer Overpayments (Recoverable)', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1400', 'is_cash_account' => false],
            ['code' => '1500', 'name' => 'Fixed Assets', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1501', 'name' => 'Office Equipment', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1500', 'is_cash_account' => false],
            ['code' => '1502', 'name' => 'Furniture & Fixtures', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1500', 'is_cash_account' => false],
            ['code' => '1503', 'name' => 'Motor Vehicles', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1500', 'is_cash_account' => false],
            ['code' => '1600', 'name' => 'Provisions / Contra Assets', 'account_type' => 'asset', 'account_class' => 'Parent', 'parent_code' => '1000', 'is_cash_account' => false],
            ['code' => '1601', 'name' => 'Loan Loss Provision', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1600', 'is_cash_account' => false],
            ['code' => '1602', 'name' => 'Interest Suspension Account', 'account_type' => 'asset', 'account_class' => 'Detail', 'parent_code' => '1600', 'is_cash_account' => false],

            ['code' => '2000', 'name' => 'Liabilities', 'account_type' => 'liability', 'account_class' => 'Header', 'parent_code' => '', 'is_cash_account' => false],
            ['code' => '2100', 'name' => 'Customer Obligations', 'account_type' => 'liability', 'account_class' => 'Parent', 'parent_code' => '2000', 'is_cash_account' => false],
            ['code' => '2101', 'name' => 'Customer Deposits / Savings', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2100', 'is_cash_account' => false],
            ['code' => '2102', 'name' => 'Customer Overpayments (Liability)', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2100', 'is_cash_account' => false],
            ['code' => '2103', 'name' => 'Unearned Interest', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2100', 'is_cash_account' => false],
            ['code' => '2104', 'name' => 'Client Wallet Balances', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2100', 'is_cash_account' => false],
            ['code' => '2105', 'name' => 'Customer Suspense / Unallocated Payments', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2100', 'is_cash_account' => false],
            ['code' => '2200', 'name' => 'Borrowings', 'account_type' => 'liability', 'account_class' => 'Parent', 'parent_code' => '2000', 'is_cash_account' => false],
            ['code' => '2201', 'name' => 'Bank Loan Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2200', 'is_cash_account' => false],
            ['code' => '2202', 'name' => 'Shareholder Loans', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2200', 'is_cash_account' => false],
            ['code' => '2203', 'name' => 'External Credit Line', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2200', 'is_cash_account' => false],
            ['code' => '2300', 'name' => 'Payables', 'account_type' => 'liability', 'account_class' => 'Parent', 'parent_code' => '2000', 'is_cash_account' => false],
            ['code' => '2301', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2300', 'is_cash_account' => false],
            ['code' => '2302', 'name' => 'Accrued Expenses', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2300', 'is_cash_account' => false],
            ['code' => '2303', 'name' => 'Staff Salaries Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2300', 'is_cash_account' => false],
            ['code' => '2400', 'name' => 'Tax Liabilities', 'account_type' => 'liability', 'account_class' => 'Parent', 'parent_code' => '2000', 'is_cash_account' => false],
            ['code' => '2401', 'name' => 'PAYE Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2400', 'is_cash_account' => false],
            ['code' => '2402', 'name' => 'Withholding Tax', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2400', 'is_cash_account' => false],
            ['code' => '2403', 'name' => 'Excise Duty Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2400', 'is_cash_account' => false],
            ['code' => '2404', 'name' => 'VAT Payable', 'account_type' => 'liability', 'account_class' => 'Detail', 'parent_code' => '2400', 'is_cash_account' => false],

            ['code' => '3000', 'name' => 'Equity', 'account_type' => 'equity', 'account_class' => 'Header', 'parent_code' => '', 'is_cash_account' => false],
            ['code' => '3100', 'name' => 'Capital', 'account_type' => 'equity', 'account_class' => 'Parent', 'parent_code' => '3000', 'is_cash_account' => false],
            ['code' => '3101', 'name' => 'Share Capital', 'account_type' => 'equity', 'account_class' => 'Detail', 'parent_code' => '3100', 'is_cash_account' => false],
            ['code' => '3102', 'name' => 'Director Capital Injection', 'account_type' => 'equity', 'account_class' => 'Detail', 'parent_code' => '3100', 'is_cash_account' => false],
            ['code' => '3200', 'name' => 'Retained Earnings', 'account_type' => 'equity', 'account_class' => 'Parent', 'parent_code' => '3000', 'is_cash_account' => false],
            ['code' => '3201', 'name' => 'Retained Earnings (Accumulated Profit)', 'account_type' => 'equity', 'account_class' => 'Detail', 'parent_code' => '3200', 'is_cash_account' => false],
            ['code' => '3300', 'name' => 'Current Year Profit', 'account_type' => 'equity', 'account_class' => 'Parent', 'parent_code' => '3000', 'is_cash_account' => false],
            ['code' => '3301', 'name' => 'Profit / Loss Account', 'account_type' => 'equity', 'account_class' => 'Detail', 'parent_code' => '3300', 'is_cash_account' => false],

            ['code' => '4000', 'name' => 'Income', 'account_type' => 'income', 'account_class' => 'Header', 'parent_code' => '', 'is_cash_account' => false],
            ['code' => '4100', 'name' => 'Interest Income', 'account_type' => 'income', 'account_class' => 'Parent', 'parent_code' => '4000', 'is_cash_account' => false],
            ['code' => '4101', 'name' => 'Loan Interest Income', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4100', 'is_cash_account' => false],
            ['code' => '4102', 'name' => 'Penalty Interest Income', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4100', 'is_cash_account' => false],
            ['code' => '4200', 'name' => 'Fee Income', 'account_type' => 'income', 'account_class' => 'Parent', 'parent_code' => '4000', 'is_cash_account' => false],
            ['code' => '4201', 'name' => 'Loan Processing Fees', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4200', 'is_cash_account' => false],
            ['code' => '4202', 'name' => 'Late Payment Fees', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4200', 'is_cash_account' => false],
            ['code' => '4203', 'name' => 'Service Charges', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4200', 'is_cash_account' => false],
            ['code' => '4300', 'name' => 'Other Income', 'account_type' => 'income', 'account_class' => 'Parent', 'parent_code' => '4000', 'is_cash_account' => false],
            ['code' => '4301', 'name' => 'Commission Income (M-Pesa / Integrations)', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4300', 'is_cash_account' => false],
            ['code' => '4302', 'name' => 'Miscellaneous Income', 'account_type' => 'income', 'account_class' => 'Detail', 'parent_code' => '4300', 'is_cash_account' => false],

            ['code' => '5000', 'name' => 'Expenses', 'account_type' => 'expense', 'account_class' => 'Header', 'parent_code' => '', 'is_cash_account' => false],
            ['code' => '5100', 'name' => 'Salaries & wages', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5101', 'name' => 'Salaries Expense', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5100', 'is_cash_account' => false],
            ['code' => '5102', 'name' => 'Staff Allowances', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5100', 'is_cash_account' => false],
            ['code' => '5200', 'name' => 'Office & petty cash', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5201', 'name' => 'Rent Expense', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5200', 'is_cash_account' => false],
            ['code' => '5202', 'name' => 'Utilities', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5200', 'is_cash_account' => false],
            ['code' => '5203', 'name' => 'Internet & Communication', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5200', 'is_cash_account' => false],
            ['code' => '5300', 'name' => 'General & administrative', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5301', 'name' => 'Fuel & Transport', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5300', 'is_cash_account' => false],
            ['code' => '5302', 'name' => 'Field Agent Expenses', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5300', 'is_cash_account' => false],
            ['code' => '5400', 'name' => 'Technology', 'account_type' => 'expense', 'account_class' => 'Parent', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5401', 'name' => 'Software Costs', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5400', 'is_cash_account' => false],
            ['code' => '5402', 'name' => 'System Maintenance', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5400', 'is_cash_account' => false],
            ['code' => '5403', 'name' => 'Hosting / Servers', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5400', 'is_cash_account' => false],
            ['code' => '5500', 'name' => 'Credit Risk Costs', 'account_type' => 'expense', 'account_class' => 'Parent', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5501', 'name' => 'Loan Loss Expense / Provision', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5500', 'is_cash_account' => false],
            ['code' => '5502', 'name' => 'Bad Debt Write-Off', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5500', 'is_cash_account' => false],
            ['code' => '5600', 'name' => 'Taxes & Compliance', 'account_type' => 'expense', 'account_class' => 'Parent', 'parent_code' => '5000', 'is_cash_account' => false],
            ['code' => '5601', 'name' => 'Corporate Tax Expense', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5600', 'is_cash_account' => false],
            ['code' => '5602', 'name' => 'Excise Duty Expense', 'account_type' => 'expense', 'account_class' => 'Detail', 'parent_code' => '5600', 'is_cash_account' => false],
        ];
    }
}
