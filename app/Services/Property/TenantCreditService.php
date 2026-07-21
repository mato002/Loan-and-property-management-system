<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
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

            if ($prioritizeInvoiceId) {
                $invoice = PmInvoice::query()
                    ->where('pm_tenant_id', $tenantId)
                    ->where('id', $prioritizeInvoiceId)
                    ->lockForUpdate()
                    ->first();
                if ($invoice) {
                    $row = $this->applyToInvoice($tenantId, $invoice, $actor, PmTenantCreditTransaction::MODE_AUTO);
                    if ($row) {
                        $applied[] = $row;
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
                if ($this->balanceForTenant($tenantId) <= 0.0001) {
                    break;
                }
                $row = $this->applyToInvoice($tenantId, $invoice, $actor, PmTenantCreditTransaction::MODE_AUTO);
                if ($row) {
                    $applied[] = $row;
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
