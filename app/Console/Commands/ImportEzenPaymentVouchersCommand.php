<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\EzenPaymentVouchersImportService;
use Illuminate\Console\Command;

class ImportEzenPaymentVouchersCommand extends Command
{
    protected $signature = 'property:import-ezen-payment-vouchers
        {file : EZEN payment voucher listing CSV, PDF, or extracted .txt}
        {--agent-user-id= : Agent that owns the portfolio}
        {--property= : Optional property code filter (e.g. M00044B)}
        {--category= : Limit to remittance, commission, tax, or expense}
        {--limit= : Import only the first N parsed rows}
        {--register-only : Store the voucher register without creating payouts or expenses}
        {--remittances-only : Import landlord remittances only}
        {--expenses-only : Import operating expenses, commissions, and tax only}
        {--post-gl : Also post trust GL for remittance payouts (off by default)}
        {--dry-run : Parse and match without saving}';

    protected $description = 'Import EZEN Payment Voucher Listing (outgoing payments: remittances, commissions, expenses).';

    public function handle(EzenPaymentVouchersImportService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 2);
        $actor = User::query()->find($agentUserId);
        if (! $actor) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;
        $category = $this->option('category') ? strtolower((string) $this->option('category')) : null;
        if ($category !== null && ! in_array($category, ['remittance', 'commission', 'tax', 'expense'], true)) {
            $this->error('Invalid --category. Use remittance, commission, tax, or expense.');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $summary = $service->importFromPath(
            $file,
            $agentUserId,
            $actor,
            (bool) $this->option('dry-run'),
            (bool) $this->option('register-only'),
            (bool) $this->option('remittances-only'),
            (bool) $this->option('expenses-only'),
            (bool) $this->option('post-gl'),
            $this->option('property') ? (string) $this->option('property') : null,
            $category,
            $limit,
        );

        $this->line('Parsed: '.$summary['parsed']);
        $this->line('Register rows: '.$summary['register_upserted']);
        if (! $this->option('register-only')) {
            $this->line('Landlord remittances posted: '.$summary['remittances']);
            $this->line('Operating expenses posted: '.$summary['expenses']);
            $this->line('Commissions posted: '.$summary['commissions']);
            $this->line('Tax / statutory posted: '.$summary['taxes']);
            $this->line('Skipped existing: '.$summary['skipped_existing']);
            $this->line('Unmatched remittance payees: '.$summary['skipped_unmatched']);
        }
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

        $this->info($this->option('dry-run')
            ? 'Dry run complete.'
            : ($this->option('register-only') ? 'Payment voucher register import complete.' : 'Import complete.'));
        $this->line('Review Accounting → Payables → Payment vouchers.');

        return self::SUCCESS;
    }
}
