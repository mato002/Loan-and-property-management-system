<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenTenantStatementImportService;
use Illuminate\Console\Command;

class ImportEzenTenantStatementCommand extends Command
{
    protected $signature = 'property:import-ezen-tenant-statement
        {file : EZEN Tenant/Resident Statement of Account .xls (SpreadsheetML)}
        {--agent-user-id= : Agent that owns the portfolio}
        {--post-gl : Post invoice issuance to trust GL (default off)}
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

        $summary = $service->importFromPath(
            (string) $this->argument('file'),
            $agentUserId,
            $actor,
            (bool) $this->option('dry-run'),
            (bool) $this->option('post-gl'),
        );

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

        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }
}
