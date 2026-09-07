<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenLandlordLedgerImportService;
use Illuminate\Console\Command;

class ImportEzenLandlordLedgerCommand extends Command
{
    protected $signature = 'property:import-ezen-landlord-ledger
        {file : CSV from EZEN account statement (property_name, date, type, txn_no, debit, credit, balance)}
        {--agent-user-id= : Agent that owns the property}
        {--dry-run : Validate without saving}';

    protected $description = 'Import an EZEN property/landlord account statement into take-on + landlord ledger lines.';

    public function handle(EzenLandlordLedgerImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $dryRun = (bool) $this->option('dry-run');
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');
        $summary = $service->importFromPath($path, $agentUserId, $actor, $dryRun);

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Take-on (opening B/F): '.$summary['takeon']);
        $this->line('Ledger lines posted: '.$summary['posted']);
        $this->line('Skipped: '.$summary['skipped']);
        if ($summary['stated_balance'] !== null) {
            $this->line('EZEN closing balance: '.number_format((float) $summary['stated_balance'], 2));
        }
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
        $this->line('Check Accounting → Payables → landlord ledger / take-on for Pazuri.');

        return self::SUCCESS;
    }
}
