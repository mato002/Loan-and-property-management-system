<?php

namespace App\Services\LoanBook;

use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\MpesaPlatformTransaction;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\LoanBookGlPostingService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LoanDisbursementPayoutService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
        private readonly LoanBookGlPostingService $glPosting,
        private readonly LoanBookLoanUpdateService $loanUpdateService,
    ) {}

    /**
     * Initiate M-Pesa B2C payout for a loan disbursement row.
     */
    public function initiateMpesaPayout(LoanBookDisbursement $disbursement, LoanBookLoan $loan): array
    {
        $clientPhone = (string) ($loan->loanClient?->phone ?? '');
        if (trim($clientPhone) === '') {
            return ['ok' => false, 'message' => 'Selected loan client has no phone number for M-Pesa payout.'];
        }

        $phone = $this->daraja->normalizeMsisdn($clientPhone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Could not normalize client phone for M-Pesa payout.'];
        }

        $payload = [
            'Amount' => (int) round((float) $disbursement->amount, 0),
            'PartyB' => $phone,
            'Remarks' => 'Loan disbursement '.$disbursement->reference,
            'Occasion' => 'loan_disbursement_'.$disbursement->id,
        ];

        $response = $this->daraja->b2cPayout($payload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];

        $conversationId = (string) ($body['ConversationID'] ?? '');
        $originatorConversationId = (string) ($body['OriginatorConversationID'] ?? '');
        $resultCode = Arr::get($body, 'ResponseCode');
        $resultDesc = (string) ($body['ResponseDescription'] ?? $response['message'] ?? '');

        DB::transaction(function () use (
            $disbursement,
            $phone,
            $response,
            $body,
            $conversationId,
            $originatorConversationId,
            $resultCode,
            $resultDesc,
        ): void {
            $disbursement->update([
                'payout_status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
                'payout_provider' => 'mpesa',
                'payout_phone' => $phone,
                'payout_conversation_id' => $conversationId !== '' ? $conversationId : null,
                'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
                'payout_result_code' => is_numeric($resultCode) ? (int) $resultCode : null,
                'payout_result_desc' => $resultDesc !== '' ? $resultDesc : null,
                'payout_requested_at' => now(),
                'payout_meta' => [
                    'initiation' => [
                        'response' => $response,
                        'received_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            MpesaPlatformTransaction::query()->create([
                'reference' => $disbursement->reference,
                'amount' => $disbursement->amount,
                'channel' => 'b2c',
                'status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
                'notes' => $resultDesc,
                'conversation_id' => $conversationId !== '' ? $conversationId : null,
                'originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
                'result_code' => is_numeric($resultCode) ? (int) $resultCode : null,
                'result_desc' => $resultDesc !== '' ? $resultDesc : null,
                'meta' => [
                    'loan_book_disbursement_id' => $disbursement->id,
                    'loan_book_loan_id' => $disbursement->loan_book_loan_id,
                    'provider' => 'mpesa',
                    'initiation_body' => $body,
                ],
            ]);
        });

        return [
            'ok' => (bool) ($response['ok'] ?? false),
            'message' => (string) ($response['message'] ?? ''),
        ];
    }

    /**
     * Handle Daraja B2C callback and mark linked disbursement.
     */
    public function applyB2cCallbackToDisbursement(array $payload, array $resultMap, string $status): ?LoanBookDisbursement
    {
        $result = (array) ($payload['Result'] ?? []);
        $conversationId = (string) ($result['ConversationID'] ?? '');
        $originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $transactionId = (string) ($result['TransactionID'] ?? '');
        $resultCode = isset($result['ResultCode']) ? (int) $result['ResultCode'] : null;
        $resultDesc = (string) ($result['ResultDesc'] ?? '');

        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'b2c')
            ->when($conversationId !== '', fn ($q) => $q->orWhere('conversation_id', $conversationId))
            ->when($originatorConversationId !== '', fn ($q) => $q->orWhere('originator_conversation_id', $originatorConversationId))
            ->when($transactionId !== '', fn ($q) => $q->orWhere('transaction_id', $transactionId))
            ->orderByDesc('id')
            ->first();

        $disbursementId = (int) (data_get($tx?->meta, 'loan_book_disbursement_id') ?? 0);
        if ($disbursementId <= 0) {
            return null;
        }

        $disbursement = LoanBookDisbursement::query()->with('loan')->find($disbursementId);
        if (! $disbursement) {
            return null;
        }

        DB::transaction(function () use (
            $disbursement,
            $status,
            $conversationId,
            $originatorConversationId,
            $transactionId,
            $resultCode,
            $resultDesc,
            $payload,
            $resultMap,
        ): void {
            $meta = is_array($disbursement->payout_meta) ? $disbursement->payout_meta : [];
            $meta['callback'] = [
                'received_at' => now()->toIso8601String(),
                'payload' => $payload,
                'result_parameters' => $resultMap,
            ];

            $disbursement->update([
                'payout_status' => $status,
                'payout_conversation_id' => $conversationId !== '' ? $conversationId : $disbursement->payout_conversation_id,
                'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : $disbursement->payout_originator_conversation_id,
                'payout_transaction_id' => $transactionId !== '' ? $transactionId : $disbursement->payout_transaction_id,
                'payout_result_code' => $resultCode,
                'payout_result_desc' => $resultDesc !== '' ? $resultDesc : $disbursement->payout_result_desc,
                'payout_completed_at' => $status === 'completed' ? now() : null,
                'payout_meta' => $meta,
            ]);

            if ($status === 'completed' && ! $disbursement->accounting_journal_entry_id) {
                $entry = $this->glPosting->postDisbursement($disbursement, null);
                $disbursement->update(['accounting_journal_entry_id' => $entry->id]);
                $this->loanUpdateService->onDisbursed($disbursement);
            }
        });

        return $disbursement;
    }
}
