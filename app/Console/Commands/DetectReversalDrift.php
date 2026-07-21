<?php

namespace App\Console\Commands;

use App\Models\PmAccountingAuditLog;
use App\Services\Property\ReversalIntegrityService;
use Illuminate\Console\Command;

class DetectReversalDrift extends Command
{
    protected $signature = 'finance:detect-reversal-drift
                            {--tenant= : Limit scan to one pm_tenants.id}
                            {--limit=100 : Max rows per category}
                            {--audit : Persist immutable accounting audit logs for detected issues}';

    protected $description = 'Report reversal integrity drift: missing credit memos, unreversed GL after payment/invoice reversal, orphan landlord credits, and unreversed penalties.';

    public function handle(ReversalIntegrityService $integrity): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));

        $snapshot = $integrity->diagnosticsSnapshot($tenantId, $limit);

        if (($snapshot['ready'] ?? false) !== true) {
            $this->error((string) ($snapshot['message'] ?? 'Accounting tables are not available.'));

            return self::FAILURE;
        }

        $categories = [
            'credit_notes_missing_credit_memo' => 'Credit notes missing credit_memo_issued',
            'reversed_payments_active_gl' => 'Reversed payments with active GL batches',
            'reversed_payments_unreversed_tenant_credit' => 'Reversed payments with unreversed tenant credit',
            'cancelled_invoices_unreversed_gl' => 'Cancelled invoices with unreversed issuance GL',
            'cancelled_invoices_unreversed_penalties' => 'Cancelled invoices with unreversed penalties',
            'orphan_payment_landlord_credits' => 'Reversed payments with orphan landlord credits',
        ];

        $totalIssues = 0;
        foreach ($categories as $key => $label) {
            $count = ($snapshot[$key] ?? collect())->count();
            $totalIssues += $count;
            $this->line(sprintf('%s: %d', $label, $count));
        }

        if ($this->option('audit')) {
            $logged = $integrity->persistDetectedIssues($snapshot);
            PmAccountingAuditLog::record(
                PmAccountingAuditLog::ACTION_REVERSAL_INTEGRITY_SCAN,
                'property',
                null,
                [
                    'summary' => 'Reversal integrity scan completed',
                    'payload' => [
                        'tenant_id' => $tenantId,
                        'total_issues' => $totalIssues,
                        'logged' => $logged,
                    ],
                ]
            );
            $this->info(sprintf('Persisted %d reversal integrity audit log row(s).', $logged));
        }

        if ($totalIssues === 0) {
            $this->info('No reversal integrity drift detected.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d reversal integrity issue row(s) across all categories.', $totalIssues));
        $this->comment('Open property accounting reconciliation dashboard or re-run with --audit to persist immutable logs.');

        return self::FAILURE;
    }
}
