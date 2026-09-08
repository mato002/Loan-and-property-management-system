<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenRentReceiptsImportService;
use Illuminate\Console\Command;

class ImportEzenRentReceiptsCommand extends Command
{
    protected $signature = 'property:import-ezen-rent-receipts
        {file : EZEN rent receipt listing PDF or extracted .txt/.csv}
        {--agent-user-id= : Agent that owns the portfolio}
        {--property= : Optional property code filter}
        {--limit= : Import only the first N parsed rows}
        {--include-already-paid : Import even when tenant has no open invoice balance}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import EZEN tenant rent receipts and allocate to open invoices (skips duplicates and already-paid tenants by default).';

    public function handle(EzenRentReceiptsImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $summary = $service->importFromPath(
            (string) $this->argument('file'),
            $agentUserId,
            $actor,
            (bool) $this->option('dry-run'),
            ! (bool) $this->option('include-already-paid'),
            $this->option('property') ? (string) $this->option('property') : null,
            $limit,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Imported: '.$summary['imported']);
        $this->line('Skipped existing: '.$summary['skipped_existing']);
        $this->line('Skipped no tenant: '.$summary['skipped_no_tenant']);
        $this->line('Skipped no open balance: '.$summary['skipped_no_open_balance']);
        $this->line('Skipped zero amount: '.$summary['skipped_zero_amount']);
        $this->line('Allocated to invoices: '.number_format((float) $summary['allocated'], 2));
        $this->line('Unallocated / credit: '.number_format((float) $summary['unallocated'], 2));
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
