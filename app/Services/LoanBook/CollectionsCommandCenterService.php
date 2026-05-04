<?php

namespace App\Services\LoanBook;

use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\AccountingChartAccount;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookCollectionRate;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates loan-book collections, targets, and (optional) chart cash for the collections command center.
 */
class CollectionsCommandCenterService
{
    use ScopesLoanPortfolioAccess;

    /**
     * @return array{
     *     metrics: array<string, float|int|string|null>,
     *     forecastWindows: Collection<int, array<string, float|string>>,
     *     collectionMix: Collection<int, array<string, float|string>>,
     *     collectionMixTotal: float,
     *     dailyCollectionRates: Collection<int, array<string, float|string|array>>,
     *     agentPerformanceSummary: array<string, float|int|string>,
     *     alerts: array<int, array<string, string>>,
     *     liquidityFloorStatus: string,
     *     projectedLiquidityBreachDate: ?string,
     *     dateWindowLabel: string,
     *     efficiencySparkline: array<int, float>,
     *     has_collection_target: bool,
     *     lending_capacity_route: string
     * }
     */
    public function buildForUser(?User $user, int $loanBranchId, Carbon $now): array
    {
        $loanIdsQ = $this->scopedLoanIdsQuery($user, $loanBranchId);
        $branchKeys = $this->branchKeysForRates($loanIdsQ);

        $today = $now->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $dailyTargetToday = $this->dailyProratedTarget($branchKeys, $today);
        $collectedToday = $this->sumCollectionEntriesOn($loanIdsQ, $today);
        $collectedYesterday = $this->sumCollectionEntriesOn($loanIdsQ, $yesterday);

        $dailyTargetYesterday = $this->dailyProratedTarget($branchKeys, $yesterday);

        $totalExpected = $dailyTargetToday;
        $totalCollected = $collectedToday;

        $effToday = $totalExpected > 0 ? ($totalCollected / $totalExpected) * 100 : null;
        $effYesterday = $dailyTargetYesterday > 0 ? ($collectedYesterday / $dailyTargetYesterday) * 100 : null;
        $efficiencyChangePp = ($effToday !== null && $effYesterday !== null)
            ? $effToday - $effYesterday
            : null;

        $currentYield = $collectedYesterday;
        $currentYieldPrior = $this->sumCollectionEntriesOn($loanIdsQ, $yesterday->copy()->subDay());
        $dailyTargetDayBeforeYesterday = $this->dailyProratedTarget($branchKeys, $yesterday->copy()->subDay());
        $effPriorDay = $dailyTargetDayBeforeYesterday > 0
            ? ($currentYieldPrior / $dailyTargetDayBeforeYesterday) * 100
            : null;
        $currentYieldChangePp = ($effYesterday !== null && $effPriorDay !== null)
            ? $effYesterday - $effPriorDay
            : null;

        $arrearsRecovery = $this->sumCollectionEntriesOnForDpdRange($loanIdsQ, $today, 1, null);
        $prepaymentYield = $this->sumPrepaymentsOn($loanIdsQ, $today);

        $yieldGap = max(0.0, $totalExpected - $totalCollected);

        $expected7 = $dailyTargetToday * 7;
        $expected14 = $dailyTargetToday * 14;
        $expected30 = $dailyTargetToday * 30;

        $trailing = $this->trailingCollectionEfficiency($loanIdsQ, $branchKeys, $today, 7);
        $forecastWindows = $this->buildForecastWindows($expected7, $expected14, $expected30, $trailing);

        [$mixRows, $mixTotal] = $this->collectionMixByDpd($loanIdsQ, $today);

        $dailyCollectionRates = $this->buildDailyRows($loanIdsQ, $branchKeys, $today, 6);
        $efficiencySparkline = $this->sparklineFromDailyRows($dailyCollectionRates);

        $agentSummary = $this->agentPerformanceSummary($loanIdsQ, $today);

        [$cashTotal, $floorTotal, $liquidityStatus, $breachDate] = $this->liquiditySnapshot();

        $metrics = [
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'collection_efficiency' => $effToday !== null ? round($effToday, 4) : null,
            'efficiency_change_pp' => $efficiencyChangePp !== null ? round($efficiencyChangePp, 2) : null,
            'current_yield' => $currentYield,
            'current_yield_change_pp' => $currentYieldChangePp !== null ? round($currentYieldChangePp, 2) : null,
            'arrears_recovery_yield' => $arrearsRecovery,
            'prepayment_yield' => $prepaymentYield,
            'expected_inflow_7_days' => $expected7,
            'expected_inflow_14_days' => $expected14,
            'expected_inflow_30_days' => $expected30,
            'available_liquidity_today' => $cashTotal,
            'liquidity_floor_amount' => $floorTotal,
            'projected_liquidity_breach_days' => $liquidityStatus === 'AT RISK' ? 0 : null,
            'yield_gap' => $yieldGap,
            'trailing_collection_rate' => round($trailing * 100, 2),
        ];

        $alerts = $this->buildAlerts(
            $yieldGap,
            $totalExpected,
            $arrearsRecovery,
            $agentSummary,
            $liquidityStatus,
            (int) ($agentSummary['pending_collections'] ?? 0)
        );

        $lendingCapacityRoute = route('loan.book.disbursements.index');

        return [
            'metrics' => $metrics,
            'forecastWindows' => $forecastWindows,
            'collectionMix' => $mixRows,
            'collectionMixTotal' => $mixTotal,
            'dailyCollectionRates' => $dailyCollectionRates,
            'agentPerformanceSummary' => $agentSummary,
            'alerts' => $alerts,
            'liquidityFloorStatus' => $liquidityStatus,
            'projectedLiquidityBreachDate' => $breachDate,
            'dateWindowLabel' => $today->format('d M Y').' - '.$today->copy()->addDays(6)->format('d M Y'),
            'efficiencySparkline' => $efficiencySparkline,
            'has_collection_target' => $totalExpected > 0,
            'lending_capacity_route' => $lendingCapacityRoute,
        ];
    }

