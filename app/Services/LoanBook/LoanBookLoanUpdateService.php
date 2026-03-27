<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookApplication;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanSystemSetting;
use Illuminate\Support\Facades\DB;

class LoanBookLoanUpdateService
{
    private const SETTING_REPAYMENT_ORDER = 'loan_repayment_allocation_order';

    /**
     * Apply a disbursement effect to the loan record (status + disbursed_at).
     * This does not change principal; it ensures the loan is marked active and timestamps are consistent.
     */
    public function onDisbursed(LoanBookDisbursement $disbursement): void
    {
        DB::transaction(function () use ($disbursement) {
            /** @var LoanBookLoan $loan */
            $loan = LoanBookLoan::query()->lockForUpdate()->findOrFail($disbursement->loan_book_loan_id);

            $loan->disbursed_at = $loan->disbursed_at ?: $disbursement->disbursed_at;
            if ($loan->status === LoanBookLoan::STATUS_PENDING_DISBURSEMENT) {
                $loan->status = LoanBookLoan::STATUS_ACTIVE;
            }

            // Ensure a sensible starting balance.
            if ((float) $loan->balance <= 0 && (float) $loan->principal > 0) {
                $loan->balance = (float) $loan->principal;
            }
            if ((float) $loan->principal_outstanding <= 0 && (float) $loan->principal > 0) {
                $loan->principal_outstanding = (float) $loan->principal;
            }

            $loan->save();

            // Sync application stage if linked.
            if ($loan->loan_book_application_id) {
                LoanBookApplication::query()
                    ->whereKey($loan->loan_book_application_id)
                    ->update(['stage' => LoanBookApplication::STAGE_DISBURSED]);
            }
        });
    }

    /**
     * Apply a processed payment effect to the loan record by reducing outstanding balance.
     * Note: This system currently tracks a single outstanding "balance" number, not split principal/interest.
     */
    public function onPaymentProcessed(LoanBookPayment $payment): void
    {
        if (! $payment->loan_book_loan_id) {
            return;
        }

        DB::transaction(function () use ($payment) {
            /** @var LoanBookLoan $loan */
            $loan = LoanBookLoan::query()->lockForUpdate()->findOrFail($payment->loan_book_loan_id);

            // For C2B reversals we store negative amounts; treat as increasing balance back.
            $signed = (float) $payment->amount;
            if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
                $delta = abs($signed);
                $loan->balance = round((float) $loan->balance + $delta, 2);
                // Reversals are ambiguous re: which bucket to restore; default to principal.
                $loan->principal_outstanding = round((float) $loan->principal_outstanding + $delta, 2);
                $loan->status = $loan->status === LoanBookLoan::STATUS_CLOSED ? LoanBookLoan::STATUS_ACTIVE : $loan->status;
                $loan->save();
                return;
            }

            $delta = abs($signed);
            $remaining = $delta;

            // Default buckets if empty: treat everything as principal.
            if ((float) $loan->principal_outstanding <= 0 && (float) $loan->balance > 0) {
                $loan->principal_outstanding = (float) $loan->balance;
            }

            foreach ($this->repaymentOrder() as $bucket) {
                if ($remaining <= 0) {
                    break;
                }

                if ($bucket === 'fees') {
                    $apply = min($remaining, max(0.0, (float) $loan->fees_outstanding));
                    $loan->fees_outstanding = round((float) $loan->fees_outstanding - $apply, 2);
                    $remaining -= $apply;
                    continue;
                }
                if ($bucket === 'interest') {
                    $apply = min($remaining, max(0.0, (float) $loan->interest_outstanding));
                    $loan->interest_outstanding = round((float) $loan->interest_outstanding - $apply, 2);
                    $remaining -= $apply;
                    continue;
                }
                if ($bucket === 'principal') {
                    $apply = min($remaining, max(0.0, (float) $loan->principal_outstanding));
                    $loan->principal_outstanding = round((float) $loan->principal_outstanding - $apply, 2);
                    $remaining -= $apply;
                    continue;
                }
            }

            $loan->balance = round(max(0.0, (float) $loan->principal_outstanding + (float) $loan->interest_outstanding + (float) $loan->fees_outstanding), 2);

            if ((float) $loan->balance <= 0.0) {
                $loan->status = LoanBookLoan::STATUS_CLOSED;
                $loan->dpd = 0;
            }

            $loan->save();
        });
    }

    /**
     * @return list<'fees'|'interest'|'principal'>
     */
    private function repaymentOrder(): array
    {
        $raw = (string) (LoanSystemSetting::getValue(self::SETTING_REPAYMENT_ORDER, 'fees,interest,principal') ?? '');
        $parts = array_values(array_filter(array_map(
            static fn (string $p) => strtolower(trim($p)),
            explode(',', $raw)
        )));

        $valid = ['fees', 'interest', 'principal'];
        $order = array_values(array_intersect($parts, $valid));
        foreach ($valid as $v) {
            if (! in_array($v, $order, true)) {
                $order[] = $v;
            }
        }

        return $order;
    }
}

