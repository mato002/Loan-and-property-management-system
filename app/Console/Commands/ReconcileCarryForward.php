<?php

namespace App\Console\Commands;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Services\Property\CarryForwardAccountingService;
use App\Services\Property\CarryForwardConsolidationService;
use Illuminate\Console\Command;

class ReconcileCarryForward extends Command
{
    protected $signature = 'finance:reconcile-carry-forward
                            {--tenant= : Reconcile one pm_tenants.id}
                            {--lease= : Reconcile one pm_leases.id}
                            {--backfill-gl : Backfill missing carry-forward invoice_issued GL batches after reconcile}';

    protected $description = 'Reconcile carry-forward lifecycle (capture → invoice → settle), auto-apply tenant credit, and report duplicate or drift issues.';

    public function handle(
        CarryForwardConsolidationService $consolidation,
        CarryForwardAccountingService $carryForwardAccounting,
    ): int {
        $tenantId = (int) ($this->option('tenant') ?? 0);
        $leaseId = (int) ($this->option('lease') ?? 0);

        if ($leaseId > 0) {
            $lease = PmLease::query()->with(['units.property'])->find($leaseId);
            if (! $lease) {
                $this->error('Lease not found.');

                return self::FAILURE;
            }

            $result = $consolidation->reconcileLease($lease);
            $this->info("Lease #{$leaseId} reconciled.");
            $this->line('Invoices created: '.(string) ($result['invoices_created'] ?? 0));
            $this->reportIssues($result['issues'] ?? []);
            $this->maybeBackfillGl($carryForwardAccounting, null, $leaseId);

            return empty($result['issues'] ?? []) ? self::SUCCESS : self::FAILURE;
        }

        if ($tenantId > 0) {
            $result = $consolidation->reconcileTenant($tenantId);
            $this->info("Tenant #{$tenantId} reconciled across {$result['leases']} lease(s).");
            $this->line('Invoices created: '.(string) ($result['invoices_created'] ?? 0));
            $this->reportIssues($result['issues'] ?? []);
            $this->maybeBackfillGl($carryForwardAccounting, $tenantId, null);

            return empty($result['issues'] ?? []) ? self::SUCCESS : self::FAILURE;
        }

        $tenants = PmTenant::query()->orderBy('id')->pluck('id');
        $totalIssues = 0;
        $leasesTouched = 0;
        $invoicesCreated = 0;

        foreach ($tenants as $id) {
            $result = $consolidation->reconcileTenant((int) $id);
            $leasesTouched += (int) ($result['leases'] ?? 0);
            $invoicesCreated += (int) ($result['invoices_created'] ?? 0);
            $issueCount = count($result['issues'] ?? []);
            $totalIssues += $issueCount;
            if ($issueCount > 0) {
                $this->warn("Tenant #{$id}: {$issueCount} issue(s).");
            }
        }

        $this->newLine();
        $this->table(
            ['tenants', 'leases_reconciled', 'invoices_created', 'issues'],
            [[(string) $tenants->count(), (string) $leasesTouched, (string) $invoicesCreated, (string) $totalIssues]]
        );

        $this->maybeBackfillGl($carryForwardAccounting, null, null);

        return $totalIssues === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function maybeBackfillGl(
        CarryForwardAccountingService $carryForwardAccounting,
        ?int $tenantId,
        ?int $leaseId,
    ): void {
        if (! $this->option('backfill-gl')) {
            return;
        }

        $result = $carryForwardAccounting->backfillMissing($tenantId, $leaseId, 500, false);
        $this->info(sprintf(
            'GL backfill: scanned %d, posted %d, skipped %d, errors %d.',
            (int) $result['scanned'],
            (int) $result['posted'],
            (int) $result['skipped'],
            count($result['errors']),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function reportIssues(array $issues): void
    {
        if ($issues === []) {
            $this->info('No carry-forward reconciliation issues detected.');

            return;
        }

        $this->warn(count($issues).' issue(s) detected:');
        foreach ($issues as $issue) {
            $this->line('- '.(string) ($issue['message'] ?? json_encode($issue)));
        }
    }
}