    private function scopedLoanIdsQuery(?User $user, int $loanBranchId): Builder
    {
        $q = LoanBookLoan::query()->select('loan_book_loans.id');
        $this->scopeByAssignedLoanClient($q, $user, 'loanClient');
        if ($loanBranchId > 0) {
            $q->where('loan_book_loans.loan_branch_id', $loanBranchId);
        }

        return $q;
    }

    /**
     * @return list<string>
     */
    private function branchKeysForRates(Builder $loanIdsQ): array
    {
        if (! Schema::hasTable('loan_book_loans')) {
            return [];
        }

        return DB::table('loan_book_loans as l')
            ->leftJoin('loan_branches as b', 'b.id', '=', 'l.loan_branch_id')
            ->whereIn('l.id', $loanIdsQ)
            ->selectRaw('DISTINCT TRIM(COALESCE(b.name, l.branch, \'\')) as br')
            ->pluck('br')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();
    }

    private function monthlyCollectionTargetForMonth(array $branchKeys, Carbon $month): float
    {
        if ($branchKeys === [] || ! Schema::hasTable('loan_book_collection_rates')) {
            return 0.0;
        }

        return (float) LoanBookCollectionRate::query()
            ->where('year', (int) $month->year)
            ->where('month', (int) $month->month)
            ->whereIn('branch', $branchKeys)
            ->sum('target_amount');
    }

    private function dailyProratedTarget(array $branchKeys, Carbon $day): float
    {
        $monthStart = $day->copy()->startOfMonth();
        $monthly = $this->monthlyCollectionTargetForMonth($branchKeys, $monthStart);
        $dim = max(1, (int) $monthStart->format('t'));

        return $monthly / $dim;
    }

    private function sumCollectionEntriesOn(Builder $loanIdsQ, Carbon $day): float
    {
        if (! Schema::hasTable('loan_book_collection_entries')) {
            return 0.0;
        }

        return (float) LoanBookCollectionEntry::query()
            ->whereIn('loan_book_loan_id', $loanIdsQ)
            ->whereDate('collected_on', $day->toDateString())
            ->sum('amount');
    }

