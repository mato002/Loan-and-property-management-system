<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PropertyAccountingFinalizeService
{
    private const ACC_CASH_BANK = '1100';

    private const ACC_AR = '1200';

    private const ACC_UTILITY_AR = '1210';

    private const ACC_LANDLORD_CLEARING = '1300';

    /** @var array<string, int>|null */
    private ?array $accountIds = null;

    public function __construct(
        private readonly TenantCreditService $tenantCreditService,
        private readonly LandlordSubledgerService $landlordSubledger,
    ) {}

    /**
     * Single accounting gateway after operational payment settlement (allocations + tenant credit).
     */
    public function afterPaymentSettled(PmPayment $payment, ?User $actor = null, ?float $unallocatedAmount = null): void
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED || (float) $payment->amount <= 0) {
            return;
        }

        if ((string) $payment->channel === 'tenant_credit') {
            return;
        }

        $payment->loadMissing('allocations.invoice.unit');

        if ($this->hasConflictingPaymentBatches($payment)) {
            Log::error('Payment has both payment_received and suspense GL batches', [
                'payment_id' => (int) $payment->id,
            ]);

            throw new RuntimeException('Payment #'.$payment->id.' has conflicting accounting batches.');
        }

        if ($this->hasPostedPaymentBatch($payment, 'payment_received')) {
            $this->requireLandlordSubledgerCredits($payment);

            return;
        }

        if ($this->hasPostedPaymentBatch($payment, 'payment_unmatched_suspense')) {
            return;
        }

        [$allocated, $gross, $remaining] = $this->paymentAmounts($payment, $unallocatedAmount);

        if ($remaining > 0.0001
            && (int) $payment->pm_tenant_id > 0
            && $this->tenantCreditService->isEnabled()) {
            $this->tenantCreditService->createCreditFromOverpayment($payment, $remaining, $actor);
            $remaining = 0.0;
        }

        if ($this->shouldPostSuspenseOnly($payment, $remaining)) {
            $batch = PropertyAccountingPostingService::postUnmatchedPaymentToSuspense($payment, $actor);
            if ($batch) {
                $this->assertPaymentBatchInvariants($batch, $payment, 'payment_unmatched_suspense');
            }

            return;
        }

        if ($this->shouldPostPaymentReceived($payment, $gross)) {
            PropertyAccountingPostingService::postPaymentReceived($payment, $actor);

            $batch = AccountingJournalBatch::query()
                ->where('source_type', 'pm_payment')
                ->where('source_id', (int) $payment->id)
                ->where('event_type', 'payment_received')
                ->where('status', AccountingJournalBatch::STATUS_POSTED)
                ->first();

            if ($batch) {
                $this->assertPaymentBatchInvariants($batch, $payment, 'payment_received');
            }

            $this->requireLandlordSubledgerCredits($payment);
        }
    }

    public function afterTenantCreditApplied(PmInvoice $invoice, float $amount, int $creditTransactionId, ?User $actor = null): void
    {
        PropertyAccountingPostingService::postTenantCreditApplied($invoice, $amount, $creditTransactionId, $actor);

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_tenant_credit_transaction')
            ->where('source_id', $creditTransactionId)
            ->where('event_type', 'tenant_credit_applied')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->first();

        if ($batch) {
            $this->assertBalancedBatch($batch);
        }
    }

    public function afterTenantCreditRefunded(int $tenantId, float $amount, ?string $reference, ?User $actor = null): void
    {
        PropertyAccountingPostingService::postTenantCreditRefund($tenantId, $amount, $reference, $actor);
    }

    /**
     * Reverse all GL + landlord ledger + tenant credit effects for a payment.
     */
    public function reversePayment(PmPayment $payment, ?int $actorId = null, ?string $reason = null): void
    {
        $actor = $actorId ? User::query()->find($actorId) : null;
        $journal = app(PropertyJournalService::class);

        $this->tenantCreditService->reverseCreditApplicationPayment($payment, $actor, $reason);
        $this->tenantCreditService->reverseCreditFromPayment($payment, $actor, $reason);

        foreach (['payment_received', 'payment_unmatched_suspense'] as $eventType) {
            $batch = AccountingJournalBatch::query()
                ->where('source_type', 'pm_payment')
                ->where('source_id', (int) $payment->id)
                ->where('event_type', $eventType)
                ->where('status', AccountingJournalBatch::STATUS_POSTED)
                ->first();

            if ($batch) {
                $journal->reverseBatch($batch, $actorId, $reason ?: 'Payment reversed');
            }
        }

        $this->landlordSubledger->reverseForPayment($payment, $actorId, $reason);
    }

    public function postLandlordLedgerCredits(PmPayment $payment): void
    {
        $this->landlordSubledger->postCreditsForPayment($payment);
    }

    private function requireLandlordSubledgerCredits(PmPayment $payment): void
    {
        $payment->loadMissing('allocations.invoice.unit');
        $hasActiveAllocations = ($payment->allocations ?? collect())
            ->contains(fn (PmPaymentAllocation $row) => ! $row->is_reversed);

        if (! $hasActiveAllocations) {
            return;
        }

        $this->landlordSubledger->postCreditsForPayment($payment);

        if ($this->landlordSubledger->expectsCreditsForPayment($payment)
            && ! $this->landlordSubledger->hasCreditsForPayment($payment)) {
            throw new RuntimeException(
                'Payment #'.$payment->id.' has allocations but no landlord subledger credits were posted.'
            );
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float} allocated, gross, remaining
     */
    private function paymentAmounts(PmPayment $payment, ?float $unallocatedAmount): array
    {
        $allocated = round((float) $payment->allocations
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed)
            ->sum('amount'), 2);
        $gross = round((float) $payment->amount, 2);
        $remaining = $unallocatedAmount !== null
            ? max(0.0, round((float) $unallocatedAmount, 2))
            : max(0.0, round($gross - $allocated, 2));

        return [$allocated, $gross, $remaining];
    }

    private function shouldPostSuspenseOnly(PmPayment $payment, float $remaining): bool
    {
        $activeAllocations = $payment->allocations
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed);

        return $activeAllocations->isEmpty() && $remaining > 0.0001;
    }

    private function shouldPostPaymentReceived(PmPayment $payment, float $gross): bool
    {
        $activeAllocations = $payment->allocations
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed);

        return $activeAllocations->isNotEmpty()
            || ((int) $payment->pm_tenant_id > 0 && $gross > 0);
    }

    private function hasPostedPaymentBatch(PmPayment $payment, string $eventType): bool
    {
        return AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', $eventType)
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
    }

    private function hasConflictingPaymentBatches(PmPayment $payment): bool
    {
        return $this->hasPostedPaymentBatch($payment, 'payment_received')
            && $this->hasPostedPaymentBatch($payment, 'payment_unmatched_suspense');
    }

    private function assertPaymentBatchInvariants(AccountingJournalBatch $batch, PmPayment $payment, string $eventType): void
    {
        $batch->loadMissing('lines');
        $this->assertBalancedBatch($batch);

        $lines = $batch->lines;
        $cashAccountId = $this->accountId(self::ACC_CASH_BANK);
        $cashDebits = $lines->filter(fn (AccountingJournalLine $line) => (int) $line->account_id === $cashAccountId)
            ->sum(fn (AccountingJournalLine $line) => (float) $line->debit);

        $gross = round((float) $payment->amount, 2);

        if ($eventType === 'payment_unmatched_suspense') {
            if (abs($cashDebits - $gross) > 0.02) {
                throw new RuntimeException('Suspense batch cash debit must equal payment amount.');
            }

            return;
        }

        if ($cashDebits <= 0) {
            throw new RuntimeException('Payment received batch must include a cash debit.');
        }

        if (abs($cashDebits - $gross) > 0.02) {
            throw new RuntimeException('Payment received batch cash debit must equal payment gross once.');
        }

        $cashLines = $lines->filter(fn (AccountingJournalLine $line) => (int) $line->account_id === $cashAccountId && (float) $line->debit > 0);
        if ($cashLines->count() !== 1) {
            throw new RuntimeException('Payment received batch must touch cash exactly once.');
        }

        $arAccountIds = array_values(array_filter([
            $this->accountId(self::ACC_AR),
            $this->accountId(self::ACC_UTILITY_AR),
        ]));

        $allocated = round((float) $payment->allocations
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed)
            ->sum('amount'), 2);

        if ($allocated > 0 && $arAccountIds !== []) {
            $arCredits = round((float) $lines
                ->filter(fn (AccountingJournalLine $line) => in_array((int) $line->account_id, $arAccountIds, true))
                ->sum(fn (AccountingJournalLine $line) => (float) $line->credit), 2);

            if (abs($arCredits - min($allocated, $gross)) > 0.02) {
                throw new RuntimeException('GL AR reduction must match operational allocations.');
            }
        }

        $clearingAccountId = $this->accountId(self::ACC_LANDLORD_CLEARING);
        if ($clearingAccountId !== null && $allocated > 0) {
            $clearingDebit = round((float) $lines
                ->filter(fn (AccountingJournalLine $line) => (int) $line->account_id === $clearingAccountId)
                ->sum(fn (AccountingJournalLine $line) => (float) $line->debit), 2);
            $arSettled = round(min($allocated, $gross), 2);

            if (abs($clearingDebit - $arSettled) > 0.02) {
                throw new RuntimeException('Landlord clearing debit must balance allocated AR settlement.');
            }
        }
    }

    private function assertBalancedBatch(AccountingJournalBatch $batch): void
    {
        $batch->loadMissing('lines');
        $debits = round((float) $batch->lines->sum('debit'), 2);
        $credits = round((float) $batch->lines->sum('credit'), 2);

        if (abs($debits - $credits) > 0.01) {
            throw new RuntimeException(sprintf(
                'Journal batch #%d is unbalanced (DR %s / CR %s).',
                $batch->id,
                number_format($debits, 2),
                number_format($credits, 2),
            ));
        }
    }

    private function reverseLandlordLedger(PmPayment $payment, ?int $actorId, ?string $reason): void
    {
        $this->landlordSubledger->reverseForPayment($payment, $actorId, $reason);
    }

    private function accountId(string $code): ?int
    {
        if ($this->accountIds === null) {
            $this->accountIds = AccountingChartAccount::query()
                ->whereIn('code', [
                    self::ACC_CASH_BANK,
                    self::ACC_AR,
                    self::ACC_UTILITY_AR,
                    self::ACC_LANDLORD_CLEARING,
                ])
                ->pluck('id', 'code')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->accountIds[$code] ?? null;
    }
}
