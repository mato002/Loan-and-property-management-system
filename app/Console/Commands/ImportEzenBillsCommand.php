<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenBillsImportService;
use Illuminate\Console\Command;

class ImportEzenBillsCommand extends Command
{
    protected $signature = 'property:import-ezen-bills
        {file : EZEN Bills Listing CSV, PDF, or extracted .txt}
        {--agent-user-id= : Agent that owns the portfolio}
        {--vendor= : Optional vendor name filter}
        {--status= : Limit to paid, partial, or unpaid}
        {--limit= : Import only the first N parsed rows}
        {--dry-run : Parse without saving}';

    protected $description = 'Import EZEN Bills Listing (vendor bills / accounts payable) into Accounting → Payables → Accounts payable.';

    public function handle(EzenBillsImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;
        $status = $this->option('status') ? strtolower((string) $this->option('status')) : null;
        if ($status !== null && ! in_array($status, ['paid', 'partial', 'unpaid'], true)) {
            $this->error('Invalid --status. Use paid, partial, or unpaid.');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $summary = $service->importFromPath(
            $file,
            $agentUserId,
            (bool) $this->option('dry-run'),
            $this->option('vendor') ? (string) $this->option('vendor') : null,
            $status,
            $limit,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Register rows: '.$summary['register_upserted']);
        $this->line('Vendors: '.$summary['vendors']);
        $this->line('Skipped zero amount: '.$summary['skipped_zero']);
        $this->line('Skipped by filter: '.$summary['skipped_filtered']);
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Bills listing import complete.');
        $this->line('Review Accounting → Payables → Accounts payable.');

        return self::SUCCESS;
    }
}
