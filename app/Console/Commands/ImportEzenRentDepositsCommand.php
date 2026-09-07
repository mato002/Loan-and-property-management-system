<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenRentDepositImportService;
use Illuminate\Console\Command;

class ImportEzenRentDepositsCommand extends Command
{
    protected $signature = 'property:import-ezen-rent-deposits
        {file : CSV from EZEN rent deposit register}
        {--agent-user-id= : Agent that owns the tenants}
        {--no-update : Skip leases that already have a rent deposit}
        {--dry-run : Validate without saving}';

    protected $description = 'Import EZEN rent/water/electricity deposits onto current tenant leases only.';

    public function handle(EzenRentDepositImportService $service): int
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
        $this->line('Current tenants updated: '.$summary['tenants_updated']);
        $this->line('Former occupants skipped: '.$summary['skipped_former']);
        $this->line('Other skipped: '.$summary['skipped']);
        $this->line('Held applied: '.number_format((float) $summary['held_applied'], 2));
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
