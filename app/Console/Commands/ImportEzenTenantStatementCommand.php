<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenTenantStatementImportService;
use Illuminate\Console\Command;

class ImportEzenTenantStatementCommand extends Command
{
    protected $signature = 'property:import-ezen-tenant-statement
        {file? : EZEN Tenant/Resident Statement of Account .xls (SpreadsheetML)}
        {--dir= : Folder of .xls files (duplicates by TNT account are skipped)}
        {--all : Import every unique statement in storage/passion-legacy/tenant-statements}
        {--agent-user-id= : Agent that owns the portfolio}
        {--post-gl : Post invoice issuance to trust GL (default off)}
        {--create-missing : Create the tenant and lease when the TNT account is new}
        {--sync-deposits : Copy imported EZEN deposit invoices onto the tenant 360 deposit tab}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import missing DBNs, rent deposits, and opening balances from an EZEN tenant statement export.';

    public function handle(EzenTenantStatementImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 1);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        if ($this->option('sync-deposits')) {
            $result = $service->syncDepositsFromImportedInvoices(
                $agentUserId,
                (bool) $this->option('dry-run'),
            );
            $this->info('Tenants with EZEN deposit invoices: '.$result['tenants']);
            $this->line('Leases updated: '.$result['leases_updated']);
            $this->line('Trust deposits recorded: '.$result['held_created']);
            $this->line('Already on deposit tab: '.$result['skipped']);
            foreach ($result['warnings'] as $warning) {
                $this->warn($warning);
            }

            $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Deposit tab sync complete.');

            return self::SUCCESS;
        }

        $paths = $this->statementPaths($service);
        if ($paths === []) {
            $this->error('Pass a .xls file, --dir=, --all, or --sync-deposits.');

            return self::FAILURE;
        }

        $failed = false;
        $complete = 0;
        $incomplete = 0;
        $unmatched = 0;
        $errorCount = 0;
        $rows = [];
        $gapDetails = [];

        foreach ($paths as $path) {
            $summary = $service->importFromPath(
                $path,
                $agentUserId,
                $actor,
                (bool) $this->option('dry-run'),
                (bool) $this->option('post-gl'),
                (bool) $this->option('create-missing'),
            );

            if (count($paths) === 1) {
                $this->printSingleSummary($summary);
            }

            foreach ($summary['warnings'] as $warning) {
                $this->warn(($summary['account'] ?: basename($path)).': '.$warning);
            }
            $isUnmatched = (int) $summary['skipped_unmatched'] > 0;
            foreach ($summary['errors'] as $error) {
                if ($isUnmatched && count($paths) > 1) {
                    $this->warn(($summary['account'] ?: basename($path)).': '.$error);

                    continue;
                }
                $failed = true;
                $errorCount++;
                $this->error(($summary['account'] ?: basename($path)).': '.$error);
            }

            $missingCharges = (int) $summary['imported'];
            $missingPayments = (int) $summary['payments_imported'];
            $isUnmatched = (int) $summary['skipped_unmatched'] > 0;
            $hasGap = $missingCharges > 0 || $missingPayments > 0;

            if ($isUnmatched) {
                $unmatched++;
            } elseif ($hasGap) {
                $incomplete++;
            } else {
                $complete++;
            }

            if ($isUnmatched || $hasGap || $summary['errors'] !== []) {
                $rows[] = [
                    $summary['account'] ?: '—',
                    $summary['tenant'] ?: '—',
                    $summary['property'] ?: '—',
                    $summary['unit'] ?: '—',
                    $missingCharges,
                    $missingPayments,
                    $isUnmatched ? 'unmatched' : ($summary['errors'] !== [] ? 'error' : 'missing lines'),
                    basename($path),
                ];
                if ($summary['pending_charges'] !== [] || $summary['pending_payments'] !== []) {
                    $gapDetails[] = ($summary['account'] ?: basename($path))
                        .' | '.($summary['tenant'] ?: '—')
                        .' | '.($summary['property'] ?: '—')
                        .' / '.($summary['unit'] ?: '—')
                        .($summary['pending_charges'] !== [] ? ' | charges: '.implode('; ', $summary['pending_charges']) : '')
                        .($summary['pending_payments'] !== [] ? ' | payments: '.implode('; ', $summary['pending_payments']) : '');
                }
            }
        }