    private function sumCollectionEntriesOnForDpdRange(Builder $loanIdsQ, Carbon $day, int $minDpd, ?int $maxDpd): float
    {
        if (! Schema::hasTable('loan_book_collection_entries')) {
            return 0.0;
        }

        $q = LoanBookCollectionEntry::query()
            ->join('loan_book_loans as l', 'l.id', '=', 'loan_book_collection_entries.loan_book_loan_id')
            ->whereIn('loan_book_collection_entries.loan_book_loan_id', $loanIdsQ)
            ->whereDate('loan_book_collection_entries.collected_on', $day->toDateString())
            ->where('l.dpd', '>=', $minDpd);
        if ($maxDpd !== null) {
            $q->where('l.dpd', '<=', $maxDpd);
        }

        return (float) $q->sum('loan_book_collection_entries.amount');
    }

    private function sumPrepaymentsOn(Builder $loanIdsQ, Carbon $day): float
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return 0.0;
        }

        return (float) LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->where('payment_kind', LoanBookPayment::KIND_PREPAYMENT)
            ->whereIn('loan_book_loan_id', $loanIdsQ)
            ->whereDate('transaction_at', $day->toDateString())
            ->sum('amount');
    }

    /**
     * @return array{0: Collection<int, array<string, float|string>>, 1: float}
     */
    private function collectionMixByDpd(Builder $loanIdsQ, Carbon $today): array
    {
        $buckets = [
            'current' => 0.0,
            'due_yesterday' => 0.0,
            'arrears_1_7' => 0.0,
            'deep' => 0.0,
        ];

        if (Schema::hasTable('loan_book_collection_entries')) {
            $rows = DB::table('loan_book_collection_entries as ce')
                ->join('loan_book_loans as l', 'l.id', '=', 'ce.loan_book_loan_id')
                ->whereIn('ce.loan_book_loan_id', $loanIdsQ)
                ->whereDate('ce.collected_on', $today->toDateString())
                ->groupBy('l.dpd')
                ->selectRaw('l.dpd as dpd, SUM(ce.amount) as total')
                ->get();

            foreach ($rows as $row) {
                $dpd = (int) $row->dpd;
                $amt = (float) $row->total;
                if ($dpd <= 0) {
                    $buckets['current'] += $amt;
                } elseif ($dpd === 1) {
                    $buckets['due_yesterday'] += $amt;
                } elseif ($dpd >= 2 && $dpd <= 7) {
                    $buckets['arrears_1_7'] += $amt;
                } else {
                    $buckets['deep'] += $amt;
                }
            }
        }

        $segments = collect([
            ['label' => 'Current / Due Today', 'amount' => $buckets['current'], 'color' => '#0f766e'],
            ['label' => 'Due Yesterday (dpd 1)', 'amount' => $buckets['due_yesterday'], 'color' => '#2563eb'],
            ['label' => 'Arrears 2–7 Days', 'amount' => $buckets['arrears_1_7'], 'color' => '#f59e0b'],
            ['label' => 'Deep Arrears 8+ Days', 'amount' => $buckets['deep'], 'color' => '#ef4444'],
        ]);

        $total = (float) $segments->sum('amount');
        $withPct = $segments->map(function (array $segment) use ($total) {
            $segment['percentage'] = $total > 0 ? (((float) $segment['amount']) / $total) * 100 : 0.0;

            return $segment;
        })->values();

        return [$withPct, $total];
    }

    private function trailingCollectionEfficiency(Builder $loanIdsQ, array $branchKeys, Carbon $today, int $days): float
    {
        $end = $today->copy()->subDay();
        $start = $end->copy()->subDays(max(0, $days - 1));
        $sumCollected = 0.0;
        $sumExpected = 0.0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $sumCollected += $this->sumCollectionEntriesOn($loanIdsQ, $d);
            $sumExpected += $this->dailyProratedTarget($branchKeys, $d);
        }
        if ($sumExpected <= 0.0) {
            return 0.85;
        }

        return min(1.0, max(0.0, $sumCollected / $sumExpected));
    }

    /**
     * @return Collection<int, array<string, float|string>>
     */
    private function buildForecastWindows(float $e7, float $e14, float $e30, float $trailingRate): Collection
    {
        $windows = [
            ['window' => '7 Days', 'expected_inflow' => $e7, 'expected_collected' => $e7 * $trailingRate],
            ['window' => '14 Days', 'expected_inflow' => $e14, 'expected_collected' => $e14 * $trailingRate],
            ['window' => '30 Days', 'expected_inflow' => $e30, 'expected_collected' => $e30 * $trailingRate],
        ];

        return collect($windows)->map(function (array $row) {
            $expectedInflow = (float) $row['expected_inflow'];
            $expectedCollected = (float) $row['expected_collected'];
            $gap = max(0.0, $expectedInflow - $expectedCollected);
            $rate = $expectedInflow > 0 ? ($expectedCollected / $expectedInflow) * 100 : 0.0;
            $row['gap'] = $gap;
            $row['collection_rate'] = $rate;

            return $row;
        })->values();
    }

    /**
     * @return Collection<int, array<string, float|string|array<int, float>>>
     */
    private function buildDailyRows(Builder $loanIdsQ, array $branchKeys, Carbon $today, int $lookbackDays): Collection
    {
        $rows = collect();
        for ($i = 0; $i <= $lookbackDays; $i++) {
            $d = $today->copy()->subDays($i);
            $collected = $this->sumCollectionEntriesOn($loanIdsQ, $d);
            $expected = $this->dailyProratedTarget($branchKeys, $d);
            $rate = $expected > 0 ? ($collected / $expected) * 100 : ($collected > 0 ? 100.0 : 0.0);
            $gap = max(0.0, $expected - $collected);

            $label = match ($i) {
                0 => 'Today',
                1 => 'Yesterday',
                default => $d->format('D, d M'),
            };

            $trend = [];
            for ($j = 6; $j >= 0; $j--) {
                $dd = $d->copy()->subDays($j);
                $c = $this->sumCollectionEntriesOn($loanIdsQ, $dd);
                $e = $this->dailyProratedTarget($branchKeys, $dd);
                $trend[] = $e > 0
                    ? max(0.0, min(100.0, ($c / $e) * 100))
                    : ($c > 0 ? 100.0 : 0.0);
            }

            $rows->push([
                'date' => $label,
                'expected' => $expected,
                'collected' => $collected,
                'trend' => $trend,
                'collection_rate' => $rate,
                'yield_gap' => $gap,
            ]);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $dailyRows
     * @return array<int, float>
     */
    private function sparklineFromDailyRows(Collection $dailyRows): array
    {
        $todayRow = $dailyRows->first();

        return collect($todayRow['trend'] ?? [])
            ->map(fn ($v) => (float) $v)
            ->take(7)
            ->values()
            ->all();
    }

    /**
     * @return array{top_agent: string, top_agent_collected: float, pending_collections: int}
     */
    private function agentPerformanceSummary(Builder $loanIdsQ, Carbon $today): array
    {
        $pending = 0;
        if (Schema::hasTable('loan_book_payments')) {
            $pending = (int) LoanBookPayment::query()
                ->where('status', LoanBookPayment::STATUS_UNPOSTED)
                ->whereNotNull('loan_book_loan_id')
                ->whereIn('loan_book_loan_id', $loanIdsQ)
                ->count();
        }

        $topName = '—';
        $topAmt = 0.0;
        if (Schema::hasTable('loan_book_collection_entries') && Schema::hasTable('employees')) {
            $row = DB::table('loan_book_collection_entries as ce')
                ->join('employees as e', 'e.id', '=', 'ce.collected_by_employee_id')
                ->whereIn('ce.loan_book_loan_id', $loanIdsQ)
                ->whereDate('ce.collected_on', $today->toDateString())
                ->whereNotNull('ce.collected_by_employee_id')
                ->groupBy('ce.collected_by_employee_id', 'e.first_name', 'e.last_name')
                ->selectRaw('TRIM(CONCAT(COALESCE(e.first_name, \'\'), \' \', COALESCE(e.last_name, \'\'))) as agent_name, SUM(ce.amount) as total')
                ->orderByDesc('total')
                ->first();
            if ($row) {
                $topName = trim((string) $row->agent_name) ?: '—';
                $topAmt = (float) $row->total;
            }
        }

        return [
            'top_agent' => $topName,
            'top_agent_collected' => $topAmt,
            'pending_collections' => $pending,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: string, 3: ?string}
     */
    private function liquiditySnapshot(): array
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return [0.0, 0.0, 'HEALTHY', null];
        }

        $hasCash = Schema::hasColumn('accounting_chart_accounts', 'is_cash_account')
            && Schema::hasColumn('accounting_chart_accounts', 'current_balance');

        if (! $hasCash) {
            return [0.0, 0.0, 'HEALTHY', null];
        }

        $cashQ = AccountingChartAccount::query()->where('is_cash_account', true);
        if (Schema::hasColumn('accounting_chart_accounts', 'is_active')) {
            $cashQ->where('is_active', true);
        }
        $cashTotal = (float) (clone $cashQ)->sum('current_balance');

        $floorTotal = 0.0;
        if (Schema::hasColumn('accounting_chart_accounts', 'min_balance_floor')
            && Schema::hasColumn('accounting_chart_accounts', 'floor_enabled')) {
            $floorQ = AccountingChartAccount::query()
                ->where('is_cash_account', true)
                ->where('floor_enabled', true);
            if (Schema::hasColumn('accounting_chart_accounts', 'is_active')) {
                $floorQ->where('is_active', true);
            }
            $floorTotal = (float) $floorQ->sum('min_balance_floor');
        }

        $status = ($floorTotal > 0.01 && $cashTotal < $floorTotal) ? 'AT RISK' : 'HEALTHY';
        $breach = $status === 'AT RISK' ? Carbon::now()->toDateString() : null;

        return [$cashTotal, $floorTotal, $status, $breach];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildAlerts(
        float $yieldGap,
        float $totalExpected,
        float $arrearsRecovery,
        array $agentSummary,
        string $liquidityStatus,
        int $pendingUnposted
    ): array {
        $alerts = [];
        $fmt = fn (float $n) => 'KES '.number_format($n, 0);

        if ($liquidityStatus === 'AT RISK') {
            $alerts[] = [
                'severity' => 'critical',
                'title' => 'Cash below configured floor',
                'description' => 'Sum of cash chart balances is below the sum of enabled minimum floors on cash accounts.',
                'time_ago' => 'Live',
            ];
        }

        if ($totalExpected > 0 && $yieldGap > $totalExpected * 0.1) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Yield gap versus prorated target',
                'description' => 'Today\'s gap is '.$fmt($yieldGap).' versus prorated monthly collection target.',
                'time_ago' => 'Live',
            ];
        }

        if ($arrearsRecovery > 0) {
            $alerts[] = [
                'severity' => 'positive',
                'title' => 'Arrears collections today',
                'description' => 'Recovered '.$fmt($arrearsRecovery).' from loans with days past due (book snapshot at collection time).',
                'time_ago' => 'Live',
            ];
        }

        if ($pendingUnposted > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Unposted pay-ins',
                'description' => (string) $pendingUnposted.' payment line(s) are still unposted in your portfolio scope.',
                'time_ago' => 'Live',
            ];
        }

        if (($agentSummary['top_agent_collected'] ?? 0) > 0 && ($agentSummary['top_agent'] ?? '') !== '—') {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'Top collector today',
                'description' => $agentSummary['top_agent'].' leads with '.$fmt((float) $agentSummary['top_agent_collected']).' on the collection sheet.',
                'time_ago' => 'Live',
            ];
        }

        if ($totalExpected <= 0) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'No monthly collection target',
                'description' => 'Configure branch targets under Collection rates for the current month to populate daily expectations.',
                'time_ago' => 'Live',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'All clear',
                'description' => 'No automated alerts for the current snapshot.',
                'time_ago' => 'Live',
            ];
        }

        return $alerts;
    }

    /**
     * Cash totals vs configured minimum floors on cash accounts (same basis as the command center header).
     *
     * @return array{0: float, 1: float, 2: string, 3: ?string}
     */
    public function liquidityTotals(): array
    {
        return $this->liquiditySnapshot();
    }
}
