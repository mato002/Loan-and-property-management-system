<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPenaltyRule;
use Illuminate\Database\Eloquent\Builder;

final class PenaltyEngineService
{
    /**
     * TYPE_MIXED carry-forward opening-arrears invoices are excluded from utility penalties.
     */
    public function isPenaltyEligible(PmInvoice $invoice, string $scope = 'water'): bool
    {
        if (! in_array((string) $invoice->status, [
            PmInvoice::STATUS_SENT,
            PmInvoice::STATUS_PARTIAL,
        ], true)) {
            return false;
        }

        if (str_starts_with((string) $invoice->description, FinanceFirebreakService::CARRY_FORWARD_PREFIX)) {
            return false;
        }

        if ($scope === 'water') {
            return (string) $invoice->invoice_type === PmInvoice::TYPE_WATER;
        }

        return true;
    }

    public function eligibleInvoiceQuery(string $scope = 'water'): Builder
    {
        $query = PmInvoice::query()
            ->billableAr()
            ->withOutstandingBalance()
            ->where(function (Builder $inner) {
                $inner->whereNull('description')
                    ->orWhere('description', 'not like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%');
            });

        if ($scope === 'water') {
            $query->where('invoice_type', PmInvoice::TYPE_WATER);
        }

        return $query;
    }

    /**
     * Sync allocations and return a fresh locked invoice row for penalty math.
     */
    public function prepareInvoiceForPenalty(int $invoiceId): ?PmInvoice
    {
        $invoice = PmInvoice::query()->whereKey($invoiceId)->lockForUpdate()->first();
        if (! $invoice) {
            return null;
        }

        $invoice->syncAmountPaidFromAllocations();

        return PmInvoice::query()->whereKey($invoiceId)->first();
    }

    public function openPenaltyBase(PmInvoice $invoice): float
    {
        return max(0.0, round((float) $invoice->amount - (float) $invoice->amount_paid, 2));
    }

    public function daysOverdue(PmInvoice $invoice, string $asOfDate, int $graceDays): int
    {
        if (! $invoice->due_date) {
            return 0;
        }

        $asOf = now()->parse($asOfDate)->startOfDay();
        $due = $invoice->due_date->copy()->startOfDay()->addDays($graceDays);
        if ($due->gte($asOf)) {
            return 0;
        }

        return (int) $due->diffInDays($asOf);
    }

    /**
     * @return array{
     *     penalty: float,
     *     base: float,
     *     days_overdue: int,
     *     compounding_mode: string,
     *     cumulative_applied: float,
     *     warnings: list<string>
     * }
     */
    public function simulate(PmPenaltyRule $rule, PmInvoice $invoice, string $asOfDate, string $thresholdDate): array
    {
        $warnings = [];
        $mode = $this->normalizeCompoundingMode((string) ($rule->compounding_mode ?? PmPenaltyRule::COMPOUNDING_SIMPLE));
        if ($mode === PmPenaltyRule::COMPOUNDING_DAILY) {
            $warnings[] = 'Daily compounding enabled for rule '.$rule->name;
        }

        $graceDays = (int) ($rule->grace_days ?? 0);
        $daysOverdue = $this->daysOverdue($invoice, $asOfDate, $graceDays);
        $base = $this->openPenaltyBase($invoice);
        $cumulativeApplied = $this->cumulativeAppliedAmount((int) $invoice->id, (int) $rule->id);

        if ($base <= 0 || $daysOverdue <= 0) {
            return [
                'penalty' => 0.0,
                'base' => $base,
                'days_overdue' => $daysOverdue,
                'compounding_mode' => $mode,
                'cumulative_applied' => $cumulativeApplied,
                'warnings' => $warnings,
            ];
        }

        $penalty = $this->calculatePenaltyAmount($rule, $base, $daysOverdue, $mode);
        $penalty = $this->applyCumulativeCap($rule, $penalty, $cumulativeApplied);

        return [
            'penalty' => round(max(0.0, $penalty), 2),
            'base' => $base,
            'days_overdue' => $daysOverdue,
            'compounding_mode' => $mode,
            'cumulative_applied' => $cumulativeApplied,
            'warnings' => $warnings,
        ];
    }

