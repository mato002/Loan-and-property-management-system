<?php

namespace App\Services;

use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Services\LoanBook\CollectionsCommandCenterService;
use Illuminate\Support\Facades\Schema;

/**
 * Live metrics for the loan settings UI (disbursement pipeline, liquidity, mapping, risk queues).
 */
class LoanSettingsOverviewService
{
    public function __construct(
        private AccountingEventRegistryService $accountingEventRegistry,
        private CollectionsCommandCenterService $collectionsCommandCenter,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $normalizedApprovalRows
     * @return array<string, mixed>
     */
    public function build(array $normalizedApprovalRows): array
    {
        [$cashTotal, $floorTotal, $liquidityStatus, $breachDate] = $this->collectionsCommandCenter->liquidityTotals();

        $loanDisbursedRow = $this->accountingEventRegistry
            ->eventRowsForChartRules()
            ->firstWhere(fn (array $r): bool => ($r['event_key'] ?? '') === 'LoanDisbursed');

        $creditCashBalance = 0.0;
        if (is_array($loanDisbursedRow) && ! empty($loanDisbursedRow['credit_account'])) {
            $acct = $loanDisbursedRow['credit_account'];
            $creditCashBalance = (float) ($acct->current_balance ?? 0);
        }

        $pending = $this->pendingDisbursementSnapshot();
        $highRiskApplications = $this->countHighRiskPipelineApplications();
        $riskyBorrowersOnBook = $this->countRiskyBorrowersOnBook();

        $approvalTierCounts = $this->approvalTierCounts($normalizedApprovalRows);

        $lendingBrakeLabel = $liquidityStatus === 'AT RISK' ? 'Engaged' : 'Healthy';
        $lendingBrakeHint = $liquidityStatus === 'AT RISK'
            ? 'Cash is below configured chart floors — treat new disbursements as restricted.'
            : 'Liquidity is at or above configured floors.';

        return [
            'available_liquidity' => $cashTotal,
            'liquidity_floor_chart' => $floorTotal,
            'liquidity_status' => $liquidityStatus,
            'liquidity_breach_date' => $breachDate,
            'loan_disbursed_row' => $loanDisbursedRow,
            'disbursement_credit_balance' => $creditCashBalance,
            'pending_disbursement_count' => $pending['count'],
            'pending_disbursement_principal' => $pending['principal_total'],
            'pending_disbursement_no_payout_count' => $pending['no_payout_count'],
            'high_risk_applications' => $highRiskApplications,
            'risky_borrowers_on_book' => $riskyBorrowersOnBook,
            'approval_tier_counts' => $approvalTierCounts,
            'lending_brake_label' => $lendingBrakeLabel,
            'lending_brake_hint' => $lendingBrakeHint,
            'applications_in_credit_review' => $this->countApplicationsByStage(LoanBookApplication::STAGE_CREDIT_REVIEW),
        ];
    }

    /**
     * @return array{count: int, principal_total: float, no_payout_count: int}
     */
    private function pendingDisbursementSnapshot(): array
    {
        if (! Schema::hasTable('loan_book_loans')) {
            return ['count' => 0, 'principal_total' => 0.0, 'no_payout_count' => 0];
        }

        $base = LoanBookLoan::query()->where('status', LoanBookLoan::STATUS_PENDING_DISBURSEMENT);
        $count = (int) (clone $base)->count();
        $principalTotal = (float) (clone $base)->sum('principal');

        $noPayout = 0;
        if (Schema::hasTable('loan_book_disbursements')) {
            $noPayout = (int) (clone $base)->whereDoesntHave('disbursements')->count();
        }

        return [
            'count' => $count,
            'principal_total' => $principalTotal,
            'no_payout_count' => $noPayout,
        ];
    }

    private function countHighRiskPipelineApplications(): int
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return 0;
        }

        $q = LoanBookApplication::query()
            ->whereIn('stage', [
                LoanBookApplication::STAGE_SUBMITTED,
                LoanBookApplication::STAGE_CREDIT_REVIEW,
            ]);

        if (Schema::hasColumn('loan_book_applications', 'borrower_category')) {
            $q->whereIn('borrower_category', ['repeat_risky', 'blocked']);
        } else {
            return 0;
        }

        return (int) $q->count();
    }

    private function countApplicationsByStage(string $stage): int
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return 0;
        }

        return (int) LoanBookApplication::query()->where('stage', $stage)->count();
    }

    private function countRiskyBorrowersOnBook(): int
    {
        if (! Schema::hasTable('loan_book_loans') || ! Schema::hasColumn('loan_book_loans', 'borrower_category')) {
            return 0;
        }

        return (int) LoanBookLoan::query()
            ->where('borrower_category', 'repeat_risky')
            ->where('status', '!=', LoanBookLoan::STATUS_CLOSED)
            ->where('balance', '>', 0.01)
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{standard: int, manager: int, director: int}
     */
    public function approvalTierCounts(array $rows): array
    {
        $standard = 0;
        $manager = 0;
        $director = 0;

        foreach ($rows as $row) {
            $tier = strtolower((string) ($row['approval_tier'] ?? ''));
            if (in_array($tier, ['standard', 'manager', 'director'], true)) {
                if ($tier === 'standard') {
                    $standard++;
                } elseif ($tier === 'manager') {
                    $manager++;
                } else {
                    $director++;
                }

                continue;
            }

            $a = strtolower((string) ($row['approver'] ?? ''));
            if (str_contains($a, 'director')) {
                $director++;
            } elseif (str_contains($a, 'branch')) {
                $standard++;
            } elseif (str_contains($a, 'regional') || str_contains($a, 'credit')) {
                $manager++;
            } else {
                $standard++;
            }
        }

        return [
            'standard' => $standard,
            'manager' => $manager,
            'director' => $director,
        ];
    }
}
