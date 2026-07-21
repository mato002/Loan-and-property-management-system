<?php

namespace App\Console\Commands;

use App\Services\Property\FinanceIntegrityService;
use Illuminate\Console\Command;

class DetectAllocationDrift extends Command
{
    protected $signature = 'finance:detect-allocation-drift
                            {--tenant= : Limit scan to one pm_tenants.id}
                            {--limit=500 : Max drift rows to report}
                            {--audit : Persist immutable accounting audit logs}
                            {--alert : Send Slack/email alert when critical drift is detected}';

    protected $description = 'Report invoices where amount_paid differs from non-reversed allocation totals.';

    public function handle(FinanceIntegrityService $integrity): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));

        $report = $integrity->scan(
            FinanceIntegrityService::SCOPE_ALLOCATION,
            $tenantId,
            $limit,
            (bool) $this->option('audit'),
            (bool) $this->option('alert'),
        );

        $category = $report['categories'][FinanceIntegrityService::CATEGORY_ALLOCATION_DRIFT] ?? null;
        $rows = $category['rows'] ?? collect();

        if ($rows->isEmpty()) {
            $this->info('No allocation drift detected.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d invoice(s) with allocation drift.', $rows->count()));
        $this->table(
            ['Invoice', 'Tenant', 'Drift', 'Severity', 'Message'],
            $rows->map(fn (array $row) => [
                '#'.(string) ($row['invoice_id'] ?? '—'),
                (string) ($row['tenant_id'] ?? '—'),
                number_format((float) ($row['drift'] ?? 0), 2),
                (string) ($row['severity'] ?? 'info'),
                (string) ($row['message'] ?? ''),
            ])->all()
        );

        if (! empty($category['repair_recommendation'])) {
            $this->comment('Repair: '.$category['repair_recommendation']);
        }

        $critical = (int) (($category['summary'] ?? [])['critical'] ?? 0);

        return $critical > 0 ? self::FAILURE : self::SUCCESS;
    }
}
