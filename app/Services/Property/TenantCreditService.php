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
                ->whereColumn('amount_paid', '<', 'amount')
                ->when($prioritizeInvoiceId, fn ($q) => $q->where('id', '!=', $prioritizeInvoiceId))
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
            $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
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

            PropertyAccountingPostingService::postTenantCreditRefund($tenantId, $amount, $txn->reference, $actor);

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

        $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
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

        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $creditPayment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        $invoice->amount_paid = (float) $invoice->amount_paid + $amount;
        $invoice->save();
        $invoice->refreshComputedStatus();

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

        PropertyAccountingPostingService::postTenantCreditApplied($invoice, $amount, (int) $txn->id, $actor);

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
