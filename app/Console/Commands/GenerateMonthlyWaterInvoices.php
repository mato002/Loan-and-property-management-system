<?php

namespace App\Console\Commands;

use App\Models\PropertyPortalSetting;
use App\Services\Property\WaterBillingService;
use Illuminate\Console\Command;

class GenerateMonthlyWaterInvoices extends Command
{
    protected $signature = 'water:generate-invoices {--month= : Target month YYYY-MM (default: current)} {--due-date= : Due date YYYY-MM-DD (default: 5th of month)}';

    protected $description = 'Generate monthly water invoices from recorded meter readings for active leases (per unit).';

    public function handle(WaterBillingService $billing): int
    {
        $enabled = PropertyPortalSetting::isWaterInvoiceAutomationEnabled();
        if (! $enabled) {
            $this->info('Water invoice automation is off. Skipping water invoice generation.');

            return self::SUCCESS;
        }

        $ym = (string) ($this->option('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $this->error('Invalid --month. Use YYYY-MM.');

            return self::FAILURE;
        }

        $due = (string) ($this->option('due-date') ?: ($ym.'-05'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $this->error('Invalid --due-date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $stats = $billing->generateInvoicesForMonth(
            billingMonth: $ym,
            dueDate: $due,
            actor: null,
            source: 'water:generate-invoices',
            postToGl: true,
            autoApplyCredit: true,
        );

        $this->info(sprintf(
            'Water invoices for %s (due %s): created=%d, skipped_no_lease=%d, skipped_duplicate=%d, credit_applied=%d.',
            $ym,
            $due,
            $stats['created'],
            $stats['skipped_no_lease'],
            $stats['skipped_duplicate'],
            $stats['credit_applied'],
        ));

        return self::SUCCESS;
    }
}
