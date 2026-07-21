<?php

namespace App\Console\Commands;

use App\Services\Property\AccountingFirebreakService;
use Illuminate\Console\Command;

class DetectAccountingDrift extends Command
{
    protected $signature = 'finance:detect-accounting-drift
                            {--tenant= : Limit scan to one pm_tenants.id}
                            {--limit=100 : Max rows per category}
                            {--audit : Persist immutable accounting audit logs for detected issues}';

    protected $description = 'Report accounting drift: missing GL issuance, landlord ledger gaps, suspense corruption, allocation/GL mismatch, and impossible GL states.';

    public function handle(AccountingFirebreakService $firebreak): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));

        $snapshot = $firebreak->diagnosticsSnapshot($tenantId, $limit);

        if (($snapshot['ready'] ?? false) !== true) {
            $this->error((string) ($snapshot['message'] ?? 'Accounting tables are not available.'));

            return self::FAILURE;
        }

        $categories = [
            'carry_forward_missing_invoice_issued' => 'Carry-forward missing invoice_issued',
            'utility_missing_invoice_issued' => 'Utility missing invoice_issued',
            'invoices_missing_gl_batch' => 'Invoices missing GL batch',
            'landlord_ledger_gaps' => 'Landlord ledger gaps',
            'suspense_double_post_risk' => 'Suspense double-post risk',
            'allocation_gl_drift' => 'Allocation vs GL drift',
            'cash_double_debit' => 'Cash double debit',
            'negative_landlord_payable' => 'Negative landlord payable',
            'invoice_without_ar' => 'Invoice without AR',
            'payment_without_cash' => 'Payment without cash',
        ];

        $totalIssues = 0;
        foreach ($categories as $key => $label) {
            $count = ($snapshot[$key] ?? collect())->count();
            $totalIssues += $count;
            $this->line(sprintf('%s: %d', $label, $count));
        }

        if ($this->option('audit')) {
            $logged = $firebreak->persistDetectedIssues($snapshot);
            $this->info(sprintf('Persisted %d accounting audit log row(s).', $logged));
        }

        if ($totalIssues === 0) {
            $this->info('No accounting drift detected.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d accounting issue row(s) across all categories.', $totalIssues));
        $this->comment('Open property accounting reconciliation dashboard or re-run with --audit to persist immutable logs.');

        return self::FAILURE;
    }
}
