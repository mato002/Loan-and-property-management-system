<?php

namespace App\Services\Property;

use App\Jobs\SendPaymentReceiptJob;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\User;
use App\Services\Property\InvoiceStateIntegrityService;
use Carbon\Carbon;
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
            $this->reconcileTenantCreditQuietly((int) $payment->pm_tenant_id);
            try {
                $this->finalizeIdentifiedPayment($payment, null, $remaining);
            } catch (\Throwable $e) {
                Log::error('Payment settled but accounting finalize failed', [
                    'pm_payment_id' => (int) $payment->id,
                    'pm_tenant_id' => (int) $payment->pm_tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
            try {
                $this->repairTenantIfDriftDetected((int) $payment->pm_tenant_id);
            } catch (\Throwable $e) {
                Log::warning('Payment repair skipped after settlement', [
                    'pm_payment_id' => (int) $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $fresh = $payment->fresh();
            $paymentId = (int) ($fresh?->id ?? 0);
            $skipNotification = (bool) data_get($payment->meta, 'skip_notification');
            if ($paymentId > 0 && ! $skipNotification) {
                DB::afterCommit(function () use ($paymentId) {
                    try {
                        SendPaymentReceiptJob::dispatch($paymentId);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to queue payment receipt', [
                            'pm_payment_id' => $paymentId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            return $fresh;
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

            app(PropertyHrWorkflowService::class)->logPaymentRecorded(
                (int) $payment->id,
                (int) $invoice->pm_tenant_id,
                $amount,
                (int) $invoice->id,
                $actor,
            );

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Manual rent receipt: one amount received, applied to the invoice lines the cashier entered.
     * Anything not applied to an invoice is held as tenant credit (on account).
     *
     * @param  array{
     *     pm_tenant_id: int,
     *     amount: float|int|string,
     *     channel: string,
     *     external_ref?: string|null,
     *     record_date?: string|null,
     *     banking_date?: string|null,
     *     payer_bank?: string|null,
     *     memo?: string|null,
     *     receipt_to?: string|null,
     *     bank_account_id?: int|null,
     *     vat_mode?: string|null,
     *     property_id?: int|null,
     *     skip_notification?: bool,
     *     allocations?: array<int|string, float|int|string>,
     *     agent_user_id?: int|null
     * }  $data
     */
    public function recordManualRentReceipt(array $data, ?User $actor = null): PmPayment
    {
        return DB::transaction(function () use ($data, $actor) {
            $tenantId = (int) $data['pm_tenant_id'];
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0.0001) {
                throw new RuntimeException('Enter the amount received.');
            }

            $lines = [];
            foreach ((array) ($data['allocations'] ?? []) as $invoiceId => $lineAmount) {
                $lineAmount = round((float) $lineAmount, 2);
                if ($lineAmount <= 0.0001) {
                    continue;
                }
                $lines[(int) $invoiceId] = $lineAmount;
            }

            $lineSum = round(array_sum($lines), 2);
            if ($lineSum - $amount > 0.009) {
                throw new RuntimeException('Invoice payments are higher than the amount received.');
            }

            $paidAt = Carbon::parse((string) ($data['banking_date'] ?: $data['record_date'] ?: now()->toDateString()))->startOfDay();

            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $tenantId,
                'channel' => (string) $data['channel'],
                'amount' => $amount,
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $paidAt,
                'status' => PmPayment::STATUS_COMPLETED,
                'agent_user_id' => ($data['agent_user_id'] ?? null) ?: null,
                'meta' => [
                    'source' => 'manual_rent_receipt',
                    'record_date' => (string) ($data['record_date'] ?? ''),
                    'banking_date' => (string) ($data['banking_date'] ?? ''),
                    'payer_bank' => (string) ($data['payer_bank'] ?? ''),
                    'memo' => (string) ($data['memo'] ?? ''),
                    'receipt_to' => (string) ($data['receipt_to'] ?? 'general_ledger'),
                    'bank_account_id' => (int) ($data['bank_account_id'] ?? 0) ?: null,
                    'vat_mode' => (string) ($data['vat_mode'] ?? 'inclusive'),
                    'property_id' => (int) ($data['property_id'] ?? 0) ?: null,
                    'skip_notification' => (bool) ($data['skip_notification'] ?? true),
                ],
            ]);

            foreach ($lines as $invoiceId => $lineAmount) {
                $invoice = PmInvoice::query()
                    ->whereKey($invoiceId)
                    ->where('pm_tenant_id', $tenantId)
                    ->first();
                if (! $invoice) {
                    throw new RuntimeException('One of the invoices does not belong to this tenant.');
                }
                if ($lineAmount - $invoice->balanceFloat() > 0.009) {
                    throw new RuntimeException('Payment on '.$invoice->invoice_no.' is higher than the amount due.');
                }
                $this->createAllocation($payment, $invoice, $lineAmount);
            }

            $allocated = round((float) PmPaymentAllocation::query()
                ->where('pm_payment_id', $payment->id)
                ->sum('amount'), 2);
            $remaining = round($amount - $allocated, 2);

            $payment->load('allocations.invoice.unit');
            $this->finalizeIdentifiedPayment($payment, $actor, $remaining);
            $this->repairTenantIfDriftDetected($tenantId);

            $fresh = $payment->fresh(['allocations']);
            if ($fresh && ! ($data['skip_notification'] ?? true)) {
                $paymentId = (int) $fresh->id;
                DB::afterCommit(function () use ($paymentId) {
                    try {
                        SendPaymentReceiptJob::dispatch($paymentId);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to queue payment receipt', [
                            'pm_payment_id' => $paymentId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            return $fresh ?? $payment;
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

        $alreadyAllocated = round((float) PmPaymentAllocation::query()
            ->where('pm_payment_id', $payment->id)
            ->where(function ($q): void {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->sum('amount'), 2);
        $remaining = round((float) $payment->amount - $alreadyAllocated, 2);
        if ($remaining <= 0.0001 || (int) $payment->pm_tenant_id <= 0) {
            app(TenantCreditService::class)->syncOverpaymentCreditToPaymentRemainder($payment);

            return max(0.0, $remaining);
        }

        $openInvoices = $this->openInvoicesForPaymentQuery($payment, $invoiceType)
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0.0001) {
                break;
            }

            $invoiceRemaining = $invoice->balanceFloat();
            if ($invoiceRemaining <= 0.0001 || $this->receiptMissesInvoicePeriod($payment, $invoice)) {
                continue;
            }

            $allocation = round(min($remaining, $invoiceRemaining), 2);
            if ($allocation <= 0.0001) {
                continue;
            }

            $this->createAllocation($payment, $invoice, $allocation);
            $remaining = round($remaining - $allocation, 2);
        }

        app(TenantCreditService::class)->syncOverpaymentCreditToPaymentRemainder($payment);

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

        $alreadyAllocated = round((float) PmPaymentAllocation::query()
            ->where('pm_payment_id', $payment->id)
            ->where(function ($q): void {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->sum('amount'), 2);
        $remaining = round((float) $payment->amount - $alreadyAllocated, 2);
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

            if ($this->receiptMissesInvoicePeriod($payment, $invoice)) {
                continue;
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
     * Credit bookkeeping must not roll back a payment that has already been received.
     */
    private function reconcileTenantCreditQuietly(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        try {
            app(TenantCreditService::class)->reconcileBalanceToStatement($tenantId);
        } catch (\Throwable $e) {
            Log::error('Tenant credit reconcile skipped after payment settlement', [
                'pm_tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create one allocation row and derive invoice.amount_paid from allocations.
     */
    public function createAllocation(PmPayment $payment, PmInvoice $invoice, float $amount, bool $syncTenantCredit = true): PmPaymentAllocation
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

        if ($syncTenantCredit && (string) $payment->channel !== 'tenant_credit') {
            try {
                app(TenantCreditService::class)->syncOverpaymentCreditToPaymentRemainder($payment);
            } catch (\Throwable $e) {
                Log::warning('Overpayment credit sync skipped during allocation', [
                    'pm_payment_id' => (int) $payment->id,
                    'pm_invoice_id' => (int) $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allocation;
    }

    /**
     * Undo allocations where a receipt names its months and the invoice is for a different month.
     * A January receipt must not mark November garbage as paid.
     *
     * @return array{allocations: int, amount: float}
     */
    public function releaseCrossPeriodAllocations(bool $dryRun = false): array
    {
        $count = 0;
        $amount = 0.0;
        $reason = 'Receipt names a different month, so this charge is not paid by it.';
        $invoiceIds = [];

        $rows = PmPaymentAllocation::query()
            ->from('pm_payment_allocations as a')
            ->join('pm_payments as p', 'p.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->where(function ($query): void {
                $query->whereNull('a.is_reversed')->orWhere('a.is_reversed', false);
            })
            ->where('p.status', PmPayment::STATUS_COMPLETED)
            ->where(function ($query): void {
                $query->whereNull('p.channel')->orWhere('p.channel', '!=', 'tenant_credit');
            })
            ->whereNotNull('i.billing_period')
            ->where('i.billing_period', '!=', '')
            ->get([
                'a.id',
                'a.amount',
                'a.pm_invoice_id',
                'i.billing_period',
                'p.meta',
            ]);

        foreach ($rows as $row) {
            $payment = new PmPayment(['meta' => $row->meta]);
            $invoice = new PmInvoice(['billing_period' => $row->billing_period]);
            if (! $this->receiptMissesInvoicePeriod($payment, $invoice)) {
                continue;
            }

            $count++;
            $amount = round($amount + (float) $row->amount, 2);
            if ($dryRun) {
                continue;
            }

            PmPaymentAllocation::query()->whereKey($row->id)->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
            $invoiceIds[(int) $row->pm_invoice_id] = true;
        }

        if (! $dryRun) {
            foreach (array_keys($invoiceIds) as $invoiceId) {
                $invoice = PmInvoice::query()->find($invoiceId);
                $invoice?->syncAmountPaidFromAllocations();
            }
        }

        return ['allocations' => $count, 'amount' => $amount];
    }

    /**
     * A receipt that names January must not be treated as payment for November.
     * Prepayments, and receipts with no named month, are left alone.
     */
    public function receiptMissesInvoicePeriod(PmPayment $payment, PmInvoice $invoice): bool
    {
        $text = trim((string) data_get($payment->meta, 'particulars', ''));
        if ($text === '' || preg_match('/prepay/i', $text) === 1) {
            return false;
        }

        $named = $this->namedBillingPeriods($text);
        if ($named === []) {
            return false;
        }

        $period = trim((string) ($invoice->billing_period ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            return false;
        }

        return ! in_array($period, $named, true);
    }

    /**
     * @return list<string>
     */
    private function namedBillingPeriods(string $text): array
    {
        $months = [
            'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
            'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
            'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09', 'sept' => '09',
            'oct' => '10', 'nov' => '11', 'dec' => '12',
        ];
        $found = [];
        if (preg_match_all('/\b([A-Za-z]+)\s*\/\s*(\d{4})\b/', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $month = $months[strtolower($match[1])] ?? null;
                if ($month !== null) {
                    $found[$match[2].'-'.$month] = true;
                }
            }
        }

        return array_keys($found);
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
