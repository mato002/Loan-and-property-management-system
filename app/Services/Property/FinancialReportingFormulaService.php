<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical financial reporting formulas (billable AR, collections, billed, landlord payable, tenant total due).
 */
final class FinancialReportingFormulaService
{
    public function __construct(
        private FinanceBalanceSnapshotService $balances,
        private TenantCreditService $tenantCredits,
        private CarryForwardConsolidationService $carryForward,
    ) {}

    public function outstandingGlobal(bool $withoutAgentScope = false): float
    {
        return $this->balances->globalOutstanding($withoutAgentScope);
    }

    public function outstandingForTenant(int $tenantId, ?string $invoiceType = null, bool $withoutAgentScope = false): float
    {
        return $this->balances->tenantOutstanding($tenantId, $invoiceType, $withoutAgentScope);
    }

    public function overdueForTenant(int $tenantId, bool $withoutAgentScope = false): float
    {
        $query = $this->balances->billableArQuery()
            ->where('pm_tenant_id', $tenantId)
            ->where('is_past_due', true);

        if ($withoutAgentScope) {
            $query = $query->withoutGlobalScope('agent_workspace');
        }

        return $this->balances->outstandingSum($query);
    }

    public function agingBucket(int $minDays, ?int $maxDays = null): float
    {
        return $this->balances->agingBucket($minDays, $maxDays);
    }

    /**
     * @return array{
     *     outstanding: float,
     *     overdue: float,
     *     partial_overdue: float,
     *     not_due: float,
     *     aging: array{0_30: float, 31_60: float, 61_90: float, over_90: float}
     * }
     */
    public function tenantArSnapshot(PmTenant $tenant): array
    {
        return $this->balances->snapshotForTenant($tenant);
    }

