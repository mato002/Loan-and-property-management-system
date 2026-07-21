<?php

namespace App\Console\Commands;

use App\Services\Property\FinanceIntegrityService;
use App\Services\Property\FinancialReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinance extends Command
{
    protected $signature = 'finance:reconcile
                            {--tenant= : Limit scan to one pm_tenants.id}
                            {--limit=100 : Max mismatches per category}
                            {--layer= : Run a single financial reconciliation layer (legacy; use --scope when possible)}
                            {--scope=all : Scan scope: all, hourly, daily, allocation, suspense, ar_gl, landlord_gl, tenant_credit_gl, penalties_gl}
                            {--audit : Persist immutable accounting audit logs for detected issues}
                            {--alert : Send Slack/email alert when critical drift is detected}';

    protected $description = 'Continuous finance integrity reconciliation — allocation, suspense, AR vs GL, landlord ledger, tenant credits, penalties, and operational drift.';

    public function handle(
        FinanceIntegrityService $integrity,
        FinancialReconciliationService $reconciliation,
    ): int {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));
        $layer = $this->option('layer');
        $layer = is_string($layer) && trim($layer) !== '' ? trim($layer) : null;
        $scope = strtolower(trim((string) $this->option('scope', FinanceIntegrityService::SCOPE_ALL)));
        $persist = (bool) $this->option('audit');
        $alert = (bool) $this->option('alert');

        if ($layer !== null) {
            return $this->runLegacyLayerReconcile($reconciliation, $tenantId, $limit, $layer, $persist);
        }

        $report = $integrity->scan($scope, $tenantId, $limit, $persist, $alert);

        if (($report['ready'] ?? false) !== true) {
            $this->error('Finance integrity scan could not run.');

            return self::FAILURE;
        }

        $summary = $report['summary'] ?? [];
        $this->info('Finance integrity scan — scope: '.$scope.' — '.($report['run_at'] ?? now()->toIso8601String()));
        if ($tenantId) {
            $this->line('Tenant filter: #'.$tenantId);
        }
        $this->newLine();

        foreach ($report['categories'] ?? [] as $category) {
            $catSummary = $category['summary'] ?? [];
            $this->line(sprintf(
                '%s: %d issue(s) [critical: %d, warning: %d, info: %d]',
                (string) ($category['label'] ?? $category['key'] ?? 'category'),
                (int) ($catSummary['count'] ?? 0),
                (int) ($catSummary['critical'] ?? 0),
                (int) ($catSummary['warning'] ?? 0),
                (int) ($catSummary['info'] ?? 0),
            ));

            if (! empty($category['repair_recommendation'])) {
                $this->line('  Repair: '.$category['repair_recommendation']);
            }

            $rows = $category['rows'] ?? collect();
            if ($rows instanceof \Illuminate\Support\Collection && $rows->isNotEmpty() && $this->output->isVerbose()) {
                foreach ($rows->take(3) as $row) {
                    $this->line('  • ['.($row['severity'] ?? 'info').'] '.($row['message'] ?? ''));
                }
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Totals: %d issue(s) — critical: %d, warning: %d, info: %d',
            (int) ($summary['total_issues'] ?? 0),
            (int) ($summary['critical'] ?? 0),
            (int) ($summary['warning'] ?? 0),
            (int) ($summary['info'] ?? 0),
        ));
        $this->line(sprintf(
            'Affected: %d tenant(s), %d invoice(s), %d active categor(ies)',
            (int) ($summary['affected_tenants'] ?? 0),
            (int) ($summary['affected_invoices'] ?? 0),
            (int) ($summary['active_categories'] ?? 0),
        ));

        if ($persist) {
            $this->info(sprintf('Persisted %d finance integrity audit log row(s).', (int) ($report['persisted'] ?? 0)));
        }

        if ($alert && (int) ($summary['critical'] ?? 0) > 0) {
            $this->warn('Critical drift alert dispatched (Slack/email when configured).');
        }

        if ((int) ($summary['total_issues'] ?? 0) === 0) {
            $this->info('All scanned categories are synchronized within tolerance.');

            return self::SUCCESS;
        }

        $this->warn('Finance drift detected. Open the Finance Integrity dashboard for details.');
        $this->comment('Re-run with -v for sample rows, --audit to persist logs, --alert for critical notifications.');

        return (int) ($summary['critical'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runLegacyLayerReconcile(
        FinancialReconciliationService $reconciliation,
        ?int $tenantId,
        int $limit,
        string $layer,
        bool $persist,
    ): int {
        $report = $reconciliation->reconcile($tenantId, $limit, $layer);

        if (($report['ready'] ?? false) !== true) {
            $this->error((string) ($report['message'] ?? 'Accounting tables are not available.'));

            return self::FAILURE;
        }

        $summary = $report['summary'] ?? [];
        foreach ($report['layers'] ?? [] as $layerKey => $layerData) {
            $rows = $layerData['mismatches'] ?? collect();
            $count = $rows instanceof \Illuminate\Support\Collection ? $rows->count() : 0;
            $layerSummary = $summary['by_layer'][$layerKey] ?? [];
            $this->line(sprintf(
                '%s: %d mismatch(es) [critical: %d, warning: %d, info: %d]',
                (string) ($layerData['label'] ?? $layerKey),
                $count,
                (int) ($layerSummary['critical'] ?? 0),
                (int) ($layerSummary['warning'] ?? 0),
                (int) ($layerSummary['info'] ?? 0),
            ));
        }

        if ($persist) {
            $logged = $reconciliation->persistReport($report);
            $this->info(sprintf('Persisted %d financial reconciliation audit log row(s).', $logged));
        }

        return (int) ($summary['total_mismatches'] ?? 0) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