    public function hasBlockingApplication(
        PmInvoice $invoice,
        PmPenaltyRule $rule,
        string $thresholdDate,
    ): bool {
        $mode = $this->normalizeCompoundingMode((string) ($rule->compounding_mode ?? PmPenaltyRule::COMPOUNDING_SIMPLE));

        if ($mode === PmPenaltyRule::COMPOUNDING_ONE_SHOT) {
            return PmInvoicePenaltyApplication::query()
                ->where('pm_invoice_id', $invoice->id)
                ->where('pm_penalty_rule_id', $rule->id)
                ->whereNull('reversed_at')
                ->exists();
        }

        return PmInvoicePenaltyApplication::query()
            ->where('pm_invoice_id', $invoice->id)
            ->where('pm_penalty_rule_id', $rule->id)
            ->whereDate('threshold_date', $thresholdDate)
            ->whereNull('reversed_at')
            ->exists();
    }

    public function cumulativeAppliedAmount(int $invoiceId, int $ruleId): float
    {
        return round((float) PmInvoicePenaltyApplication::query()
            ->where('pm_invoice_id', $invoiceId)
            ->where('pm_penalty_rule_id', $ruleId)
            ->whereNull('reversed_at')
            ->sum('amount'), 2);
    }

    public function calculatePenaltyAmount(
        PmPenaltyRule $rule,
        float $base,
        int $daysOverdue,
        ?string $compoundingMode = null,
    ): float {
        $mode = $this->normalizeCompoundingMode($compoundingMode ?? (string) ($rule->compounding_mode ?? PmPenaltyRule::COMPOUNDING_SIMPLE));
        $penalty = 0.0;

        if (in_array((string) $rule->formula, ['flat', 'fixed'], true)) {
            $penalty = (float) ($rule->amount ?? 0);
        } else {
            $rate = ((float) ($rule->percent ?? 0)) / 100;
            $penalty = match ($mode) {
                PmPenaltyRule::COMPOUNDING_DAILY => $base * (pow(1 + $rate, max(1, $daysOverdue)) - 1),
                PmPenaltyRule::COMPOUNDING_ONE_SHOT, PmPenaltyRule::COMPOUNDING_SIMPLE => $base * $rate,
                default => $base * $rate,
            };

            if ((float) ($rule->amount ?? 0) > 0) {
                $penalty += (float) $rule->amount;
            }
        }

        if ((float) ($rule->cap ?? 0) > 0) {
            $penalty = min($penalty, (float) $rule->cap);
        }

        return round(max(0.0, $penalty), 2);
    }

    public function applyCumulativeCap(PmPenaltyRule $rule, float $penalty, float $cumulativeApplied): float
    {
        $cumulativeCap = (float) ($rule->cumulative_cap ?? 0);
        if ($cumulativeCap <= 0) {
            return $penalty;
        }

        $remaining = max(0.0, round($cumulativeCap - $cumulativeApplied, 2));

        return min($penalty, $remaining);
    }

    public function normalizeCompoundingMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            PmPenaltyRule::COMPOUNDING_DAILY, 'daily', 'daily_compound' => PmPenaltyRule::COMPOUNDING_DAILY,
            PmPenaltyRule::COMPOUNDING_ONE_SHOT, 'one_shot', 'once' => PmPenaltyRule::COMPOUNDING_ONE_SHOT,
            default => PmPenaltyRule::COMPOUNDING_SIMPLE,
        };
    }

    /**
     * @return list<string>
     */
    public function ruleOperatorWarnings(PmPenaltyRule $rule): array
    {
        $warnings = [];
        $mode = $this->normalizeCompoundingMode((string) ($rule->compounding_mode ?? PmPenaltyRule::COMPOUNDING_SIMPLE));

        if ($mode === PmPenaltyRule::COMPOUNDING_DAILY) {
            $warnings[] = 'Daily compounding enabled — penalty grows exponentially with overdue days.';
        }

        if ($mode === PmPenaltyRule::COMPOUNDING_ONE_SHOT) {
            $warnings[] = 'One-shot mode — this rule applies at most once per invoice until reversed.';
        }

        if ((float) ($rule->cumulative_cap ?? 0) > 0) {
            $warnings[] = 'Cumulative cap active — total penalties for this rule cannot exceed '
                .number_format((float) $rule->cumulative_cap, 2).' on one invoice.';
        }

        return $warnings;
    }
}
