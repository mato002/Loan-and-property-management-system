<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenBillingScheduleImportService;
use Illuminate\Console\Command;

class ImportEzenBillingScheduleCommand extends Command
{
    protected $signature = 'property:import-ezen-billing-schedule
        {file : EZEN rental billing schedule PDF or .txt}
        {--agent-user-id= : Agent that owns the tenants}
        {--no-update : Skip leases that already have utility extras}
        {--dry-run : Validate without saving}';

    protected $description = 'Import EZEN Sep billing S.Charge/Utility onto current leases (not rent).';

    public function handle(EzenBillingScheduleImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $dryRun = (bool) $this->option('dry-run');
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');
        $summary = $service->importFromPath(
            $path,
            $agentUserId,
            $dryRun,
            ! (bool) $this->option('no-update'),
        );

        $this->line('Parsed rows: '.$summary['parsed']);
        $this->line('Rows with utility: '.$summary['with_utility']);
        $this->line('Leases updated: '.$summary['leases_updated']);
        $this->line('Skipped zero utility: '.$summary['skipped_zero']);
        $this->line('Skipped unmatched: '.$summary['skipped_unmatched']);
        $this->line('Skipped ambiguous: '.$summary['skipped_ambiguous']);
        $this->line('Utility applied: '.number_format((float) $summary['utility_applied'], 2));
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }
}
