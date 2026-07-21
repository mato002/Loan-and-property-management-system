<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\User;

class PropertyReversalFinalizeService
{
    public function __construct(
        private readonly WaterPenaltyService $penalties,
        private readonly LandlordSubledgerService $landlordSubledger,
    ) {}

    /**
     * Financially complete invoice reversal: AR/revenue issuance batches,
     * incremental penalties, and landlord subledger impacts on linked payments.
     */
    public function reverseInvoiceFully(PmInvoice $invoice, ?User $actor = null, ?string $reason = null): void
    {
        PropertyAccountingPostingService::reverseAllInvoiceIssuanceBatches($invoice, $actor, $reason);
        $this->reverseActivePenaltiesForInvoice($invoice, $actor, $reason);
        $this->reverseLandlordImpactsForInvoice($invoice, $actor?->id, $reason);
    }

    /**
     * Post credit memo GL and adjust landlord subledger for the original invoice.
     */
    public function issueCreditMemo(PmInvoice $creditNote, ?User $actor = null): void
    {
        PropertyAccountingPostingService::postCreditMemoIssued($creditNote, $actor);

        $creditNote->loadMissing('originalInvoice');
        $original = $creditNote->originalInvoice;
        if (! $original) {
            return;
        }

        $this->landlordSubledger->adjustForCreditMemo(
            $creditNote,
            $original,
            round(abs((float) $creditNote->amount), 2),
            $actor?->id,
        );
    }

    private function reverseActivePenaltiesForInvoice(PmInvoice $invoice, ?User $actor, ?string $reason): void
    {
        PmInvoicePenaltyApplication::query()
            ->where('pm_invoice_id', (int) $invoice->id)
            ->whereNull('reversed_at')
            ->orderBy('id')
            ->each(function (PmInvoicePenaltyApplication $application) use ($actor, $reason) {
                $this->penalties->reverseApplication(
                    $application,
                    $actor,
                    $reason ?: 'Invoice reversed',
                );
            });
    }

    private function reverseLandlordImpactsForInvoice(PmInvoice $invoice, ?int $actorId, ?string $reason): void
    {
        $invoice->loadMissing('allocations.payment');
        $paymentIds = ($invoice->allocations ?? collect())
            ->filter(fn ($allocation) => ! $allocation->is_reversed)
            ->pluck('pm_payment_id')
            ->filter()
            ->unique();

        foreach ($paymentIds as $paymentId) {
            $payment = $invoice->allocations
                ->firstWhere('pm_payment_id', $paymentId)
                ?->payment;
            if (! $payment || $payment->status !== \App\Models\PmPayment::STATUS_COMPLETED) {
                continue;
            }

            $this->landlordSubledger->reverseForPayment($payment, $actorId, $reason ?: 'Invoice reversed');
        }
    }
}
