<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenRentalInvoicesImportService;
use Illuminate\Console\Command;

class ImportEzenRentalInvoicesCommand extends Command
{
    protected $signature = 'property:import-ezen-rental-invoices
        {file : EZEN rental invoicing schedule PDF or extracted .txt}
        {--agent-user-id= : Agent that owns the portfolio}
        {--property= : Optional property code filter (e.g. A00039A)}
        {--limit= : Import only the first N parsed rows (for testing)}
        {--include-deposits : Import RENT DEPOSIT / WATER DEPOSIT invoice rows}
        {--post-gl : Post invoice issuance to trust GL (default off for bulk history)}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import EZEN rental invoice history (amount, paid, due) for matched tenants/units.';

    public function handle(EzenRentalInvoicesImportService $service): int
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
            (bool) $this->option('include-deposits'),
            (bool) $this->option('post-gl'),
            $this->option('property') ? (string) $this->option('property') : null,
            $limit,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Imported: '.$summary['imported']);
        $this->line('Skipped existing: '.$summary['skipped_existing']);
        $this->line('Skipped deposits: '.$summary['skipped_deposit']);
        $this->line('Skipped unmatched: '.$summary['skipped_unmatched']);
        $this->line('Payments posted: '.$summary['payments_posted']);
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
