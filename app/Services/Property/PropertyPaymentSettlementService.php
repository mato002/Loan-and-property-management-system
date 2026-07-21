<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\User;
use App\Services\Property\InvoiceStateIntegrityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PropertyPaymentSettlementService
{
    public function fail(PmPayment $payment, ?string $externalRef, ?string $message, string $source): PmPayment
    {
        return DB::transaction(function () use ($payment, $externalRef, $message, $source) {
            $payment = $this->lockPayment($payment);

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['callback'] = [
                'source' => $source,
                'status' => 'failed',
                'message' => $message,
                'received_at' => now()->toIso8601String(),
            ];

            $payment->update([
                'status' => PmPayment::STATUS_FAILED,
                'external_ref' => $externalRef ?: $payment->external_ref,
                'meta' => $meta,
            ]);

            return $payment->fresh();
        });
    }

    public function complete(
        PmPayment $payment,
        ?string $externalRef,
        mixed $paidAt,
        ?string $message,
        string $source,
        ?float $paidAmount = null,
    ): PmPayment {
        return DB::transaction(function () use ($payment, $externalRef, $paidAt, $message, $source, $paidAmount) {
            $payment = $this->lockPayment($payment);

            if ($paidAmount !== null && $paidAmount > 0) {
                $payment->amount = $paidAmount;
            }

            $payment->update([
                'status' => PmPayment::STATUS_COMPLETED,
                'paid_at' => $paidAt ?: now(),
                'external_ref' => $externalRef ?: $payment->external_ref,
                'meta' => array_merge(is_array($payment->meta) ? $payment->meta : [], [
                    'callback' => [
                        'source' => $source,
                        'status' => 'success',
                        'message' => $message,
                        'amount' => $paidAmount,
                        'received_at' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            $scope = (string) data_get($payment->meta, 'bill_scope', 'all');
            $invoiceType = match (strtolower(trim($scope))) {
                'rent' => PmInvoice::TYPE_RENT,
                'water' => PmInvoice::TYPE_WATER,
                default => null,
            };

            $targetInvoiceId = (int) data_get($payment->meta, 'invoice_id', 0);
            if ($targetInvoiceId > 0) {
                $targetInvoice = PmInvoice::query()
                    ->where('pm_tenant_id', $payment->pm_tenant_id)
                    ->whereKey($targetInvoiceId)
                    ->first();
                if ($targetInvoice) {
                    $remaining = $this->allocatePaymentToSpecificInvoice($payment, $targetInvoice);
                } else {
                    $remaining = $this->allocatePaymentToOpenInvoices($payment, $invoiceType);
                }
            } else {
                $remaining = $this->allocatePaymentToOpenInvoices($payment, $invoiceType);
            }

            $payment->load('allocations.invoice.unit');
            $this->finalizeIdentifiedPayment($payment, null, $remaining);
            $this->repairTenantIfDriftDetected((int) $payment->pm_tenant_id);

            return $payment->fresh();
        });
    }

    /**
     * Record a completed payment allocated to one invoice (manual quick pay).
     */
    public function recordPaymentToInvoice(
        PmInvoice $invoice,
        float $amount,
        string $channel,
        ?string $externalRef,
        mixed $paidAt,
        ?User $actor = null,
        ?array $meta = null,
        ?int $agentUserId = null,
        bool $postAccounting = true,
    ): PmPayment {
        return DB::transaction(function () use ($invoice, $amount, $channel, $externalRef, $paidAt, $actor, $meta, $agentUserId, $postAccounting) {
            $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->syncAmountPaidFromAllocations();

            $amount = round(min($amount, $invoice->balanceFloat()), 2);
            if ($amount <= 0.0001) {
                throw new RuntimeException('Invoice has no open balance for this payment.');
            }

            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $invoice->pm_tenant_id,
                'channel' => $channel,
                'amount' => $amount,
                'external_ref' => $externalRef,
                'paid_at' => $paidAt ?: now(),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => $meta,
                'agent_user_id' => $agentUserId,
            ]);

            $this->createAllocation($payment, $invoice, $amount);

            if ($postAccounting) {
                $payment->load('allocations.invoice.unit');
                $this->finalizeIdentifiedPayment($payment, $actor, 0.0);
            }

            $this->repairTenantIfDriftDetected((int) $invoice->pm_tenant_id);

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Record advance payment: allocate oldest-first, remainder to tenant credit.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordAdvancePayment(array $data, ?User $actor = null): PmPayment
    {
        return DB::transaction(function () use ($data, $actor) {
            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'channel' => $data['channel'],
                'amount' => $data['amount'],
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => $data['meta'] ?? [
                    'source' => 'manual',
                    'payment_kind' => 'advance',
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $payment = $this->lockPayment($payment);
            $remaining = $this->allocatePaymentToOpenInvoices($payment);
            $this->finalizeIdentifiedPayment($payment, $actor, $remaining);
            $this->repairTenantIfDriftDetected((int) $payment->pm_tenant_id);

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Apply a completed payment to open invoices (oldest due first).
     *
     * @return float Unallocated remainder
     */
    public function allocatePaymentToOpenInvoices(PmPayment $payment, ?string $invoiceType = null): float
    {
        $payment = $this->lockPayment($payment);

        $remaining = round((float) $payment->amount, 2);
        if ($remaining <= 0.0001 || (int) $payment->pm_tenant_id <= 0) {
            return $remaining;
        }

        $openInvoices = $this->openInvoicesForPaymentQuery($payment, $invoiceType)
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0.0001) {
                break;
            }

            $invoiceRemaining = $invoice->balanceFloat();
            if ($invoiceRemaining <= 0.0001) {
                continue;
            }

            $allocation = round(min($remaining, $invoiceRemaining), 2);
            if ($allocation <= 0.0001) {
                continue;
            }

            $this->createAllocation($payment, $invoice, $allocation);
            $remaining = round($remaining - $allocation, 2);
        }

        return max(0.0, $remaining);
    }

    /**
     * Allocate to one invoice first, then spill to other open invoices oldest-first.
     *
     * @return float Unallocated remainder
     */
    public function allocatePaymentToSpecificInvoice(PmPayment $payment, PmInvoice $targetInvoice): float
    {
        $payment = $this->lockPayment($payment);
        $targetInvoice = PmInvoice::query()->whereKey($targetInvoice->id)->lockForUpdate()->firstOrFail();
        $targetInvoice->syncAmountPaidFromAllocations();

        $remaining = round((float) $payment->amount, 2);
        if ($remaining <= 0.0001) {
            return 0.0;
        }

        $targetRemaining = $targetInvoice->balanceFloat();
        if ($targetRemaining > 0.0001) {
            $allocation = round(min($remaining, $targetRemaining), 2);
            $this->createAllocation($payment, $targetInvoice, $allocation);
            $remaining = round($remaining - $allocation, 2);
        }

        if ($remaining <= 0.0001) {
            return 0.0;
        }

        $openInvoices = $this->openInvoicesForPaymentQuery($payment, null, (int) $targetInvoice->id)
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0.0001) {
                break;
            }

            $allocation = round(min($remaining, $invoice->balanceFloat()), 2);
            if ($allocation <= 0.0001) {
                continue;
            }

            $this->createAllocation($payment, $invoice, $allocation);
            $remaining = round($remaining - $allocation, 2);
        }

        return max(0.0, $remaining);
    }

    /**
     * Create one allocation row and derive invoice.amount_paid from allocations.
     */
    public function createAllocation(PmPayment $payment, PmInvoice $invoice, float $amount): PmPaymentAllocation
    {
        $payment = $this->lockPayment($payment);
        $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        $invoice->syncAmountPaidFromAllocations();

        $amount = round(min($amount, $invoice->balanceFloat()), 2);
        if ($amount <= 0.0001) {
            throw new RuntimeException('Invoice has no open balance for allocation.');
        }

        $allocation = PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        $invoice->syncAmountPaidFromAllocations();
        $this->assertInvoiceAllocationInvariant($invoice);
        app(InvoiceStateIntegrityService::class)->assertHealthy($invoice);

        return $allocation;
    }

    /**
     * Reverse active allocations and re-derive invoice balances from allocations.
     */
    public function reversePaymentAllocations(PmPayment $payment, ?int $actorId, ?string $reason): void
    {
        $payment = $this->lockPayment($payment);

        $allocations = PmPaymentAllocation::query()
            ->where('pm_payment_id', $payment->id)
            ->where(function ($q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->lockForUpdate()
            ->get();

        $invoiceIds = [];
        foreach ($allocations as $allocation) {
            $allocation->is_reversed = true;
            $allocation->reversed_by = $actorId;
            $allocation->reversed_at = now();
            $allocation->reversal_reason = $reason;
            $allocation->save();

            if ((int) $allocation->pm_invoice_id > 0) {
                $invoiceIds[] = (int) $allocation->pm_invoice_id;
            }
        }

        foreach (array_unique($invoiceIds) as $invoiceId) {
            $invoice = PmInvoice::query()->whereKey($invoiceId)->lockForUpdate()->first();
            if (! $invoice) {
                continue;
            }

            $invoice->syncAmountPaidFromAllocations();
            $this->assertInvoiceAllocationInvariant($invoice);
        }

        $this->repairTenantIfDriftDetected((int) $payment->pm_tenant_id);
    }

    /**
     * Post GL / tenant credit after allocations are already on the payment.
     */
    public function finalizeIdentifiedPayment(PmPayment $payment, ?User $actor = null, ?float $unallocatedAmount = null): void
    {
        app(PropertyAccountingFinalizeService::class)
            ->afterPaymentSettled($payment, $actor, $unallocatedAmount);
    }

    public function settlePending(
        int $paymentId,
        string $status,
        ?string $externalRef,
        mixed $paidAt,
        ?string $message,
        string $source,
        ?float $paidAmount = null,
    ): PmPayment {
        return DB::transaction(function () use ($paymentId, $status, $externalRef, $paidAt, $message, $source, $paidAmount) {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return $payment;
            }

            if ($status === 'failed') {
                return $this->fail($payment, $externalRef, $message, $source);
            }

            return $this->complete($payment, $externalRef, $paidAt, $message, $source, $paidAmount);
        });
    }

    /**
     * Ensure invoice.amount_paid matches non-reversed allocation totals.
     * Auto-sync first; trigger tenant repair if drift persists.
     */
    public function assertInvoiceAllocationInvariant(PmInvoice $invoice): void
    {
        $invoice->refresh();
        $allocated = $invoice->allocatedAmount();
        $amountPaid = round((float) $invoice->amount_paid, 2);

        if (abs($allocated - $amountPaid) <= 0.009) {
            app(InvoiceStateIntegrityService::class)->assertHealthy($invoice);

            return;
        }

        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();

        if (abs($invoice->allocatedAmount() - (float) $invoice->amount_paid) <= 0.009) {
            return;
        }

        Log::warning('Invoice allocation invariant drift detected', [
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'amount_paid' => (float) $invoice->amount_paid,
            'allocated_sum' => $invoice->allocatedAmount(),
        ]);

        $this->repairTenantIfDriftDetected((int) $invoice->pm_tenant_id);
    }

    public function repairTenantIfDriftDetected(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $drift = app(FinanceFirebreakService::class)->detectAllocationDrift($tenantId, 1);
        if ($drift->isEmpty()) {
            return false;
        }

        app(PropertyPaymentAllocationRepairService::class)->repairTenant($tenantId);

        return true;
    }

    private function lockPayment(PmPayment $payment): PmPayment
    {
        return PmPayment::query()->lockForUpdate()->findOrFail($payment->id);
    }

    private function openInvoicesForPaymentQuery(
        PmPayment $payment,
        ?string $invoiceType = null,
        ?int $excludeInvoiceId = null,
    ): Builder {
        return PmInvoice::query()
            ->openBillable()
            ->where('pm_tenant_id', $payment->pm_tenant_id)
            ->when($invoiceType !== null, fn (Builder $q) => $q->where('invoice_type', $invoiceType))
            ->when($excludeInvoiceId !== null, fn (Builder $q) => $q->where('id', '!=', $excludeInvoiceId))
            ->orderBy('due_date')
            ->orderBy('id');
    }
}
