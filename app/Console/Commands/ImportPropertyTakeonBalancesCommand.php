<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PropertyTakeonBalanceService;
use Illuminate\Console\Command;

class ImportPropertyTakeonBalancesCommand extends Command
{
    protected $signature = 'property:import-takeon-balances
        {file : CSV path (property_code, balance_date, balance)}
        {--agent-user-id= : Scope property matching to this agent}
        {--dry-run : Validate without saving}
        {--no-update : Skip updating existing take-on rows}';

    protected $description = 'Import property take-on balances from CSV into landlord ledger (Ezen Property Take-on Balance).';

    public function handle(PropertyTakeonBalanceService $service): int
    {
        $path = (string) $this->argument('file');
        $agentUserId = (int) ($this->option('agent-user-id') ?: 1);
        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = ! (bool) $this->option('no-update');

        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $summary = $service->importFromPath($path, $agentUserId, $actor, $dryRun, $updateExisting);

        $this->line('Parsed: '.($summary['parsed'] ?? 0));
        $this->line('Created: '.($summary['created'] ?? 0));
        $this->line('Updated: '.($summary['updated'] ?? 0));
        $this->line('Skipped: '.($summary['skipped'] ?? 0));

        foreach ($summary['warnings'] ?? [] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] ?? [] as $error) {
            $this->error($error);
        }

        if (($summary['errors'] ?? []) !== []) {
            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Import complete.');

        return self::SUCCESS;
    }
}
