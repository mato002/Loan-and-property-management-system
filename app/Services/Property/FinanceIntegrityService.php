<?php

namespace App\Services\Property;

use App\Models\PmAccountingAuditLog;
use Illuminate\Support\Collection;

/**
 * Unified finance integrity scans for continuous drift detection (Batch D).
 */
final class FinanceIntegrityService
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_HOURLY = 'hourly';

    public const SCOPE_DAILY = 'daily';

    public const SCOPE_ALLOCATION = 'allocation';

    public const SCOPE_SUSPENSE = 'suspense';

    public const SCOPE_AR_GL = 'ar_gl';

    public const SCOPE_LANDLORD_GL = 'landlord_gl';

    public const SCOPE_TENANT_CREDIT_GL = 'tenant_credit_gl';

    public const SCOPE_PENALTIES_GL = 'penalties_gl';

    public const CATEGORY_ALLOCATION_DRIFT = 'allocation_drift';

    public const CATEGORY_SUSPENSE_MISMATCH = 'suspense_mismatch';

    public const CATEGORY_GL_AR_MISMATCH = 'gl_ar_mismatch';

    public const CATEGORY_LANDLORD_IMBALANCE = 'landlord_imbalance';

    public const CATEGORY_TENANT_CREDIT_GL = 'tenant_credit_gl_mismatch';

    public const CATEGORY_PENALTY_GL = 'penalty_gl_mismatch';

    public const CATEGORY_ORPHAN_ALLOCATIONS = 'orphan_allocations';

    public const CATEGORY_STALE_CARRY_FORWARD = 'stale_carry_forward';

    /** @var array<string, string> */
    private const REPAIR_HINTS = [
        self::CATEGORY_ALLOCATION_DRIFT => 'Run allocation repair for affected tenants (tenant statement → Repair allocations) or `php artisan finance:detect-allocation-drift`.',
        self::CATEGORY_SUSPENSE_MISMATCH => 'Review payment finalize path; ensure unmatched payments use payment_unmatched_suspense only. Run `php artisan finance:detect-accounting-drift --audit`.',
        self::CATEGORY_GL_AR_MISMATCH => 'Run `php artisan finance:detect-accounting-drift --audit` and backfill missing invoice_issued batches (`finance:backfill-carry-forward-gl`).',
        self::CATEGORY_LANDLORD_IMBALANCE => 'Run `php artisan finance:backfill-landlord-subledger` and `finance:reconcile-landlord-subledger`.',
        self::CATEGORY_TENANT_CREDIT_GL => 'Review tenant credit apply/refund flows and payment reversal hardening.',
        self::CATEGORY_PENALTY_GL => 'Reverse and re-apply penalties or post missing water_penalty_applied GL batches.',
        self::CATEGORY_ORPHAN_ALLOCATIONS => 'Remove or reverse allocations on cancelled/deleted invoices; re-run payment settlement for affected payments.',
        self::CATEGORY_STALE_CARRY_FORWARD => 'Consolidate or invoice lease carry-forward lines; run `finance:reconcile-carry-forward`.',
    ];

    public function __construct(
        private FinancialReconciliationService $reconciliation,
        private FinanceFirebreakService $financeFirebreak,
        private AccountingFirebreakService $accountingFirebreak,
    ) {}

    /**
     * Live dashboard snapshot (all categories).
     *
     * @return array<string, mixed>
     */
    public function dashboard(?int $tenantId = null, int $limit = 100): array
    {
        return $this->scan(self::SCOPE_ALL, $tenantId, $limit, persist: false, alert: false);
    }

    /**
     * Scoped integrity scan for CLI scheduler.
     *
     * @return array<string, mixed>
     */
    public function scan(
        string $scope = self::SCOPE_ALL,
        ?int $tenantId = null,
        int $limit = 100,
        bool $persist = false,
        bool $alert = false,
    ): array {
        $scope = strtolower(trim($scope));
        if ($scope === '') {
            $scope = self::SCOPE_ALL;
        }

        $categories = $this->buildCategories($scope, $tenantId, $limit);
        $summary = $this->summarizeCategories($categories);

        $report = [
            'ready' => true,
            'scope' => $scope,
            'run_at' => now()->toIso8601String(),
            'tenant_filter' => $tenantId,
            'categories' => $categories,
            'summary' => $summary,
        ];

        if ($persist) {
            $report['persisted'] = $this->persistScan($report);
        }

        if ($alert) {
            app(FinanceIntegrityAlertService::class)->notifyIfCritical($report, $scope);
        }

        return $report;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildCategories(string $scope, ?int $tenantId, int $limit): array
    {
        $categories = [];
        $runAllocation = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_HOURLY, self::SCOPE_ALLOCATION]);
        $runSuspense = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_HOURLY, self::SCOPE_SUSPENSE]);
        $runArGl = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_DAILY, self::SCOPE_AR_GL]);
        $runLandlordGl = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_DAILY, self::SCOPE_LANDLORD_GL]);
        $runTenantCreditGl = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_DAILY, self::SCOPE_TENANT_CREDIT_GL]);
        $runPenaltiesGl = $this->scopeIncludes($scope, [self::SCOPE_ALL, self::SCOPE_DAILY, self::SCOPE_PENALTIES_GL]);
        $runOpsExtras = $scope === self::SCOPE_ALL;

        if ($runAllocation) {
            $rows = $this->normalizeAllocationDrift(
                $this->financeFirebreak->detectAllocationDrift($tenantId, $limit),
                $this->reconciliation->reconcileAllocationsVsAmountPaid($tenantId, $limit),
            );
            $categories[self::CATEGORY_ALLOCATION_DRIFT] = $this->wrapCategory(
                self::CATEGORY_ALLOCATION_DRIFT,
                'Allocation drift',
                $rows,
            );
        }

        if ($runSuspense) {
            $finSuspense = $this->reconciliation->reconcileSuspenseVsUnmatchedPayments($tenantId, $limit);
            $acctSnapshot = $this->accountingFirebreak->diagnosticsSnapshot($tenantId, $limit);
            $acctSuspense = ($acctSnapshot['ready'] ?? false) === true
                ? ($acctSnapshot['suspense_double_post_risk'] ?? collect())
                : collect();
            $rows = $this->normalizeSuspenseRows($finSuspense, $acctSuspense);
            $categories[self::CATEGORY_SUSPENSE_MISMATCH] = $this->wrapCategory(
                self::CATEGORY_SUSPENSE_MISMATCH,
                'Suspense mismatch',
                $rows,
            );
        }

        if ($runArGl) {
            $rows = $this->normalizeFinReconRows(
                $this->reconciliation->reconcileInvoiceArVsGlAr($tenantId, $limit),
                self::CATEGORY_GL_AR_MISMATCH,
            );
            $categories[self::CATEGORY_GL_AR_MISMATCH] = $this->wrapCategory(
                self::CATEGORY_GL_AR_MISMATCH,
                'GL AR mismatch',
                $rows,
            );
        }

        if ($runLandlordGl) {
            $finLandlord = $this->reconciliation->reconcileLandlordSubledgerVsGl2100($limit);
            $acctSnapshot = $this->accountingFirebreak->diagnosticsSnapshot($tenantId, $limit);
            $ledgerGaps = ($acctSnapshot['ready'] ?? false) === true
                ? ($acctSnapshot['landlord_ledger_gaps'] ?? collect())
                : collect();
            $negativePayable = ($acctSnapshot['ready'] ?? false) === true
                ? ($acctSnapshot['negative_landlord_payable'] ?? collect())
                : collect();
            $rows = $this->normalizeLandlordRows($finLandlord, $ledgerGaps, $negativePayable);
            $categories[self::CATEGORY_LANDLORD_IMBALANCE] = $this->wrapCategory(
                self::CATEGORY_LANDLORD_IMBALANCE,
                'Landlord imbalance',
                $rows,
            );
        }

        if ($runTenantCreditGl) {
            $rows = $this->normalizeFinReconRows(
                $this->reconciliation->reconcileTenantCreditsVsLiability($tenantId, $limit),
                self::CATEGORY_TENANT_CREDIT_GL,
            );
            $categories[self::CATEGORY_TENANT_CREDIT_GL] = $this->wrapCategory(
                self::CATEGORY_TENANT_CREDIT_GL,
                'Tenant credit vs GL',
                $rows,
            );
        }

        if ($runPenaltiesGl) {
            $rows = $this->normalizeFinReconRows(
                $this->reconciliation->reconcilePenaltiesVsPenaltyGl($tenantId, $limit),
                self::CATEGORY_PENALTY_GL,
            );
            $categories[self::CATEGORY_PENALTY_GL] = $this->wrapCategory(
                self::CATEGORY_PENALTY_GL,
                'Penalty GL mismatch',
                $rows,
            );
        }

        if ($runOpsExtras) {
            $ops = $this->financeFirebreak->diagnosticsSnapshot($tenantId);
            $orphanRows = $this->normalizeOrphanAllocations($ops['orphan_allocations'] ?? collect());
            $categories[self::CATEGORY_ORPHAN_ALLOCATIONS] = $this->wrapCategory(
                self::CATEGORY_ORPHAN_ALLOCATIONS,
                'Orphan allocations',
                $orphanRows,
            );

            $staleRows = $this->normalizeStaleCarryForward(
                ($ops['stale_opening_arrears'] ?? collect())->merge($ops['duplicated_carry_forward'] ?? collect())
            );
            $categories[self::CATEGORY_STALE_CARRY_FORWARD] = $this->wrapCategory(
                self::CATEGORY_STALE_CARRY_FORWARD,
                'Stale carry-forward',
                $staleRows,
            );
        }

        return $categories;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function scopeIncludes(string $scope, array $allowed): bool
    {
        return in_array($scope, $allowed, true);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function wrapCategory(string $key, string $label, Collection $rows): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'repair_recommendation' => self::REPAIR_HINTS[$key] ?? 'Review finance integrity dashboard.',
            'rows' => $rows->values(),
            'summary' => $this->summarizeRows($rows),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{count:int,critical:int,warning:int,info:int,affected_tenants:int,affected_invoices:int}
     */
    private function summarizeRows(Collection $rows): array
    {
        $critical = 0;
        $warning = 0;
        $info = 0;
        $tenantIds = [];
        $invoiceIds = [];

        foreach ($rows as $row) {
            match ($row['severity'] ?? FinancialReconciliationService::SEVERITY_INFO) {
                FinancialReconciliationService::SEVERITY_CRITICAL => $critical++,
                FinancialReconciliationService::SEVERITY_WARNING => $warning++,
                default => $info++,
            };
            if (! empty($row['tenant_id'])) {
                $tenantIds[(int) $row['tenant_id']] = true;
            }
            if (! empty($row['invoice_id'])) {
                $invoiceIds[(int) $row['invoice_id']] = true;
            }
        }

        return [
            'count' => $rows->count(),
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'affected_tenants' => count($tenantIds),
            'affected_invoices' => count($invoiceIds),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $categories
     * @return array<string, mixed>
     */
    private function summarizeCategories(array $categories): array
    {
        $total = 0;
        $critical = 0;
        $warning = 0;
        $info = 0;
        $affectedTenants = [];
        $affectedInvoices = [];

        foreach ($categories as $category) {
            $catSummary = $category['summary'] ?? [];
            $total += (int) ($catSummary['count'] ?? 0);
            $critical += (int) ($catSummary['critical'] ?? 0);
            $warning += (int) ($catSummary['warning'] ?? 0);
            $info += (int) ($catSummary['info'] ?? 0);

            foreach ($category['rows'] ?? [] as $row) {
                if (! empty($row['tenant_id'])) {
                    $affectedTenants[(int) $row['tenant_id']] = true;
                }
                if (! empty($row['invoice_id'])) {
                    $affectedInvoices[(int) $row['invoice_id']] = true;
                }
            }
        }

        return [
            'total_issues' => $total,
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'affected_tenants' => count($affectedTenants),
            'affected_invoices' => count($affectedInvoices),
            'active_categories' => count(array_filter(
                $categories,
                fn (array $cat) => ((int) (($cat['summary'] ?? [])['count'] ?? 0)) > 0
            )),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $firebreakRows
     * @param  Collection<int, array<string, mixed>>  $reconRows
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeAllocationDrift(Collection $firebreakRows, Collection $reconRows): Collection
    {
        $merged = collect();

        foreach ($firebreakRows as $row) {
            $drift = abs((float) ($row['drift'] ?? 0));
            $merged->push([
                'issue_type' => self::CATEGORY_ALLOCATION_DRIFT,
                'entity_type' => 'pm_invoice',
                'entity_id' => (int) ($row['invoice_id'] ?? 0),
                'tenant_id' => (int) ($row['tenant_id'] ?? 0) ?: null,
                'invoice_id' => (int) ($row['invoice_id'] ?? 0) ?: null,
                'drift' => (float) ($row['drift'] ?? 0),
                'severity' => $this->reconciliation->severityForDrift($drift),
                'message' => sprintf(
                    'Invoice %s: amount_paid KES %s vs allocated KES %s (drift KES %s).',
                    $row['invoice_no'] ?? '#'.($row['invoice_id'] ?? '?'),
                    number_format((float) ($row['amount_paid'] ?? 0), 2),
                    number_format((float) ($row['allocated_sum'] ?? 0), 2),
                    number_format($drift, 2),
                ),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_ALLOCATION_DRIFT],
            ]);
        }

        foreach ($reconRows as $row) {
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            if ($merged->contains(fn (array $r) => (int) ($r['invoice_id'] ?? 0) === $invoiceId)) {
                continue;
            }
            $merged->push(array_merge($row, [
                'issue_type' => self::CATEGORY_ALLOCATION_DRIFT,
                'repair_recommendation' => $row['repair_recommendation'] ?? self::REPAIR_HINTS[self::CATEGORY_ALLOCATION_DRIFT],
            ]));
        }

        return $merged->sortByDesc(fn (array $r) => abs((float) ($r['drift'] ?? 0)))->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $finRows
     * @param  Collection<int, array<string, mixed>>  $acctRows
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeSuspenseRows(Collection $finRows, Collection $acctRows): Collection
    {
        $merged = $this->normalizeFinReconRows($finRows, self::CATEGORY_SUSPENSE_MISMATCH);

        foreach ($acctRows as $row) {
            $paymentId = (int) ($row['payment_id'] ?? $row['pm_payment_id'] ?? 0);
            $merged->push([
                'issue_type' => self::CATEGORY_SUSPENSE_MISMATCH,
                'entity_type' => 'pm_payment',
                'entity_id' => $paymentId,
                'tenant_id' => (int) ($row['tenant_id'] ?? $row['pm_tenant_id'] ?? 0) ?: null,
                'payment_id' => $paymentId ?: null,
                'drift' => 0.0,
                'severity' => FinancialReconciliationService::SEVERITY_WARNING,
                'message' => (string) ($row['message'] ?? 'Payment has both payment_received and suspense GL batches.'),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_SUSPENSE_MISMATCH],
            ]);
        }

        return $merged->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $finRows
     * @param  Collection<int, array<string, mixed>>  $ledgerGaps
     * @param  Collection<int, array<string, mixed>>  $negativePayable
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeLandlordRows(Collection $finRows, Collection $ledgerGaps, Collection $negativePayable): Collection
    {
        $merged = $this->normalizeFinReconRows($finRows, self::CATEGORY_LANDLORD_IMBALANCE);

        foreach ($ledgerGaps as $row) {
            $merged->push([
                'issue_type' => self::CATEGORY_LANDLORD_IMBALANCE,
                'entity_type' => 'pm_payment',
                'entity_id' => (int) ($row['payment_id'] ?? 0),
                'tenant_id' => (int) ($row['tenant_id'] ?? 0) ?: null,
                'payment_id' => (int) ($row['payment_id'] ?? 0) ?: null,
                'drift' => 0.0,
                'severity' => FinancialReconciliationService::SEVERITY_WARNING,
                'message' => (string) ($row['message'] ?? 'Payment GL posted without landlord ledger entry.'),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_LANDLORD_IMBALANCE],
            ]);
        }

        foreach ($negativePayable as $row) {
            $drift = abs((float) ($row['balance'] ?? $row['drift'] ?? 0));
            $merged->push([
                'issue_type' => self::CATEGORY_LANDLORD_IMBALANCE,
                'entity_type' => 'property',
                'entity_id' => (int) ($row['property_id'] ?? 0),
                'drift' => (float) ($row['balance'] ?? $row['drift'] ?? 0),
                'severity' => $this->reconciliation->severityForDrift($drift),
                'message' => (string) ($row['message'] ?? 'Negative landlord payable balance detected.'),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_LANDLORD_IMBALANCE],
            ]);
        }

        return $merged->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeFinReconRows(Collection $rows, string $issueType): Collection
    {
        return $rows->map(fn (array $row) => array_merge($row, [
            'issue_type' => $issueType,
            'repair_recommendation' => $row['repair_recommendation'] ?? (self::REPAIR_HINTS[$issueType] ?? ''),
        ]))->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeOrphanAllocations(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            $amount = (float) ($row['amount'] ?? 0);

            return [
                'issue_type' => self::CATEGORY_ORPHAN_ALLOCATIONS,
                'entity_type' => 'pm_payment_allocation',
                'entity_id' => (int) ($row['allocation_id'] ?? 0),
                'tenant_id' => null,
                'invoice_id' => (int) ($row['invoice_id'] ?? 0) ?: null,
                'payment_id' => (int) ($row['payment_id'] ?? 0) ?: null,
                'drift' => $amount,
                'severity' => $this->reconciliation->severityForDrift($amount),
                'message' => sprintf(
                    'Allocation #%d (KES %s) on invoice status %s / payment status %s.',
                    (int) ($row['allocation_id'] ?? 0),
                    number_format($amount, 2),
                    (string) ($row['invoice_status'] ?? 'missing'),
                    (string) ($row['payment_status'] ?? 'missing'),
                ),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_ORPHAN_ALLOCATIONS],
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeStaleCarryForward(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            $amount = (float) ($row['amount'] ?? $row['total_amount'] ?? 0);
            $leaseId = (int) ($row['lease_id'] ?? 0);
            $tenantId = (int) ($row['tenant_id'] ?? 0);

            return [
                'issue_type' => self::CATEGORY_STALE_CARRY_FORWARD,
                'entity_type' => $leaseId > 0 ? 'pm_lease' : 'pm_tenant',
                'entity_id' => $leaseId > 0 ? $leaseId : $tenantId,
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
                'drift' => $amount,
                'severity' => $amount > 1000
                    ? FinancialReconciliationService::SEVERITY_CRITICAL
                    : ($amount > 100 ? FinancialReconciliationService::SEVERITY_WARNING : FinancialReconciliationService::SEVERITY_INFO),
                'message' => (string) ($row['message'] ?? 'Stale or duplicated carry-forward balance detected.'),
                'repair_recommendation' => self::REPAIR_HINTS[self::CATEGORY_STALE_CARRY_FORWARD],
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function persistScan(array $report): int
    {
        $logged = 0;
        $scope = (string) ($report['scope'] ?? self::SCOPE_ALL);

        foreach ($report['categories'] ?? [] as $category) {
            foreach ($category['rows'] ?? [] as $row) {
                PmAccountingAuditLog::recordIfNew(
                    'finance_integrity_'.$category['key'],
                    (string) ($row['entity_type'] ?? 'property'),
                    (int) ($row['entity_id'] ?? 0) ?: null,
                    [
                        'pm_tenant_id' => (int) ($row['tenant_id'] ?? 0) ?: null,
                        'pm_invoice_id' => (int) ($row['invoice_id'] ?? 0) ?: null,
                        'pm_payment_id' => (int) ($row['payment_id'] ?? 0) ?: null,
                        'summary' => (string) ($row['message'] ?? 'Finance integrity drift'),
                        'payload' => array_merge(['scope' => $scope], $row),
                    ]
                );
                $logged++;
            }
        }

        PmAccountingAuditLog::record(
            PmAccountingAuditLog::ACTION_FINANCE_INTEGRITY_SCAN,
            'financial_integrity',
            null,
            [
                'summary' => 'Finance integrity scan completed ('.$scope.')',
                'payload' => $report['summary'] ?? [],
            ]
        );
        $logged++;

        return $logged;
    }
}
