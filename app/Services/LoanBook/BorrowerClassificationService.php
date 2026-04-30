<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookLoan;
use App\Models\LoanClient;
use App\Models\LoanSystemSetting;

class BorrowerClassificationService
{
    /**
     * @return array<string, mixed>
     */
    public function classify(LoanClient $client, float $requestedAmount): array
    {
        $requestedAmount = max(0.0, $requestedAmount);
        $loans = $client->loanBookLoans()->get();

        $activeLoans = $loans->filter(function (LoanBookLoan $loan): bool {
            if ((string) $loan->status === LoanBookLoan::STATUS_PENDING_DISBURSEMENT) {
                return true;
            }

            return (string) $loan->status !== LoanBookLoan::STATUS_CLOSED
                && (float) ($loan->balance ?? 0) > 0.01;
        });

        $closedLoans = $loans->filter(function (LoanBookLoan $loan): bool {
            return (string) $loan->status === LoanBookLoan::STATUS_CLOSED
                || (float) ($loan->balance ?? 0) <= 0.01;
        });

        $writtenOffExists = $loans->contains(fn (LoanBookLoan $loan): bool => (string) $loan->status === LoanBookLoan::STATUS_WRITTEN_OFF);
        $maxObservedDpd = (int) $loans->max(fn (LoanBookLoan $loan): int => (int) ($loan->dpd ?? 0));
        $loanSequence = (int) $loans->count() + 1;

        $allowTopUp = $this->settingBool('allow_top_up_if_active_loan', false);
        $blockWrittenOff = $this->settingBool('block_if_written_off_history', false);
        $maxInstallmentRatio = $this->settingFloat('max_installment_to_income_ratio', 0.35);
        $maxAllowedDpdForRepeat = max(0, (int) $this->settingFloat('max_allowed_dpd_for_repeat', 5));
        $minRepaymentSuccessRate = min(1.0, max(0.0, $this->settingFloat('min_repayment_success_rate', 0.6)));

        $blockingReasons = [];
        $warnings = [];
        $riskFlags = [];

        if ($activeLoans->isNotEmpty() && ! $allowTopUp) {
            $blockingReasons[] = 'active_open_loan_exists';
        }

        if ($writtenOffExists && $blockWrittenOff) {
            $blockingReasons[] = 'written_off_history_blocked';
            $riskFlags[] = 'written_off_history';
        }

        $monthlyIncome = (float) data_get((array) ($client->biodata_meta ?? []), 'monthly_income', 0);
        $existingEstimatedInstallment = $activeLoans->sum(function (LoanBookLoan $loan): float {
            $termValue = max(1, (int) ($loan->term_value ?? 1));
            return (float) ($loan->balance ?? 0) / $termValue;
        });
        $requestedEstimatedInstallment = $requestedAmount / 12;
        $projectedInstallment = $existingEstimatedInstallment + $requestedEstimatedInstallment;
        $installmentRatio = $monthlyIncome > 0 ? $projectedInstallment / $monthlyIncome : 1.0;
        if ($installmentRatio > $maxInstallmentRatio) {
            $blockingReasons[] = 'installment_to_income_ratio_exceeded';
        }

        if ($maxObservedDpd > $maxAllowedDpdForRepeat) {
            $riskFlags[] = 'historical_dpd_above_threshold';
        }

        $repaymentSuccessRate = 1.0;
        if ($closedLoans->count() > 0) {
            $goodClosed = $closedLoans->filter(fn (LoanBookLoan $loan): bool => (int) ($loan->dpd ?? 0) <= $maxAllowedDpdForRepeat)->count();
            $repaymentSuccessRate = $goodClosed / max(1, $closedLoans->count());
        }
        if ($repaymentSuccessRate < $minRepaymentSuccessRate) {
            $riskFlags[] = 'repayment_success_rate_low';
        }

        $borrowerCategory = 'new_borrower';
        if ($blockingReasons !== []) {
            $borrowerCategory = 'blocked';
        } elseif ($loans->isEmpty()) {
            $borrowerCategory = 'new_borrower';
        } elseif ($activeLoans->isNotEmpty()) {
            $borrowerCategory = 'repeat_risky';
        } elseif ($maxObservedDpd <= 0 && $repaymentSuccessRate >= 0.9) {
            $borrowerCategory = 'repeat_good';
        } elseif ($maxObservedDpd <= $maxAllowedDpdForRepeat && $repaymentSuccessRate >= $minRepaymentSuccessRate) {
            $borrowerCategory = 'repeat_normal';
        } else {
            $borrowerCategory = 'repeat_risky';
        }

        $lastClosedPrincipal = (float) ($closedLoans->sortByDesc('id')->first()?->principal ?? 0);
        $graduationPercent = max(0.0, $this->settingFloat('graduation_increase_percentage', 0));
        $secondLoanLimit = max(0.0, $this->settingFloat('second_loan_limit', 0));

        $suggestedLimit = $requestedAmount;
        if ($loanSequence === 1) {
            $firstLoanLimit = max(0.0, $this->settingFloat('first_loan_limit', 0));
            if ($firstLoanLimit > 0) {
                $suggestedLimit = $firstLoanLimit;
            }
        } elseif ($loanSequence === 2 && $secondLoanLimit > 0) {
            $suggestedLimit = $secondLoanLimit;
        } elseif ($lastClosedPrincipal > 0 && $graduationPercent > 0) {
            $suggestedLimit = $lastClosedPrincipal * (1 + ($graduationPercent / 100));
        }

        $graduationAllowed = true;
        if ($suggestedLimit > 0 && $requestedAmount > $suggestedLimit) {
            $graduationAllowed = false;
            $warnings[] = 'requested_amount_above_suggested_limit';
        }

        $approvalLevel = $borrowerCategory === 'repeat_risky' ? 'manager' : 'standard';
        if ($borrowerCategory === 'blocked') {
            $approvalLevel = 'blocked';
        } elseif ($requestedAmount >= 500000) {
            $approvalLevel = 'director';
        }

        return [
            'borrower_category' => $borrowerCategory,
            'client_capacity' => [
                'monthly_income' => $monthlyIncome,
                'projected_installment' => $projectedInstallment,
                'installment_to_income_ratio' => $installmentRatio,
                'max_installment_to_income_ratio' => $maxInstallmentRatio,
                'repayment_success_rate' => $repaymentSuccessRate,
            ],
            'borrower_decision' => [
                'borrower_category' => $borrowerCategory,
                'blocking_reasons' => array_values(array_unique($blockingReasons)),
                'warnings' => array_values(array_unique($warnings)),
                'risk_flags' => array_values(array_unique($riskFlags)),
                'approval_level_required' => $approvalLevel,
                'graduation_allowed' => $graduationAllowed,
                'suggested_max_limit' => $suggestedLimit,
                'client_loan_sequence' => $loanSequence,
            ],
        ];
    }

    private function settingBool(string $key, bool $default): bool
    {
        return LoanSystemSetting::getValue($key, $default ? '1' : '0') === '1';
    }

    private function settingFloat(string $key, float $default): float
    {
        $raw = LoanSystemSetting::getValue($key, (string) $default);
        if ($raw === null || ! is_numeric($raw)) {
            return $default;
        }

        return (float) $raw;
    }
}