        if (count($paths) > 1 || $this->option('all') || $this->option('dir')) {
            $this->newLine();
            $this->info('Statement census (unique TNT files: '.count($paths).')');
            $this->line('Complete: '.$complete);
            $this->line('Missing EZEN lines (same status as John Njunge): '.$incomplete);
            $this->line('Unmatched tenant/unit: '.$unmatched);
            $this->line('Files with errors: '.$errorCount);
            if ($rows !== []) {
                $this->table(
                    ['Account', 'Tenant', 'Property', 'Unit', 'Missing charges', 'Missing payments', 'Status', 'File'],
                    $rows,
                );
            }
            if ($gapDetails !== []) {
                $this->newLine();
                $this->line('Missing line detail:');
                foreach ($gapDetails as $detail) {
                    $this->line(' - '.$detail);
                }
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function statementPaths(EzenTenantStatementImportService $service): array
    {
        $file = $this->argument('file');
        if (is_string($file) && $file !== '') {
            return [$file];
        }

        $dir = (string) $this->option('dir');
        if ($this->option('all')) {
            $dir = storage_path('passion-legacy/tenant-statements');
        }
        if ($dir === '') {
            return [];
        }
        if (! is_dir($dir)) {
            $this->error('Directory not found: '.$dir);

            return [];
        }

        $files = glob(rtrim($dir, '\\/').DIRECTORY_SEPARATOR.'*.xls') ?: [];
        $byHash = [];
        foreach ($files as $path) {
            $hash = md5_file($path);
            if ($hash === false || isset($byHash[$hash])) {
                continue;
            }
            $byHash[$hash] = $path;
        }

        $byAccount = [];
        foreach ($byHash as $path) {
            try {
                $parsed = $service->parseSpreadsheet($path);
            } catch (\Throwable $e) {
                $this->warn(basename($path).': '.$e->getMessage());
                $byAccount['file:'.basename($path)] = [
                    'path' => $path,
                    'lines' => 0,
                ];

                continue;
            }

            $account = strtoupper(trim((string) ($parsed['account'] ?? '')));
            $key = $account !== '' ? $account : 'file:'.basename($path);
            $lines = count($parsed['charges']) + count($parsed['payments']);
            if (! isset($byAccount[$key]) || $lines > (int) $byAccount[$key]['lines']) {
                $byAccount[$key] = [
                    'path' => $path,
                    'lines' => $lines,
                ];
            }
        }

        return array_values(array_map(fn (array $row): string => $row['path'], $byAccount));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function printSingleSummary(array $summary): void
    {
        $this->line('Tenant: '.($summary['tenant'] ?: '—'));
        $this->line('Account: '.($summary['account'] ?: '—'));
        $this->line('Property: '.($summary['property'] ?: '—'));
        $this->line('Unit: '.($summary['unit'] ?: '—'));
        $this->line('Charges parsed: '.$summary['charges_parsed']);
        $this->line('Imported: '.$summary['imported']);
        $this->line('Skipped existing: '.$summary['skipped_existing']);
        $this->line('Payments parsed: '.$summary['payments_parsed']);
        $this->line('Payments imported: '.$summary['payments_imported']);
        $this->line('Payments skipped existing: '.$summary['payments_skipped_existing']);
        $this->line('Skipped unmatched: '.$summary['skipped_unmatched']);
        $this->line('Payments reallocated: '.number_format((float) $summary['payments_reallocated'], 2));
    }
}
