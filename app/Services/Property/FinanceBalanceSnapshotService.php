<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FinanceBalanceSnapshotService
{
    public const OUTSTANDING_SUM_SQL = 'COALESCE(SUM(balance_due), 0)';

    public static function outstandingSumSql(string $tableAlias = ''): string
    {
        $prefix = $tableAlias !== '' ? $tableAlias.'.' : '';

        return 'COALESCE(SUM('.$prefix.'balance_due), 0)';
    }

    public function billableArQuery(?Builder $base = null): Builder
    {
        return ($base ?? PmInvoice::query())->billableAr();
    }

    public function withPositiveBalance(Builder $query): Builder
    {
        return $query->where('balance_due', '>', 0);
    }

    public function outstandingSum(Builder $query): float
    {
        return round((float) (clone $query)->selectRaw(self::OUTSTANDING_SUM_SQL.' as t')->value('t'), 2);
    }

    public function invoiceBalance(PmInvoice $invoice): float
    {
        return $invoice->balanceFloat();
    }

    public function tenantOutstanding(int $tenantId, ?string $invoiceType = null, bool $withoutAgentScope = false): float
    {
        $query = $this->billableArQuery()->where('pm_tenant_id', $tenantId);
        if ($withoutAgentScope) {
            $query = $query->withoutGlobalScope('agent_workspace');
        }
        if ($invoiceType !== null) {
            $query->where('invoice_type', $invoiceType);
        }

        return $this->outstandingSum($query);
    }

    public function tenantHasOutstanding(int $tenantId, bool $withoutAgentScope = false): bool
    {
        return $this->tenantOutstanding($tenantId, null, $withoutAgentScope) > 0;
    }

    public function globalOutstanding(bool $withoutAgentScope = false): float
    {
        $query = $this->billableArQuery();
        if ($withoutAgentScope) {
            $query = $query->withoutGlobalScope('agent_workspace');
        }

        return $this->outstandingSum($query);
    }

    /**
     * @param  list<int>|array<int, int>  $unitIds
     */
    public function unitOutstanding(array $unitIds, ?CarbonInterface $issueDateOnOrBefore = null): float
    {
        if ($unitIds === []) {
            return 0.0;
        }

        $query = $this->billableArQuery()->whereIn('property_unit_id', $unitIds);
        if ($issueDateOnOrBefore !== null) {
            $query->whereDate('issue_date', '<=', $issueDateOnOrBefore->toDateString());
        }

        return $this->outstandingSum($query);
    }

    /**
     * @param  list<int>|array<int, int>  $unitIds
     * @return array<int, float>
     */
    public function unitOutstandingMap(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return $this->billableArQuery()
            ->whereIn('property_unit_id', $unitIds)
            ->selectRaw('property_unit_id, '.self::OUTSTANDING_SUM_SQL.' as outstanding')
            ->groupBy('property_unit_id')
            ->pluck('outstanding', 'property_unit_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    /**
     * @param  list<int>|array<int, int>  $propertyIds
     */
    public function outstandingByPropertyId(?CarbonInterface $issueDateOnOrBefore = null, array $propertyIds = []): Collection
    {
        $query = DB::table('pm_invoices as i')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->tap(fn ($q) => PmInvoice::applyBillableArConstraints($q, 'i'));

        if ($issueDateOnOrBefore !== null) {
            $query->whereDate('i.issue_date', '<=', $issueDateOnOrBefore->toDateString());
        }
        if ($propertyIds !== []) {
            $query->whereIn('pu.property_id', $propertyIds);
        }

        return $query
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, '.self::outstandingSumSql('i').' as total')
            ->pluck('total', 'property_id');
    }

    /**
     * @param  list<int>|array<int, int>  $leaseIds
     * @return array<int, float>
     */
    public function leaseArrearsMap(array $leaseIds, ?CarbonInterface $dueBefore = null): array
    {
        if ($leaseIds === []) {
            return [];
        }

        $query = $this->billableArQuery()->whereIn('pm_lease_id', $leaseIds);
        if ($dueBefore !== null) {
            $query->whereDate('due_date', '<', $dueBefore->toDateString());
        }

        return $query
            ->selectRaw('pm_lease_id, '.self::OUTSTANDING_SUM_SQL.' as arrears_total')
            ->groupBy('pm_lease_id')
            ->pluck('arrears_total', 'pm_lease_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    public function leaseOutstanding(int $leaseId): float
    {
        return $this->outstandingSum(
            $this->billableArQuery()->where('pm_lease_id', $leaseId)
        );
    }

    public function leaseOverdueOutstanding(int $leaseId): float
    {
        return $this->outstandingSum(
            $this->billableArQuery()
                ->where('pm_lease_id', $leaseId)
                ->whereDate('due_date', '<', now()->toDateString())
        );
    }

    /**
     * @param  list<int>|array<int, int>  $leaseIds
     * @return array<int, float>
     */
    public function leaseOutstandingMap(array $leaseIds): array
    {
        if ($leaseIds === []) {
            return [];
        }

        return $this->billableArQuery()
            ->whereIn('pm_lease_id', $leaseIds)
            ->selectRaw('pm_lease_id, '.self::OUTSTANDING_SUM_SQL.' as outstanding')
            ->groupBy('pm_lease_id')
            ->pluck('outstanding', 'pm_lease_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    public function overdueOutstanding(?Builder $base = null): float
    {
        $query = $base ?? $this->billableArQuery();

        return $this->outstandingSum(
            (clone $query)->where('is_past_due', true)
        );
    }

    public function partialOverdueOutstanding(?Builder $base = null): float
    {
        $query = $base ?? $this->billableArQuery();

        return $this->outstandingSum(
            (clone $query)
                ->where('status', PmInvoice::STATUS_PARTIAL)
                ->where('is_past_due', true)
        );
    }

    public function partialOverdueCount(?Builder $base = null): int
    {
        $query = $base ?? $this->billableArQuery();

        return (int) (clone $query)
            ->where('status', PmInvoice::STATUS_PARTIAL)
            ->where('is_past_due', true)
            ->where('balance_due', '>', 0)
            ->count();
    }

    /**
     * Outstanding balance for invoices at least $minDays overdue.
     * When $maxDays is set, only includes invoices less than $maxDays overdue.
     */
    public function agingBucket(int $minDays, ?int $maxDays = null, ?Builder $base = null): float
    {
        $query = $base ?? $this->billableArQuery();
        $query = (clone $query)->whereDate('due_date', '<=', now()->subDays($minDays)->toDateString());
        if ($maxDays !== null) {
            $query->whereDate('due_date', '>', now()->subDays($maxDays)->toDateString());
        }

        return $this->outstandingSum($query);
    }

    public function daysOverdue(?CarbonInterface $dueDate, ?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        if (! $dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate)->startOfDay();
        if ($due->gte($today)) {
            return 0;
        }

        return (int) $today->diffInDays($due, true);
    }

    /**
     * @param  array{not_due: float, 0_30: float, 31_60: float, 61_90: float, over_90: float}  $buckets
     */
    public function addBalanceToAgingBuckets(?CarbonInterface $dueDate, float $balance, array &$buckets, ?Carbon $today = null): void
    {
        if ($balance <= 0) {
            return;
        }

        $today = ($today ?? now())->copy()->startOfDay();
        if (! $dueDate || Carbon::parse($dueDate)->startOfDay()->gte($today)) {
            $buckets['not_due'] += $balance;

            return;
        }

        $days = $this->daysOverdue($dueDate, $today);
        if ($days < 31) {
            $buckets['0_30'] += $balance;
        } elseif ($days < 61) {
            $buckets['31_60'] += $balance;
        } elseif ($days < 91) {
            $buckets['61_90'] += $balance;
        } else {
            $buckets['over_90'] += $balance;
        }
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
    public function snapshotForTenant(PmTenant $tenant): array
    {
        $query = $this->billableArQuery()
            ->withoutGlobalScope('agent_workspace')
            ->where('pm_tenant_id', $tenant->id);

        return $this->snapshotFromQuery($query);
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
    public function snapshotGlobal(bool $withoutAgentScope = false): array
    {
        $query = $this->billableArQuery();
        if ($withoutAgentScope) {
            $query = $query->withoutGlobalScope('agent_workspace');
        }

        return $this->snapshotFromQuery($query);
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
    private function snapshotFromQuery(Builder $query): array
    {
        $outstanding = $this->outstandingSum($query);
        $overdue = $this->overdueOutstanding($query);
        $partialOverdue = $this->partialOverdueOutstanding($query);

        return [
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'partial_overdue' => $partialOverdue,
            'not_due' => max(0.0, round($outstanding - $overdue, 2)),
            'aging' => [
                '0_30' => $this->agingBucket(0, 30, $query),
                '31_60' => $this->agingBucket(31, 60, $query),
                '61_90' => $this->agingBucket(61, 90, $query),
                'over_90' => $this->agingBucket(91, null, $query),
            ],
        ];
    }
}
