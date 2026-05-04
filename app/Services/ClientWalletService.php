<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingWalletSlotSetting;
use App\Models\ClientWallet;
use App\Models\ClientWalletTransaction;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ClientWalletService
{
    public function ensureWallet(LoanClient $client, ?int $actorUserId = null): ClientWallet
    {
        if ($client->kind !== LoanClient::KIND_CLIENT) {
            throw new RuntimeException('Only clients may have a wallet.');
        }

        return ClientWallet::query()->firstOrCreate(
            ['loan_client_id' => $client->id],
            [
                'balance' => 0,
                'currency' => 'KES',
                'status' => ClientWallet::STATUS_ACTIVE,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]
        );
    }

    /**
     * After a payment is processed and loan balances updated, sync client wallet sub-ledger (idempotent).
     */
    public function syncPostedPaymentWalletEffects(LoanBookPayment $payment): void
    {
        if ($payment->status !== LoanBookPayment::STATUS_PROCESSED) {
            return;
        }
        $payment->loadMissing('loan.loanClient');
        $client = $payment->loan?->loanClient;
        if (! $client || $client->kind !== LoanClient::KIND_CLIENT) {
            return;
        }

        $this->syncOverpaymentCreditFromPayment($payment, $client);
        $this->syncWalletFundedRepaymentDebit($payment, $client);
    }

    public function syncOverpaymentCreditFromPayment(LoanBookPayment $payment, ?LoanClient $client = null): void
    {
        if ((bool) $payment->funded_from_wallet) {
            return;
        }
        if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
            return;
        }
        if ($payment->status !== LoanBookPayment::STATUS_PROCESSED) {
            return;
        }

        $client ??= $payment->loan?->loanClient;
        if (! $client || $client->kind !== LoanClient::KIND_CLIENT) {
            return;
        }

        $payment->loadMissing('allocations');
        $over = 0.0;
        foreach ($payment->allocations as $row) {
            if (strtolower(trim((string) $row->component)) === 'overpayment') {
                $over += (float) $row->amount;
            }
        }
        $over = round($over, 2);
        if ($over <= 0.0) {
            return;
        }

        if (ClientWalletTransaction::query()
            ->where('loan_book_payment_id', $payment->id)
            ->where('source_type', ClientWalletTransaction::SOURCE_OVERPAYMENT)
            ->exists()) {
            return;
        }

        $ref = (string) ($payment->reference ?? 'PAY-'.$payment->id);
        $this->creditWallet($client, $over, ClientWalletTransaction::SOURCE_OVERPAYMENT, [
            'reference' => $ref,
            'description' => 'Overpayment from payment '.$ref,
            'loan_book_payment_id' => $payment->id,
            'loan_book_loan_id' => $payment->loan_book_loan_id,
            'accounting_journal_entry_id' => $payment->accounting_journal_entry_id,
            'created_by' => $payment->posted_by,
        ]);
    }

    public function syncWalletFundedRepaymentDebit(LoanBookPayment $payment, ?LoanClient $client = null): void
    {
        if (! (bool) $payment->funded_from_wallet) {
            return;
        }
        if ($payment->status !== LoanBookPayment::STATUS_PROCESSED) {
            return;
        }

        $client ??= $payment->loan?->loanClient;
        if (! $client || $client->kind !== LoanClient::KIND_CLIENT) {
            return;
        }

        if (ClientWalletTransaction::query()
            ->where('loan_book_payment_id', $payment->id)
            ->where('source_type', ClientWalletTransaction::SOURCE_WALLET_TO_LOAN)
            ->exists()) {
            return;
        }

        $payment->loadMissing('allocations');
        $allocated = 0.0;
        foreach ($payment->allocations as $row) {
            $c = strtolower(trim((string) $row->component));
            if (in_array($c, ['principal', 'interest', 'fees', 'penalty'], true)) {
                $allocated += (float) $row->amount;
            }
        }
        $allocated = round($allocated, 2);
        if ($allocated <= 0.0) {
            return;
        }

        $ref = (string) ($payment->reference ?? 'PAY-'.$payment->id);
        $this->debitWallet($client, $allocated, ClientWalletTransaction::SOURCE_WALLET_TO_LOAN, [
            'reference' => $ref,
            'description' => 'Wallet applied to loan repayment '.$ref,
            'loan_book_payment_id' => $payment->id,
            'loan_book_loan_id' => $payment->loan_book_loan_id,
            'accounting_journal_entry_id' => $payment->accounting_journal_entry_id,
            'created_by' => $payment->posted_by,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function creditWallet(LoanClient $client, float $amount, string $sourceType, array $context = []): ClientWalletTransaction
    {
        return $this->applyMovement($client, round(abs($amount), 2), $sourceType, ClientWalletTransaction::TYPE_CREDIT, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debitWallet(LoanClient $client, float $amount, string $sourceType, array $context = []): ClientWalletTransaction
    {
        return $this->applyMovement($client, round(abs($amount), 2), $sourceType, ClientWalletTransaction::TYPE_DEBIT, $context);
    }

    public function setWalletStatus(ClientWallet $wallet, string $status, ?int $actorUserId): void
    {
        if (! in_array($status, [ClientWallet::STATUS_ACTIVE, ClientWallet::STATUS_FROZEN, ClientWallet::STATUS_CLOSED], true)) {
            throw new RuntimeException('Invalid wallet status.');
        }
        $wallet->update([
            'status' => $status,
            'updated_by' => $actorUserId,
        ]);
    }

    public function reconcileWalletTotalVsGlWalletLiability(): array
    {
        if (! Schema::hasTable('client_wallets')) {
            return ['sum_wallets' => null, 'gl_balance' => null, 'mismatch' => false, 'message' => null];
        }

        $sumWallets = (float) ClientWallet::query()->sum('balance');
        $glBalance = null;
        $slot = AccountingWalletSlotSetting::query()->where('slot_key', 'client_wallet_liability_account')->first();
        if ($slot?->accounting_chart_account_id) {
            $glBalance = (float) (AccountingChartAccount::query()
                ->whereKey((int) $slot->accounting_chart_account_id)
                ->value('current_balance') ?? 0);
        }

        $mismatch = $glBalance !== null && round(abs($sumWallets - $glBalance), 2) > 0.01;
        $message = null;
        if ($mismatch) {
            $message = 'Sum of client wallet balances does not match the mapped client wallet liability GL account balance. Sub-ledger and GL may diverge until fully reconciled.';
        }
        if ($glBalance === null) {
            $message = ($message ? $message.' ' : '').'Client wallet liability is not mapped on the chart; GL comparison skipped.';
        }

        return [
            'sum_wallets' => $sumWallets,
            'gl_balance' => $glBalance,
            'mismatch' => $mismatch,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyMovement(LoanClient $client, float $amount, string $sourceType, string $txType, array $context): ClientWalletTransaction
    {
        if ($amount <= 0.0) {
            throw new RuntimeException('Wallet movement amount must be greater than zero.');
        }

        return DB::transaction(function () use ($client, $amount, $sourceType, $txType, $context): ClientWalletTransaction {
            $wallet = ClientWallet::query()
                ->where('loan_client_id', $client->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $this->ensureWallet($client, isset($context['created_by']) ? (int) $context['created_by'] : null);
                $wallet = ClientWallet::query()
                    ->where('loan_client_id', $client->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($wallet->status === ClientWallet::STATUS_CLOSED) {
                throw new RuntimeException('Wallet is closed.');
            }

            if ($txType === ClientWalletTransaction::TYPE_DEBIT && $wallet->status !== ClientWallet::STATUS_ACTIVE) {
                throw new RuntimeException('Wallet must be active to debit.');
            }

            $current = round((float) $wallet->balance, 2);
            $delta = $txType === ClientWalletTransaction::TYPE_CREDIT ? $amount : -$amount;
            $newBalance = round($current + $delta, 2);
            if ($newBalance < 0.0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $actor = isset($context['created_by']) ? (int) $context['created_by'] : null;
            $row = ClientWalletTransaction::query()->create([
                'client_wallet_id' => $wallet->id,
                'loan_client_id' => $client->id,
                'transaction_type' => $txType,
                'source_type' => $sourceType,
                'amount' => $amount,
                'running_balance' => $newBalance,
                'reference' => $context['reference'] ?? null,
                'description' => $context['description'] ?? null,
                'loan_book_payment_id' => $context['loan_book_payment_id'] ?? null,
                'loan_book_loan_id' => $context['loan_book_loan_id'] ?? null,
                'accounting_journal_entry_id' => $context['accounting_journal_entry_id'] ?? null,
                'created_by' => $actor ?: null,
                'approved_by' => $context['approved_by'] ?? null,
                'approved_at' => $context['approved_at'] ?? null,
            ]);

            $wallet->update([
                'balance' => $newBalance,
                'updated_by' => $actor ?: null,
            ]);

            if ($sourceType === ClientWalletTransaction::SOURCE_ADJUSTMENT) {
                $ref = (string) ($context['reference'] ?? 'WADJ-'.$row->id);
                $desc = (string) ($context['description'] ?? 'Wallet adjustment');
                $entry = app(LoanBookGlPostingService::class)->postWalletAdjustment(
                    $amount,
                    $txType,
                    $ref,
                    $desc,
                    $actor,
                    $context['entry_date'] ?? null
                );
                $row->update(['accounting_journal_entry_id' => $entry->id]);
            }

            return $row;
        });
    }
}
