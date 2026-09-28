<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\CoopBankAccountStatementImportService;
use Illuminate\Console\Command;

class ImportCoopBankAccountStatementCommand extends Command
{
    protected $signature = 'property:import-coop-bank-statement
        {file : Co-op Statement of Account PDF or extracted .txt}
        {--agent-user-id= : Agent that owns the portfolio}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import a Co-operative Bank statement of account into Accounting → Cash & Bank → Reconciliation.';

    public function handle(CoopBankAccountStatementImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $summary = $service->importFromPath(
            $file,
            $agentUserId,
            (bool) $this->option('dry-run'),
        );

        $this->line('Parsed lines: '.$summary['parsed']);
        $this->line('Statements: '.$summary['statements']);
        $this->line('Register rows: '.$summary['lines_upserted']);
        $this->line('Matched receipts: '.$summary['matched']);
        $this->line('Unmatched credits: '.$summary['unmatched']);
        $this->line('Bank-only (cheque / charges): '.$summary['bank_only']);
        $this->line('Parsed credits: '.number_format((float) $summary['credit_total'], 2));
        $this->line('Parsed debits: '.number_format((float) $summary['debit_total'], 2));
        foreach ($summary['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        if ($summary['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Bank statement import complete.');
        $this->line('Review Accounting → Cash & Bank → Reconciliation.');

        return self::SUCCESS;
    }
}
