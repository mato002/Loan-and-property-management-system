<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PropertyTransactionReversalService
{
    public function reversePayment(PmPayment $payment, ?int $actorId = null, ?string $reason = null, ?int $utilityOverrideRequestId = null): void
    {
        DB::transaction(function () use ($payment, $actorId, $reason, $utilityOverrideRequestId) {
            $payment->loadMissing('allocations.invoice');
            $actor = $actorId ? \App\Models\User::query()->find($actorId) : null;
            app(UtilityPeriodGuardService::class)->assertPaymentReversalMutable($payment, $actor, $utilityOverrideRequestId);

            $journalBatch = AccountingJournalBatch::query()
                ->where('source_type', 'pm_payment')
                ->where('source_id', $payment->id)
                ->where('event_type', 'payment_received')
                ->first();

            if (! $journalBatch) {
                throw new RuntimeException('No posted payment journal batch found for reversal.');
            }

            app(PropertyJournalService::class)->reverseBatch($journalBatch, $actorId, $reason);
            $this->reversePaymentAllocations($payment, $actorId, $reason);
            $this->reverseLandlordLedger($payment, $actorId, $reason);

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['reversal'] = [
                'reversed_by' => $actorId,
                'reason' => $reason,
                'reversed_at' => now()->toIso8601String(),
            ];

            $payment->meta = $meta;
            $payment->status = PmPayment::STATUS_FAILED;
            $payment->save();
        });
    }

    private function reversePaymentAllocations(PmPayment $payment, ?int $actorId, ?string $reason): void
    {
        $allocations = PmPaymentAllocation::query()
            ->where('pm_payment_id', $payment->id)
            ->where(function ($q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->get();

        foreach ($allocations as $allocation) {
            $invoice = PmInvoice::query()->find($allocation->pm_invoice_id);
            if ($invoice) {
                $invoice->amount_paid = max(0, (float) $invoice->amount_paid - (float) $allocation->amount);
                $invoice->save();
                $invoice->refreshComputedStatus();
            }

            $allocation->is_reversed = true;
            $allocation->reversed_by = $actorId;
            $allocation->reversed_at = now();
            $allocation->reversal_reason = $reason;
            $allocation->save();
        }
    }

    private function reverseLandlordLedger(PmPayment $payment, ?int $actorId, ?string $reason): void
    {
        $entries = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', $payment->id)
            ->whereNull('reversal_of_id')
            ->get();

        foreach ($entries as $entry) {
            $reversalDirection = $entry->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT
                ? PmLandlordLedgerEntry::DIRECTION_DEBIT
                : PmLandlordLedgerEntry::DIRECTION_CREDIT;

            $new = LandlordLedger::post(
                $entry->user,
                $reversalDirection,
                (float) $entry->amount,
                'Reversal of landlord ledger entry #'.$entry->id.($reason ? ' - '.$reason : ''),
                $entry->property,
                'pm_payment_reversal',
                (int) $payment->id,
                now()
            );

            $new->reversal_of_id = $entry->id;
            $new->reversed_by = $actorId;
            $new->reversed_at = now();
            $new->agent_user_id = $entry->agent_user_id;
            $new->save();
        }
    }
}

