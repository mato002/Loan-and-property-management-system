<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenLatePenaltyInvoicesImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportLatePenaltyInvoices extends Command
{
    protected $signature = 'property:import-late-penalty-invoices
                            {path : Server-side path to the prepared penalty CSV}
                            {--agent-user-id= : Passion Homes agent user ID}
                            {--property= : Optional property code filter}
                            {--limit= : Optional maximum number of rows}
                            {--apply : Create the invoices; without this flag the command is a dry run}
                            {--post-gl : Also post invoice journals to the general ledger}';

    protected $description = 'Import late penalty invoices only; source Paid values remain references and never create receipts.';

    public function handle(EzenLatePenaltyInvoicesImportService $importer): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('CSV file does not exist or is not readable: '.$path);

            return self::FAILURE;
        }

        $agentUserId = filter_var($this->option('agent-user-id'), FILTER_VALIDATE_INT);
        if (! $agentUserId || $agentUserId < 1) {
            $this->error('Pass a valid --agent-user-id for the Passion Homes workspace.');

            return self::FAILURE;
        }

        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error('No user exists with ID '.$agentUserId.'.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        try {
            $summary = $importer->importFromPath(
                $path,
                (int) $agentUserId,
                $actor,
                ! $apply,
                (bool) $this->option('post-gl'),
                $this->option('property') !== null ? (string) $this->option('property') : null,
                $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Result', 'Count / amount'],
            [
                [$apply ? 'Invoices created' : 'Invoices that would be created', $summary['imported']],
                ['Rows parsed', $summary['parsed']],
                ['Already imported', $summary['skipped_existing']],
                ['Unmatched rows skipped', $summary['skipped_unmatched']],
                ['Rows with source Paid values (reference only)', $summary['source_paid_rows']],
                ['Source Paid total (reference only)', 'KES '.number_format($summary['source_paid_total'], 2)],
            ],
        );

        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($apply
            ? 'Import finished. No receipts were created.'
            : 'Dry run only; no invoices or receipts were created. Review the matches, then rerun with --apply.');

        return self::SUCCESS;
    }
}
