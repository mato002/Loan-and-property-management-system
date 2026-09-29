<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenLandlordInvoicesImportService;
use Illuminate\Console\Command;

class ImportEzenLandlordInvoicesCommand extends Command
{
    protected $signature = 'property:import-ezen-landlord-invoices
        {file : JSON list of EZEN landlord management-fee invoices}
        {--agent-user-id= : Agent that owns the portfolio}
        {--post-fees : Also post unmatched period management-fee journals}
        {--dry-run : Validate without saving}';

    protected $description = 'Import EZEN Landlords Invoices (management fees) into the Passion landlord-invoice register.';

    public function handle(EzenLandlordInvoicesImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 1);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $summary = $service->importFromJsonPath(
            (string) $this->argument('file'),
            $agentUserId,
            (bool) $this->option('dry-run'),
            (bool) $this->option('post-fees'),
            $actor->id,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Created: '.$summary['created']);
        $this->line('Updated: '.$summary['updated']);
        $this->line('Unchanged: '.$summary['unchanged']);
        $this->line('Unmatched property: '.$summary['unmatched_property']);
        $this->line('Fees posted: '.$summary['fees_posted']);
        $this->line('Fees already posted / skipped: '.$summary['fees_skipped']);
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Landlord invoices import complete.');

        return self::SUCCESS;
    }
}
