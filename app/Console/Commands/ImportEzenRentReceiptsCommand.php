<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenRentReceiptsImportService;
use Illuminate\Console\Command;

class ImportEzenRentReceiptsCommand extends Command
{
    protected $signature = 'property:import-ezen-rent-receipts
        {file? : EZEN rent receipt listing PDF or extracted .txt/.csv (not required with --sync-from-register)}
        {--agent-user-id= : Agent that owns the portfolio}
        {--property= : Optional property code filter}
        {--limit= : Import only the first N parsed rows}
        {--include-already-paid : Import even when tenant has no open invoice balance}
        {--enrich-only : Backfill M-Pesa ref / payment method on existing invoice-import payments only}
        {--register-only : Import all EZEN receipt rows into the receipt register (no payments created)}
        {--sync-from-register : Copy refs from receipt register onto existing payments (no file needed)}
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

        if ($this->option('sync-from-register')) {
            $syncSummary = $service->syncPaymentsFromRegister($agentUserId, (bool) $this->option('dry-run'));
            $this->line('Register rows: '.$syncSummary['register_rows']);
            $this->line('Register rows matched: '.$syncSummary['register_rows_matched']);
            $this->line('Group matches: '.($syncSummary['group_matches'] ?? 0));
            $this->line('Fallback matches: '.($syncSummary['fallback_matches'] ?? 0));
            $this->line('Payments updated: '.$syncSummary['payments_updated']);
            $this->line('Skipped no tenant: '.$syncSummary['skipped_no_tenant']);
            $this->line('Skipped no payment match: '.$syncSummary['skipped_no_payment']);
            foreach ($syncSummary['warnings'] as $warning) {
                $this->warn($warning);
            }
            $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Payment sync from register complete.');

            return self::SUCCESS;
        }

        $file = (string) ($this->argument('file') ?? '');
        if ($file === '') {
            $this->error('File path is required unless using --sync-from-register.');

            return self::FAILURE;
        }

        $summary = $service->importFromPath(
            $file,
            $agentUserId,
            $actor,
            (bool) $this->option('dry-run'),
            ! (bool) $this->option('include-already-paid'),
            $this->option('property') ? (string) $this->option('property') : null,
            $limit,
            (bool) $this->option('enrich-only'),
            (bool) $this->option('register-only'),
        );

        $this->line('Parsed: '.$summary['parsed']);
        if ($this->option('register-only')) {
            $this->line('Receipt register rows saved: '.$summary['register_upserted']);
        } elseif ($this->option('enrich-only')) {
            $this->line('Enriched existing payments: '.$summary['enriched_existing']);
            $this->line('No matching payment: '.$summary['skipped_no_match']);
        } else {
            $this->line('Imported: '.$summary['imported']);
            $this->line('Skipped existing: '.$summary['skipped_existing']);
            $this->line('Skipped no tenant: '.$summary['skipped_no_tenant']);
            $this->line('Skipped no open balance: '.$summary['skipped_no_open_balance']);
            $this->line('Enriched existing payments: '.$summary['enriched_existing']);
            $this->line('Skipped zero amount: '.$summary['skipped_zero_amount']);
            $this->line('Allocated to invoices: '.number_format((float) $summary['allocated'], 2));
            $this->line('Unallocated / credit: '.number_format((float) $summary['unallocated'], 2));
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

        $this->info($this->option('dry-run')
            ? 'Dry run complete.'
            : ($this->option('register-only')
                ? 'Receipt register import complete.'
                : ($this->option('enrich-only') ? 'Enrichment complete.' : 'Import complete.')));

        return self::SUCCESS;
    }
}
