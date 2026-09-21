<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\MpesaPlatformTransaction;
use App\Models\PropertyPortalSetting;
use App\Services\ClientWalletService;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\LoanBookGlPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LoanStkRepaymentService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
    ) {}

    /**
     * @return array{ok:bool, message:string, platform_transaction_id?:int, checkout_request_id?:string}
     */
    public function initiate(LoanBookLoan $loan, float $amount, string $phone, ?int $userId = null): array
    {
        if (! $this->daraja->isConfigured()) {
            $missing = implode(', ', $this->daraja->missingConfigKeys());

            return ['ok' => false, 'message' => 'Daraja is not configured (missing: '.$missing.').'];
        }

        if (! $loan->acceptsPostedLoanCollections()) {
            return ['ok' => false, 'message' => 'This facility is not ready for STK repayments yet.'];
        }

        $amount = round(abs($amount), 2);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Amount must be at least 1.'];
        }

        $msisdn = $this->daraja->normalizeMsisdn($phone);
        if ($msisdn === '') {
            return ['ok' => false, 'message' => 'Could not normalize M-Pesa phone number.'];
        }

        $tx = MpesaPlatformTransaction::query()->create([
            'reference' => 'LN-STK-'.$loan->id.'-'.now()->format('YmdHis'),
            'amount' => $amount,
            'channel' => 'stk_push',
            'status' => 'pending',
            'notes' => 'Loan STK repayment for '.$loan->loan_number,
            'meta' => [
                'purpose' => 'loan_repayment',
                'loan_book_loan_id' => $loan->id,
                'phone' => $msisdn,
                'requested_by' => $userId,
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        $accountRef = $this->accountReference($loan);
        $init = $this->daraja->stkPush([
            'Amount' => (int) round($amount),
            'PartyA' => $msisdn,
            'PartyB' => (string) config('services.mpesa.stk_shortcode'),
            'PhoneNumber' => $msisdn,
            'AccountReference' => $accountRef,
            'TransactionDesc' => 'Loan repayment '.$loan->loan_number,
        ]);

        $body = is_array($init['body'] ?? null) ? $init['body'] : [];
        $checkout = (string) ($body['CheckoutRequestID'] ?? '');
        $merchant = (string) ($body['MerchantRequestID'] ?? '');
        $meta = is_array($tx->meta) ? $tx->meta : [];
        $meta['daraja'] = [
            'checkout_request_id' => $checkout,
            'merchant_request_id' => $merchant,
            'initiated_at' => now()->toIso8601String(),
            'response' => $body,
        ];

        if (($init['ok'] ?? false) !== true) {
            $tx->update([
                'status' => 'failed',
                'result_desc' => (string) ($init['message'] ?? 'STK initiation failed'),
                'meta' => $meta,
            ]);

            return [
                'ok' => false,
                'message' => (string) ($init['message'] ?? 'STK initiation failed'),
                'platform_transaction_id' => (int) $tx->id,
            ];
        }

        $tx->update([
            'status' => 'pending',
            'conversation_id' => $checkout !== '' ? $checkout : null,
            'originator_conversation_id' => $merchant !== '' ? $merchant : null,
            'meta' => $meta,
        ]);

        return [
            'ok' => true,
            'message' => (string) ($init['message'] ?? 'STK push sent. Ask the borrower to enter their M-Pesa PIN.'),
            'platform_transaction_id' => (int) $tx->id,
            'checkout_request_id' => $checkout,
        ];
    }

    /**
     * Settle a loan STK callback into an unposted (or auto-posted) LoanBookPayment.
     *
     * @param  array<string, mixed>  $metaMap
     */
    public function applyStkCallback(
        string $checkoutRequestId,
        string $merchantRequestId,
        int $resultCode,
        string $resultDesc,
        array $metaMap,
        ?string $receipt,
        ?float $paidAmount,
    ): bool {
        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'stk_push')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.purpose')) = 'loan_repayment'")
            ->where(function ($q) use ($checkoutRequestId) {
                $q->where('conversation_id', $checkoutRequestId)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.daraja.checkout_request_id')) = ?", [$checkoutRequestId]);
            })
            ->orderByDesc('id')
            ->first();

        if (! $tx) {
            return false;
        }

        DB::transaction(function () use (
            $tx,
            $checkoutRequestId,
            $merchantRequestId,
            $resultCode,
            $resultDesc,
            $metaMap,
            $receipt,
            $paidAmount,
        ): void {
            $locked = MpesaPlatformTransaction::query()->lockForUpdate()->find($tx->id);
            if (! $locked) {
                return;
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $darajaMeta = is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [];
            $meta['daraja'] = array_merge($darajaMeta, [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'callback_metadata' => $metaMap,
                'receipt' => $receipt,
                'received_at' => now()->toIso8601String(),
            ]);

            if ($resultCode !== 0) {
                $locked->update([
                    'status' => 'failed',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc !== '' ? $resultDesc : null,
                    'meta' => $meta,
                ]);

                return;
            }

            if (isset($meta['loan_book_payment_id'])) {
                $locked->update([
                    'status' => 'completed',
                    'result_code' => 0,
                    'result_desc' => $resultDesc !== '' ? $resultDesc : 'Success',
                    'transaction_id' => $receipt ?: $locked->transaction_id,
                    'amount' => $paidAmount !== null && $paidAmount > 0 ? $paidAmount : $locked->amount,
                    'meta' => $meta,
                ]);

                return;
            }

            $loanId = (int) ($meta['loan_book_loan_id'] ?? 0);
            $loan = $loanId > 0 ? LoanBookLoan::query()->find($loanId) : null;
            $amount = $paidAmount !== null && $paidAmount > 0 ? $paidAmount : (float) $locked->amount;
            $msisdn = (string) ($meta['phone'] ?? '');

            if ($receipt && LoanBookPayment::query()->where('mpesa_receipt_number', $receipt)->exists()) {
                $meta['duplicate_receipt'] = true;
                $locked->update([
                    'status' => 'completed',
                    'result_code' => 0,
                    'result_desc' => 'Duplicate receipt skipped',
                    'transaction_id' => $receipt,
                    'meta' => $meta,
                ]);

                return;
            }

            $payment = LoanBookPayment::query()->create([
                'reference' => null,
                'loan_book_loan_id' => $loan?->id,
                'amount' => $amount,
                'currency' => $this->defaultLoanCurrency(),
                'channel' => 'mpesa_stk',
                'status' => LoanBookPayment::STATUS_UNPOSTED,
                'payment_kind' => LoanBookPayment::KIND_NORMAL,
                'mpesa_receipt_number' => $receipt,
                'payer_msisdn' => $msisdn !== '' ? $msisdn : null,
                'transaction_at' => now(),
                'notes' => 'Paid via loan STK Push (CheckoutRequestID: '.$checkoutRequestId.')',
                'created_by' => isset($meta['requested_by']) ? (int) $meta['requested_by'] : null,
            ]);
            $payment->update([
                'reference' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            ]);

            $meta['loan_book_payment_id'] = $payment->id;
            $locked->update([
                'status' => 'completed',
                'result_code' => 0,
                'result_desc' => $resultDesc !== '' ? $resultDesc : 'Success',
                'transaction_id' => $receipt ?: $locked->transaction_id,
                'amount' => $amount,
                'meta' => $meta,
            ]);

            if ($loan && (bool) config('services.loan_sms_ingest.auto_post_matched', true)) {
                $this->tryAutoPost($payment->fresh());
            }
        });

        return true;
    }

    private function accountReference(LoanBookLoan $loan): string
    {
        $ref = trim((string) ($loan->loan_number ?: 'LN'.$loan->id));
        $ref = preg_replace('/\s+/', '', $ref) ?: ('LN'.$loan->id);
        if (strlen($ref) > 12) {
            $ref = 'LN'.$loan->id;
        }

        return $ref;
    }

    private function tryAutoPost(LoanBookPayment $payment): void
    {
        try {
            DB::transaction(function () use ($payment): void {
                $locked = LoanBookPayment::query()->lockForUpdate()->find($payment->id);
                if (! $locked || $locked->status !== LoanBookPayment::STATUS_UNPOSTED || ! $locked->loan_book_loan_id) {
                    return;
                }
                if ($locked->accounting_journal_entry_id) {
                    return;
                }

                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($locked, null);
                $locked->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => null,
                    'accounting_journal_entry_id' => $entry->id,
                ]);

                $fresh = $locked->fresh(['allocations', 'loan']);
                app(LoanBookLoanUpdateService::class)->onPaymentProcessed($fresh);
                app(ClientWalletService::class)->syncPostedPaymentWalletEffects($fresh);
            });
        } catch (Throwable $e) {
            Log::warning('Loan STK auto-post failed; left unposted', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function defaultLoanCurrency(): string
    {
        if (! Schema::hasTable('property_portal_settings')) {
            return 'KES';
        }
        try {
            $v = PropertyPortalSetting::query()->value('loan_currency_code');

            return $v ? (string) $v : 'KES';
        } catch (Throwable) {
            return 'KES';
        }
    }
}
