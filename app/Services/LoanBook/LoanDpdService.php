<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookLoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LoanDpdService
{
    public function __construct(
        private readonly LoanBookLoanUpdateService $loanMath,
    ) {}

    public function compute(LoanBookLoan $loan, ?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();

        if ($loan->isSettled() || (float) $loan->balance <= 0.01) {
            return 0;
        }

        if ($loan->disbursed_at === null || ! $loan->acceptsPostedLoanCollections()) {
            return 0;
        }

        if (! in_array($loan->status, [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED], true)) {
            return 0;
        }

        $loan->loadMissing('application');

        $paid = (float) $loan->processedRepayments()->sum('amount');
        $remaining = max(0.0, (float) $loan->balance);
        $termValue = max(1, (int) ($loan->term_value ?: $loan->term_months ?: 1));

        $interestEstimate = $this->loanMath->estimateInterestForLoan(
            $loan,
            (float) $loan->principal,
            (float) $loan->interest_rate
        );
        $contractRepayable = round((float) $loan->principal + $interestEstimate + (float) ($loan->fees_outstanding ?? 0), 2);
        if ($contractRepayable <= 0.0) {
            $contractRepayable = max(0.01, round($paid + $remaining, 2));
        }

        $baseInstallmentAmount = max(0.01, $contractRepayable / $termValue);
        $scheduleUnit = $this->resolveScheduleUnit($loan);
        $disbursedAt = Carbon::parse($loan->disbursed_at)->startOfDay();

        for ($installment = 1; $installment <= $termValue; $installment++) {
            $dueDate = $this->addScheduleStep($disbursedAt, $installment, $scheduleUnit)->startOfDay();
            $coveredByPaid = $paid + 0.01 >= ($baseInstallmentAmount * $installment);
            if (! $coveredByPaid && $dueDate->lt($asOf)) {
                return max(0, (int) $dueDate->diffInDays($asOf));
            }
        }

        return 0;
    }

    public function refreshLoan(LoanBookLoan $loan, ?Carbon $asOf = null): bool
    {
        $next = $this->compute($loan, $asOf);
        if ((int) ($loan->dpd ?? 0) === $next) {
            return false;
        }

        $loan->forceFill(['dpd' => $next])->save();

        return true;
    }

    /**
     * @param  callable(Builder): void|null  $scopeQuery
     */
    public function refreshActiveLoans(?callable $scopeQuery = null, ?Carbon $asOf = null): int
    {
        $query = LoanBookLoan::query()
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->where('balance', '>', 0.01)
            ->whereNotNull('disbursed_at');

        if ($scopeQuery) {
            $scopeQuery($query);
        }

        $updated = 0;
        $query->orderBy('id')->chunkById(200, function ($loans) use (&$updated, $asOf): void {
            foreach ($loans as $loan) {
                if ($this->refreshLoan($loan, $asOf)) {
                    $updated++;
                }
            }
        });

        return $updated;
    }

    private function resolveScheduleUnit(LoanBookLoan $loan): string
    {
        $rawTermUnit = strtolower(trim((string) ($loan->term_unit ?: 'monthly')));

        return str_contains($rawTermUnit, 'day')
            ? 'daily'
            : (str_contains($rawTermUnit, 'week')
                ? 'weekly'
                : (str_contains($rawTermUnit, 'year') ? 'annual' : 'monthly'));
    }

    private function addScheduleStep(Carbon $date, int $steps, string $scheduleUnit): Carbon
    {
        return match ($scheduleUnit) {
            'daily' => $date->copy()->addDays($steps),
            'weekly' => $date->copy()->addWeeks($steps),
            'annual' => $date->copy()->addYearsNoOverflow($steps),
            default => $date->copy()->addMonthsNoOverflow($steps),
        };
    }
}
