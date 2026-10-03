<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\PmTenantCreditBalance;
use App\Models\PmTenantCreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantCreditService
{
    public function isEnabled(): bool
    {
        return Schema::hasTable('pm_tenant_credit_balances')
            && Schema::hasTable('pm_tenant_credit_transactions');
    }

    public function balanceForTenant(int $tenantId): float
    {
        if (! $this->isEnabled() || $tenantId <= 0) {
            return 0.0;
        }

        $row = PmTenantCreditBalance::query()
            ->where('pm_tenant_id', $tenantId)
            ->value('balance');

        return round(max(0.0, (float) $row), 2);
    }

    /**
     * Reverse tenant advance credit created from an overpayment when the
     * source payment is reversed. Throws if credit has already been applied.
     */
    public function reverseCreditFromPayment(PmPayment $payment, ?User $actor = null, ?string $reason = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $txnId = (int) data_get($payment->meta, 'tenant_credit_transaction_id', 0);
        if ($txnId <= 0) {
            return;
        }

        $created = PmTenantCreditTransaction::query()->find($txnId);
        if (! $created || $created->type !== PmTenantCreditTransaction::TYPE_CREDIT_CREATED) {
            return;
        }

        $reference = 'PAY-REV-'.(int) $payment->id;
        $alreadyReversed = PmTenantCreditTransaction::query()
            ->where('pm_tenant_id', (int) $created->pm_tenant_id)
            ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_REVERSED)
            ->where('reference', $reference)
            ->exists();
        if ($alreadyReversed) {
            return;
        }

        $creditAmount = round((float) $created->amount, 2);
        if ($creditAmount <= 0) {
            return;
        }

        $tenantId = (int) $created->pm_tenant_id;
        $appliedAfter = round((float) PmTenantCreditTransaction::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_APPLIED)
            ->where('id', '>', (int) $created->id)
            ->sum('amount'), 2);

        if ($appliedAfter > 0.01) {
            throw new RuntimeException(
                'Cannot reverse payment #'.$payment->id.': tenant credit from overpayment has been applied to invoices.'
            );
        }

        DB::transaction(function () use ($payment, $actor, $reason, $tenantId, $creditAmount, $reference) {
            $balance = $this->lockBalanceRow($tenantId);
            $available = round((float) $balance->balance, 2);
            if ($available + 0.01 < $creditAmount) {
                throw new RuntimeException(
                    'Cannot reverse payment #'.$payment->id.': tenant credit balance is insufficient.'
                );
            }

            PmTenantCreditTransaction::query()->create([
                'pm_tenant_id' => $tenantId,
                'pm_payment_id' => (int) $payment->id,
                'pm_invoice_id' => null,
                'type' => PmTenantCreditTransaction::TYPE_CREDIT_REVERSED,
                'amount' => $creditAmount,
                'reference' => $reference,
                'notes' => $reason ?: ('Reversed with payment #'.$payment->id),
                'application_mode' => PmTenantCreditTransaction::MODE_AUTO,
                'created_by' => $actor?->id,
            ]);

            $balance->balance = round(max(0.0, $available - $creditAmount), 2);
            $balance->save();
        });
    }

    /**
     * Keep the overpayment wallet in line with money still unallocated on that payment.
     * Deposit invoices imported later consume the leftover, but the credit row was left behind
     * and then shown as extra A/C balance even though the statement already nets to zero.
     */
    public function syncOverpaymentCreditToPaymentRemainder(PmPayment $payment, ?User $actor = null, bool $dryRun = false): float
    {
        if (! $this->isEnabled()) {
            return 0.0;
        }

        $txnId = (int) data_get($payment->meta, 'tenant_credit_transaction_id', 0);
        if ($txnId <= 0) {
            return 0.0;
        }

        $created = PmTenantCreditTransaction::query()->find($txnId);
        if (! $created || $created->type !== PmTenantCreditTransaction::TYPE_CREDIT_CREATED) {
            return 0.0;
        }

        $tenantId = (int) $created->pm_tenant_id;
        $createdAmount = round((float) $created->amount, 2);
        $alreadyReversed = round((float) PmTenantCreditTransaction::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('pm_payment_id', (int) $payment->id)
            ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_REVERSED)
            ->sum('amount'), 2);
        $creditStillOpen = round(max(0.0, $createdAmount - $alreadyReversed), 2);
        if ($creditStillOpen <= 0.009) {
            return 0.0;
        }

        $allocated = round((float) $payment->allocations()
            ->where(function ($q): void {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->sum('amount'), 2);
        $unallocated = round(max(0.0, (float) $payment->amount - $allocated), 2);
        $excess = round($creditStillOpen - $unallocated, 2);
        if ($excess <= 0.009) {
            return 0.0;
        }

        if ($dryRun) {
            return $excess;
        }

        return DB::transaction(function () use ($payment, $actor, $tenantId, $excess) {
            $balance = $this->lockBalanceRow($tenantId);
            $available = round((float) $balance->balance, 2);
            $toReverse = round(min($excess, $available), 2);
            if ($toReverse <= 0.009) {
                return 0.0;
            }

            PmTenantCreditTransaction::query()->create([
                'pm_tenant_id' => $tenantId,
                'pm_payment_id' => (int) $payment->id,
                'pm_invoice_id' => null,
                'type' => PmTenantCreditTransaction::TYPE_CREDIT_REVERSED,
                'amount' => $toReverse,
                'reference' => 'PAY-ALLOC-'.(int) $payment->id,
                'notes' => 'Cleared after leftover from payment #'.$payment->id.' was allocated to invoices',
                'application_mode' => PmTenantCreditTransaction::MODE_AUTO,
                'created_by' => $actor?->id,
            ]);

            $balance->balance = round(max(0.0, $available - $toReverse), 2);
            $balance->save();

            return $toReverse;
        });
    }

    /**
     * Reverse GL + operational credit application for tenant_credit channel payments.
     */
    public function reverseCreditApplicationPayment(PmPayment $payment, ?User $actor = null, ?string $reason = null): void
    {
        if (! $this->isEnabled() || (string) $payment->channel !== 'tenant_credit') {
            return;
        }

        $appliedTxn = PmTenantCreditTransaction::query()
            ->where('pm_payment_id', (int) $payment->id)
            ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_APPLIED)
            ->first();

        if (! $appliedTxn) {
            return;
        }

        PropertyAccountingPostingService::reverseTenantCreditApplied(
            (int) $appliedTxn->id,
            $actor,
            $reason ?: 'Tenant credit payment reversed'
        );
    }

    /**
     * Receipts minus invoices, excluding wallet re-applications.
     * Positive means the statement itself is in credit.
     */
    public function receiptSurplus(int $tenantId): float
    {
        if ($tenantId <= 0) {
            return 0.0;
        }

        $receipts = (float) PmPayment::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where(function ($query): void {
                $query->whereNull('channel')->orWhere('channel', '!=', 'tenant_credit');
            })
            ->sum('amount');
        $charges = (float) PmInvoice::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->sum('amount');

        return round($receipts - $charges, 2);
    }

    /**
     * Credit the statement can support. A tenant who still owes has none.
     */
    public function allowedCredit(int $tenantId): float
    {
        return round(max(0.0, $this->receiptSurplus($tenantId)), 2);
    }

    /**
     * Drop wallet balances and undo credit applications that the statement does not support.
     *
     * @return array{wallet_cleared: float, applications_undone: float, payments_undone: int}
     */
    public function alignUnsupportedCredit(int $tenantId, bool $dryRun = false): array
    {
        $empty = ['wallet_cleared' => 0.0, 'applications_undone' => 0.0, 'payments_undone' => 0];
        if (! $this->isEnabled() || $tenantId <= 0) {
            return $empty;
        }

        $allowed = $this->allowedCredit($tenantId);
        $wallet = $this->balanceForTenant($tenantId);
        $applications = PmPayment::query()
            ->where('pm_tenant_id', $tenantId)
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('channel', 'tenant_credit')
            ->orderByDesc('id')
            ->get();
        $applied = round((float) $applications->sum('amount'), 2);
        $excess = round($wallet + $applied - $allowed, 2);
        if ($excess <= 0.009) {
            return $empty;
        }

        $walletCleared = round(min($wallet, max(0.0, $wallet - $allowed)), 2);
        $toUndo = [];
        $applicationsUndone = 0.0;
        $remaining = round($excess - $walletCleared, 2);
        foreach ($applications as $payment) {
            if ($remaining <= 0.009) {
                break;
            }
            $amount = round((float) $payment->amount, 2);
            if ($amount <= 0.009 || $amount - $remaining > 0.009) {
                continue;
            }
            $toUndo[] = $payment;
            $applicationsUndone = round($applicationsUndone + $amount, 2);
            $remaining = round($remaining - $amount, 2);
        }

        if ($dryRun) {
            return [
                'wallet_cleared' => $walletCleared,
                'applications_undone' => $applicationsUndone,
                'payments_undone' => count($toUndo),
            ];
        }

        $reason = 'Cleared credit that is not on the statement. Receipts do not exceed invoices.';
        foreach ($toUndo as $payment) {
            $this->undoWalletApplication($payment, $reason);
        }

        if ($walletCleared > 0.009) {
            $this->reverseUnsupportedWallet($tenantId, $walletCleared, $reason);
        }

        return [
            'wallet_cleared' => $walletCleared,
            'applications_undone' => $applicationsUndone,
            'payments_undone' => count($toUndo),
        ];
    }

    /**
     * Record advance rent from an identified tenant overpayment.
     */
    public function createCreditFromOverpayment(PmPayment $payment, float $amount, ?User $actor = null): ?PmTenantCreditTransaction
    {
        if (! $this->isEnabled() || $amount <= 0.0001) {
            return null;
        }

        $tenantId = (int) $payment->pm_tenant_id;
        if ($tenantId <= 0) {
            return null;
        }

        $room = round($this->allowedCredit($tenantId) - $this->balanceForTenant($tenantId), 2);
        if ($room <= 0.009) {
            return null;
        }
        $amount = min(round($amount, 2), $room);

        return DB::transaction(function () use ($payment, $amount, $actor, $tenantId) {
            $balance = $this->lockBalanceRow($tenantId);
            $amount = round($amount, 2);

            $txn = PmTenantCreditTransaction::query()->create([
                'pm_tenant_id' => $tenantId,
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => null,
                'type' => PmTenantCreditTransaction::TYPE_CREDIT_CREATED,
                'amount' => $amount,
                'reference' => $payment->external_ref ?: ('PAY-'.$payment->id),
                'notes' => 'Advance balance from payment #'.$payment->id
                .((string) data_get($payment->meta, 'bill_scope') === 'water' ? ' (utility scope)' : ''),
                'application_mode' => PmTenantCreditTransaction::MODE_AUTO,
                'created_by' => $actor?->id,
            ]);

            $balance->balance = round((float) $balance->balance + $amount, 2);
            $balance->save();

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['tenant_credit_created'] = $amount;
            $meta['tenant_credit_transaction_id'] = $txn->id;
            $payment->update(['meta' => $meta]);

            return $txn;
        });
    }

    /**
     * Auto-apply available credit to open invoices (oldest due first).
     *
     * @return list<array{invoice_id:int,amount:float,transaction_id:int}>
     */
    public function autoApplyForTenant(int $tenantId, ?User $actor = null, ?int $prioritizeInvoiceId = null): array
    {
        if (! $this->isEnabled() || $tenantId <= 0) {
            return [];
        }

        return DB::transaction(function () use ($tenantId, $actor, $prioritizeInvoiceId) {
            $applied = [];
            $room = round($this->allowedCredit($tenantId), 2);
            if ($room <= 0.009) {
                return [];
            }

            if ($prioritizeInvoiceId) {
                $invoice = PmInvoice::query()
                    ->where('pm_tenant_id', $tenantId)
                    ->where('id', $prioritizeInvoiceId)
                    ->lockForUpdate()
                    ->first();
                if ($invoice && $room > 0.009) {
                    $row = $this->applyToInvoice($tenantId, $invoice, $actor, PmTenantCreditTransaction::MODE_AUTO, $room);
                    if ($row) {
                        $applied[] = $row;
                        $room = round($room - (float) $row['amount'], 2);
                    }
                }
            }

            $openInvoices = PmInvoice::query()
                ->where('pm_tenant_id', $tenantId)
                ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                ->when($prioritizeInvoiceId, fn ($q) => $q->where('id', '!=', $prioritizeInvoiceId))
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(fn (PmInvoice $invoice) => $invoice->syncAmountPaidFromAllocations())
                ->filter(fn (PmInvoice $invoice) => $invoice->balanceFloat() > 0.0001);

            foreach ($openInvoices as $invoice) {
                if ($room <= 0.009 || $this->balanceForTenant($tenantId) <= 0.0001) {
                    break;
                }
                $row = $this->applyToInvoice($tenantId, $invoice, $actor, PmTenantCreditTransaction::MODE_AUTO, $room);
                if ($row) {
                    $applied[] = $row;
                    $room = round($room - (float) $row['amount'], 2);
                }
            }

            return $applied;
        });
    }

    /**
     * Manually apply credit to a specific invoice.
     */
    public function applyToInvoiceManual(int $tenantId, PmInvoice $invoice, float $amount, ?User $actor, ?string $notes = null): array
    {
        if ((int) $invoice->pm_tenant_id !== $tenantId) {
            throw new RuntimeException('Invoice does not belong to this tenant.');
        }

        return DB::transaction(function () use ($tenantId, $invoice, $amount, $actor, $notes) {
            $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->syncAmountPaidFromAllocations();
            $invoiceRemaining = $invoice->balanceFloat();
            $amount = min(round($amount, 2), $invoiceRemaining, $this->balanceForTenant($tenantId));
            if ($amount <= 0.0001) {
                throw new RuntimeException('No credit available or invoice has no open balance.');
            }

            $row = $this->applyToInvoice($tenantId, $invoice, $actor, PmTenantCreditTransaction::MODE_MANUAL, $amount, $notes);
            if (! $row) {
                throw new RuntimeException('Unable to apply credit.');
            }

            return $row;
        });
    }

    public function refundCredit(int $tenantId, float $amount, ?User $actor, ?string $notes = null, ?string $reference = null): PmTenantCreditTransaction
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Tenant credit module is not available.');
        }

        return DB::transaction(function () use ($tenantId, $amount, $actor, $notes, $reference) {
            $balance = $this->lockBalanceRow($tenantId);
            $amount = round($amount, 2);
            $available = round((float) $balance->balance, 2);
            if ($amount <= 0.0001) {
                throw new RuntimeException('Refund amount must be greater than zero.');
            }
            if ($amount > $available + 0.0001) {
                throw new RuntimeException('Refund exceeds available tenant credit (KES '.number_format($available, 2).').');
            }

            $txn = PmTenantCreditTransaction::query()->create([
                'pm_tenant_id' => $tenantId,
                'pm_payment_id' => null,
                'pm_invoice_id' => null,
                'type' => PmTenantCreditTransaction::TYPE_CREDIT_REFUNDED,
                'amount' => $amount,
                'reference' => $reference ?: ('REFUND-'.now()->format('YmdHis')),
                'notes' => $notes ?: 'Tenant credit refunded',
                'application_mode' => PmTenantCreditTransaction::MODE_MANUAL,
                'created_by' => $actor?->id,
            ]);

            $balance->balance = round(max(0.0, $available - $amount), 2);
            $balance->save();

            app(PropertyAccountingFinalizeService::class)
                ->afterTenantCreditRefunded($tenantId, $amount, $txn->reference, $actor);

            return $txn;
        });
    }

    /**
     * @return array{invoice_id:int,amount:float,transaction_id:int}|null
     */
    private function applyToInvoice(
        int $tenantId,
        PmInvoice $invoice,
        ?User $actor,
        string $mode,
        ?float $requestedAmount = null,
        ?string $notes = null,
    ): ?array {
        $balance = $this->lockBalanceRow($tenantId);
        $available = round((float) $balance->balance, 2);
        if ($available <= 0.0001) {
            return null;
        }

        $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
        if (! $invoice || (int) $invoice->pm_tenant_id !== $tenantId) {
            return null;
        }

        $invoice->syncAmountPaidFromAllocations();
        $invoiceRemaining = $invoice->balanceFloat();
        if ($invoiceRemaining <= 0.0001) {
            return null;
        }

        $amount = $requestedAmount !== null
            ? min(round($requestedAmount, 2), $invoiceRemaining, $available)
            : min($available, $invoiceRemaining);
        $amount = round($amount, 2);
        if ($amount <= 0.0001) {
            return null;
        }

        $creditPayment = PmPayment::query()->create([
            'pm_tenant_id' => $tenantId,
            'channel' => 'tenant_credit',
            'amount' => $amount,
            'external_ref' => 'CREDIT-APP-'.now()->format('YmdHis').'-'.$invoice->id,
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
            'meta' => [
                'source' => 'tenant_credit',
                'application_mode' => $mode,
                'invoice_id' => $invoice->id,
            ],
        ]);

        app(PropertyPaymentSettlementService::class)->createAllocation($creditPayment, $invoice, $amount);

        $txn = PmTenantCreditTransaction::query()->create([
            'pm_tenant_id' => $tenantId,
            'pm_payment_id' => $creditPayment->id,
            'pm_invoice_id' => $invoice->id,
            'type' => PmTenantCreditTransaction::TYPE_CREDIT_APPLIED,
            'amount' => $amount,
            'reference' => $creditPayment->external_ref,
            'notes' => $notes ?: ('Applied to '.$invoice->invoice_no),
            'application_mode' => $mode,
            'created_by' => $actor?->id,
        ]);

        $balance->balance = round(max(0.0, $available - $amount), 2);
        $balance->save();

        app(PropertyAccountingFinalizeService::class)
            ->afterTenantCreditApplied($invoice, $amount, (int) $txn->id, $actor);

        return [
            'invoice_id' => (int) $invoice->id,
            'amount' => $amount,
            'transaction_id' => (int) $txn->id,
        ];
    }

    private function reverseUnsupportedWallet(int $tenantId, float $amount, string $reason): void
    {
        DB::transaction(function () use ($tenantId, $amount, $reason): void {
            $balance = $this->lockBalanceRow($tenantId);
            $available = round((float) $balance->balance, 2);
            $toReverse = round(min($amount, $available), 2);
            if ($toReverse <= 0.009) {
                return;
            }

            PmTenantCreditTransaction::query()->create([
                'pm_tenant_id' => $tenantId,
                'pm_payment_id' => null,
                'pm_invoice_id' => null,
                'type' => PmTenantCreditTransaction::TYPE_CREDIT_REVERSED,
                'amount' => $toReverse,
                'reference' => 'CREDIT-ALIGN-'.$tenantId,
                'notes' => $reason,
                'application_mode' => PmTenantCreditTransaction::MODE_AUTO,
                'created_by' => null,
            ]);

            $balance->balance = round(max(0.0, $available - $toReverse), 2);
            $balance->save();
        });
    }

    private function undoWalletApplication(PmPayment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason): void {
            $payment = PmPayment::query()->lockForUpdate()->find($payment->id);
            if (! $payment || $payment->status !== PmPayment::STATUS_COMPLETED || (string) $payment->channel !== 'tenant_credit') {
                return;
            }

            $allocations = PmPaymentAllocation::query()
                ->where('pm_payment_id', $payment->id)
                ->where(function ($query): void {
                    $query->whereNull('is_reversed')->orWhere('is_reversed', false);
                })
                ->lockForUpdate()
                ->get();

            $invoiceIds = [];
            foreach ($allocations as $allocation) {
                $allocation->is_reversed = true;
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
                $invoice->refreshComputedStatus();
            }

            $this->reverseCreditApplicationPayment($payment, null, $reason);

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['reversal'] = [
                'reason' => $reason,
                'reversed_at' => now()->toIso8601String(),
                'cleanup' => 'unsupported_tenant_credit',
            ];
            $payment->meta = $meta;
            $payment->status = PmPayment::STATUS_FAILED;
            if (Schema::hasColumn('pm_payments', 'reversal_status')) {
                $payment->reversal_status = PmPayment::REVERSAL_STATUS_REVERSED;
            }
            $payment->save();
        });
    }

    private function lockBalanceRow(int $tenantId): PmTenantCreditBalance
    {
        $balance = PmTenantCreditBalance::query()
            ->where('pm_tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        return PmTenantCreditBalance::query()->create([
            'pm_tenant_id' => $tenantId,
            'balance' => 0,
        ]);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, PmTenantCreditTransaction>
     */
    public function ledgerForTenant(int $tenantId, int $perPage = 25)
    {
        return PmTenantCreditTransaction::query()
            ->with(['invoice', 'payment', 'creator'])
            ->where('pm_tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
