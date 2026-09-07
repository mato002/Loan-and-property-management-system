<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenStatementBalancesImportService;
use App\Services\Property\PropertyTakeonBalanceService;
use Illuminate\Console\Command;

class ImportEzenStatementBalancesCommand extends Command
{
    protected $signature = 'property:import-ezen-statement-balances
        {tenants : CSV of tenant B/F (property_code, unit_label, rent_bf, garbage_bf, water_bf)}
        {--takeon= : Optional landlord take-on CSV (property_code, balance_date, balance)}
        {--agent-user-id= : Agent to match properties}
        {--sync-invoices : Create carry-forward invoices from B/F lines}
        {--dry-run : Validate without saving}';

    protected $description = 'Import EZEN property-statement Balance b/f into tenant carry-forward and optional landlord take-on.';

    public function handle(
        EzenStatementBalancesImportService $tenants,
        PropertyTakeonBalanceService $takeon,
    ): int {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $dryRun = (bool) $this->option('dry-run');
        $syncInvoices = (bool) $this->option('sync-invoices');
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $tenantPath = (string) $this->argument('tenants');
        $this->info('Tenant B/F: '.$tenantPath);
        $summary = $tenants->importTenantBalancesFromPath($tenantPath, $agentUserId, $dryRun, $syncInvoices);

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Leases updated: '.$summary['leases_updated']);
        $this->line('Skipped: '.$summary['skipped']);
        $this->line('Carry-forward invoices created: '.$summary['invoices_created']);
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        $takeonPath = (string) ($this->option('takeon') ?: '');
        if ($takeonPath !== '') {
            $this->info('Landlord take-on: '.$takeonPath);
            $takeonSummary = $takeon->importFromPath($takeonPath, $agentUserId, $actor, $dryRun, true);
            $this->line('Take-on parsed: '.($takeonSummary['parsed'] ?? 0));
            $this->line('Take-on created: '.($takeonSummary['created'] ?? 0));
            $this->line('Take-on updated: '.($takeonSummary['updated'] ?? 0));
            foreach ($takeonSummary['warnings'] ?? [] as $warning) {
                $this->warn((string) $warning);
            }
            foreach ($takeonSummary['errors'] ?? [] as $error) {
                $this->error((string) $error);
            }
            if (($takeonSummary['errors'] ?? []) !== []) {
                return self::FAILURE;
            }
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }
}
