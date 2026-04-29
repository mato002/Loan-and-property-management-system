<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanPaymentAllocation;
use App\Models\LoanSystemSetting;

class LoanRepaymentAllocationService
{
    private const SETTING_REPAYMENT_ORDER = 'loan_repayment_allocation_order';

    /**
     * @return list<'principal'|'interest'|'fees'|'penalty'|'overpayment'>
     */
    public function repaymentOrder(): array
    {
        $raw = (string) (LoanSystemSetting::getValue(
            self::SETTING_REPAYMENT_ORDER,
            'principal,interest,fees,penalty,overpayment'
        ) ?? '');
        $parts = array_values(array_filter(array_map(
            static fn (string $p): string => strtolower(trim($p)),
            explode(',', $raw)
        )));

        $base = ['principal', 'interest', 'fees', 'penalty'];
        $orderedBase = array_values(array_intersect($parts, $base));
        foreach ($base as $bucket) {
            if (! in_array($bucket, $orderedBase, true)) {
                $orderedBase[] = $bucket;
            }
        }

        // Overpayment is always resolved as residual at the end.
        $orderedBase[] = 'overpayment';

        return $orderedBase;
    }

    /**
     * @return array{
     *   allocations: array<'principal'|'interest'|'fees'|'penalty'|'overpayment', float>,
     *   order: list<'principal'|'interest'|'fees'|'penalty'|'overpayment'>
     * }
     */
    public function allocate(LoanBookPayment $payment, ?LoanBookLoan $loan = null, ?float $amount = null): array
    {
        $value = round(abs($amount ?? (float) $payment->amount), 2);
        $order = $this->repaymentOrder();
        $allocations = [
            'principal' => 0.0,
            'interest' => 0.0,
            'fees' => 0.0,
            'penalty' => 0.0,
            'overpayment' => 0.0,
        ];

        if ($value <= 0.0) {
            return ['allocations' => $allocations, 'order' => $order];
        }

        if ($payment->payment_kind === LoanBookPayment::KIND_OVERPAYMENT) {
            $allocations['overpayment'] = $value;

            return ['allocations' => $allocations, 'order' => $order];
        }

        $loan ??= $payment->loan;
        if (! $loan) {
            $allocations['principal'] = $value;

            return ['allocations' => $allocations, 'order' => $order];
        }

        $remaining = $value;
        $principal = max(0.0, (float) ($loan->principal_outstanding ?? 0.0));
        $interest = max(0.0, (float) ($loan->interest_outstanding ?? 0.0));
        $fees = max(0.0, (float) ($loan->fees_outstanding ?? 0.0));

        if ($principal <= 0.0 && $interest <= 0.0 && $fees <= 0.0) {
            $principal = max(0.0, (float) ($loan->balance ?? 0.0));
        }

        foreach ($order as $bucket) {
            if ($bucket === 'overpayment' || $remaining <= 0.0) {
                continue;
            }

            if ($bucket === 'principal') {
                $apply = min($remaining, $principal);
                $principal = round($principal - $apply, 2);
                $allocations['principal'] += $apply;
                $remaining -= $apply;
                continue;
            }

            if ($bucket === 'interest') {
                $apply = min($remaining, $interest);
                $interest = round($interest - $apply, 2);
                $allocations['interest'] += $apply;
                $remaining -= $apply;
                continue;
            }

            // Current loan model has a single fees bucket; penalty is tracked within it.
            if ($bucket === 'fees') {
                $apply = min($remaining, $fees);
                $fees = round($fees - $apply, 2);
                $allocations['fees'] += $apply;
                $remaining -= $apply;
                continue;
            }

            if ($bucket === 'penalty') {
                $apply = min($remaining, $fees);
                $fees = round($fees - $apply, 2);
                $allocations['penalty'] += $apply;
                $remaining -= $apply;
            }
        }

        if ($remaining > 0.0) {
            $allocations['overpayment'] += $remaining;
        }

        return [
            'allocations' => $allocations,
            'order' => $order,
        ];
    }

    /**
     * @param  array<'principal'|'interest'|'fees'|'penalty'|'overpayment', float>  $allocations
     * @param  list<'principal'|'interest'|'fees'|'penalty'|'overpayment'>  $order
     */
    public function persistAllocation(LoanBookPayment $payment, array $allocations, array $order): void
    {
        LoanPaymentAllocation::query()
            ->where('loan_book_payment_id', $payment->id)
            ->delete();

        $position = array_flip($order);
        foreach ($allocations as $component => $amount) {
            $value = round((float) $amount, 2);
            if ($value <= 0.0) {
                continue;
            }
            LoanPaymentAllocation::query()->create([
                'loan_book_payment_id' => $payment->id,
                'loan_book_loan_id' => $payment->loan_book_loan_id,
                'component' => $component,
                'amount' => $value,
                'allocation_order' => ((int) ($position[$component] ?? 0)) + 1,
            ]);
        }
    }
}
