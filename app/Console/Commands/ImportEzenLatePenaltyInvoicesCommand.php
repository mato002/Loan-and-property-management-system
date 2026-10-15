<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenLatePenaltyInvoicesImportService;
use Illuminate\Console\Command;

class ImportEzenLatePenaltyInvoicesCommand extends Command
{
    protected $signature = 'property:import-ezen-late-penalty-invoices
        {file : EZEN penalty invoice CSV or Excel XML (.xls) listing}
        {--agent-user-id=2 : Agent that owns the portfolio}
        {--property= : Optional property code filter}
        {--limit= : Import only the first N parsed rows}
        {--post-gl : Post invoice issuance to trust GL}
        {--apply : Create the invoices; without this option the command is a dry run}
        {--dry-run : Explicitly force a dry run}';

    protected $description = 'Import EZEN late penalty invoices from the penalty invoice register.';

    public function handle(EzenLatePenaltyInvoicesImportService $service): int
    {
        $agentUserId = (int) $this->option('agent-user-id');
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
            ! (bool) $this->option('apply') || (bool) $this->option('dry-run'),
            (bool) $this->option('post-gl'),
            $this->option('property') ? (string) $this->option('property') : null,
            $limit,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Imported: '.$summary['imported']);
        $this->line('Skipped existing: '.$summary['skipped_existing']);
        $this->line('Skipped unmatched: '.$summary['skipped_unmatched']);
        $this->line('Paid source rows (reference only): '.$summary['source_paid_rows']);
        $this->line('Paid source total (reference only): '.number_format((float) $summary['source_paid_total'], 2));
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info(! $this->option('apply') || $this->option('dry-run')
            ? 'Dry run complete; no invoices or receipts were created.'
            : 'Late penalty invoice import complete; no receipts were created.');

        return self::SUCCESS;
    }
}