    public function collectionsForPeriod(
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $tenantId = null,
        ?array $propertyIds = null,
        ?array $unitIds = null,
    ): float {
        $query = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end]);

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pay.pm_tenant_id', $tenantId);
        }

        if (($propertyIds !== null && $propertyIds !== []) || ($unitIds !== null && $unitIds !== [])) {
            $query->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id');
            if ($unitIds !== null && $unitIds !== []) {
                $query->whereIn('i.property_unit_id', $unitIds);
            }
            if ($propertyIds !== null && $propertyIds !== []) {
                $query->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                    ->whereIn('pu.property_id', $propertyIds);
            }
        }

        return round((float) $query->sum('a.amount'), 2);
    }

    public function collectionsMtd(): float
    {
        return $this->collectionsForPeriod(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        );
    }

    /**
     * Sum completed allocation amounts on loaded payment models (ignores payment header amount).
     *
     * @param  iterable<int, PmPayment>  $payments
     */
    public function collectionsFromPayments(iterable $payments): float
    {
        $total = 0.0;
        foreach ($payments as $payment) {
            if ((string) $payment->status !== PmPayment::STATUS_COMPLETED) {
                continue;
            }
            if ($payment->relationLoaded('allocations')) {
                $total += (float) $payment->allocations->sum('amount');

                continue;
            }
            $total += (float) $payment->allocations()->sum('amount');
        }

        return round($total, 2);
    }

    /**
     * @param  list<int>|array<int, int>  $unitIds
     */
    public function billedForPeriod(
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $tenantId = null,
        array $unitIds = [],
    ): float {
        $query = $this->billableIssuedQuery($start, $end);
        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }
        if ($unitIds !== []) {
            $query->whereIn('property_unit_id', $unitIds);
        }

        return round((float) $query->sum('amount'), 2);
    }

    public function billedMtd(): float
    {
        return $this->billedForPeriod(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        );
    }

    public function landlordPayableGlobal(): float
    {
        return max(0.0, round((float) PmLandlordLedgerEntry::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END),0) as bal")
            ->value('bal'), 2));
    }

    public function landlordPayableForUser(int $landlordUserId): float
    {
        if ($landlordUserId <= 0) {
            return 0.0;
        }

        return max(0.0, round((float) PmLandlordLedgerEntry::query()
            ->where('user_id', $landlordUserId)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END),0) as bal")
            ->value('bal'), 2));
    }

    /**
     * @return array{
     *     invoice_ar: float,
     *     uninvoiced_cf: float,
     *     tenant_credit: float,
     *     total_due: float
     * }
     */
    public function tenantTotalDueBreakdown(PmTenant $tenant): array
    {
        $invoiceAr = $this->outstandingForTenant((int) $tenant->id, null, true);
        $uninvoicedCf = $this->uninvoicedCarryForwardForTenant($tenant);
        $credit = $this->tenantCredits->balanceForTenant((int) $tenant->id);
        $totalDue = max(0.0, round($invoiceAr + $uninvoicedCf - $credit, 2));

        return [
            'invoice_ar' => $invoiceAr,
            'uninvoiced_cf' => $uninvoicedCf,
            'tenant_credit' => $credit,
            'total_due' => $totalDue,
        ];
    }

    public function tenantTotalDue(PmTenant $tenant): float
    {
        return $this->tenantTotalDueBreakdown($tenant)['total_due'];
    }

    /** Canonical closing balance for tenant statements (billable invoice AR). */
    public function tenantStatementClosingBalance(int $tenantId): float
    {
        return $this->outstandingForTenant($tenantId, null, true);
    }

    public function outstandingByPropertyId(?CarbonInterface $issueDateOnOrBefore = null, array $propertyIds = []): Collection
    {
        return $this->balances->outstandingByPropertyId($issueDateOnOrBefore, $propertyIds);
    }

    /**
     * @param  list<int>|array<int, int>  $unitIds
     */
    public function unitOutstanding(array $unitIds, ?CarbonInterface $issueDateOnOrBefore = null): float
    {
        return $this->balances->unitOutstanding($unitIds, $issueDateOnOrBefore);
    }

    /**
     * @param  list<int>|array<int, int>  $unitIds
     * @return array<int, float>
     */
    public function unitOutstandingMap(array $unitIds): array
    {
        return $this->balances->unitOutstandingMap($unitIds);
    }

    /**
     * @param  list<int>|array<int, int>  $leaseIds
     * @return array<int, float>
     */
    public function leaseOutstandingMap(array $leaseIds): array
    {
        return $this->balances->leaseOutstandingMap($leaseIds);
    }

    /**
     * @param  list<int>|array<int, int>  $leaseIds
     * @return array<int, float>
     */
    public function leaseArrearsMap(array $leaseIds, ?CarbonInterface $dueBefore = null): array
    {
        return $this->balances->leaseArrearsMap($leaseIds, $dueBefore);
    }

    public function tenantCollectionsTotal(int $tenantId): float
    {
        if ($tenantId <= 0) {
            return 0.0;
        }

        return round((float) DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->where('pay.pm_tenant_id', $tenantId)
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->sum('a.amount'), 2);
    }

    /**
     * @return array{
     *     invoice_totals: array{count:int,total:float,paid:float,due:float,open_count:int},
     *     lease_carry_forward: array{total:float,lines:array<int,array<string,mixed>>,invoiced:bool,uninvoiced_due:float},
     *     total_due: array{invoice_ar:float,uninvoiced_cf:float,tenant_credit:float,total_due:float}
     * }
     */
    public function tenantBillingSnapshot(PmTenant $tenant): array
    {
        $tenant->loadMissing(['leases']);

        $invoiceAgg = $this->balances->billableArQuery()
            ->withoutGlobalScope('agent_workspace')
            ->where('pm_tenant_id', $tenant->id)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_billed')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_paid')
            ->selectRaw(FinanceBalanceSnapshotService::OUTSTANDING_SUM_SQL.' as outstanding')
            ->selectRaw('SUM(CASE WHEN balance_due > 0 THEN 1 ELSE 0 END) as open_count')
            ->first();

        $invoiceOutstanding = round((float) ($invoiceAgg->outstanding ?? 0), 2);

        $leaseLines = [];
        $leaseCarryForwardTotal = 0.0;
        $uninvoicedLeaseCarryForward = 0.0;
        foreach ($tenant->leases ?? [] as $lease) {
            foreach (collect((array) ($lease->opening_arrears ?? [])) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $amount = (float) ($row['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $leaseCarryForwardTotal += $amount;
                $leaseLines[] = [
                    'lease_id' => (int) $lease->id,
                    'charge_type' => (string) ($row['charge_type'] ?? 'other'),
                    'specific_charge' => (string) ($row['specific_charge'] ?? ''),
                    'period' => (string) ($row['period'] ?? ''),
                    'amount' => $amount,
                ];
            }
            $uninvoicedLeaseCarryForward += $this->carryForward->leaseJsonUninvoicedInDue($lease);
        }

        $totalDue = $this->tenantTotalDueBreakdown($tenant);

        return [
            'invoice_totals' => [
                'count' => (int) ($invoiceAgg->invoice_count ?? 0),
                'total' => round((float) ($invoiceAgg->total_billed ?? 0), 2),
                'paid' => round((float) ($invoiceAgg->total_paid ?? 0), 2),
                'due' => $invoiceOutstanding,
                'open_count' => (int) ($invoiceAgg->open_count ?? 0),
            ],
            'lease_carry_forward' => [
                'total' => round($leaseCarryForwardTotal, 2),
                'lines' => $leaseLines,
                'invoiced' => $this->carryForward->tenantHasInvoicedCarryForward((int) $tenant->id),
                'uninvoiced_due' => round($uninvoicedLeaseCarryForward, 2),
            ],
            'total_due' => $totalDue,
        ];
    }

    /**
     * @return array{target: float, actual: float|null, gap_kes: float}
     */
    public function collectionRateMtd(float $targetPercent = 95.0): array
    {
        $collected = $this->collectionsMtd();
        $billed = $this->billedMtd();
        $actual = $billed > 0 ? round(min(100.0, 100.0 * $collected / $billed), 1) : null;
        $gapKes = max(0.0, $billed - $collected);

        return [
            'target' => $targetPercent,
            'actual' => $actual,
            'gap_kes' => $gapKes,
        ];
    }

    private function uninvoicedCarryForwardForTenant(PmTenant $tenant): float
    {
        $tenant->loadMissing(['leases']);
        $total = 0.0;
        foreach ($tenant->leases ?? [] as $lease) {
            $total += $this->carryForward->leaseJsonUninvoicedInDue($lease);
        }

        return round($total, 2);
    }

    private function billableIssuedQuery(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return PmInvoice::query()
            ->billableAr()
            ->whereBetween('issue_date', [
                $start->copy()->startOfDay()->toDateString(),
                $end->copy()->endOfDay()->toDateString(),
            ]);
    }
}
