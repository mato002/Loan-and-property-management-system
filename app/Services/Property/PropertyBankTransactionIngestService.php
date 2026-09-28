<?php

namespace App\Services\Property;

use App\Repositories\Equity\EquityPaymentRepository;
use App\Services\PaymentMatchingService;
use App\Support\Property\BankIntegrationRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Ingests inbound bank paybill / collection notifications for any configured provider.
 */
class PropertyBankTransactionIngestService
{
    public function __construct(
        private readonly PaymentMatchingService $matcher,
        private readonly EquityPaymentRepository $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,duplicate?:bool,matched?:bool,payment_id?:int|null,pm_payment_id?:int|null,message:string}
     */
    public function ingest(string $provider, array $payload, ?int $agentUserId = null): array
    {
        if (! BankIntegrationRegistry::isValidProvider($provider)) {
            return ['ok' => false, 'message' => 'Unknown bank provider.'];
        }

        $tx = $this->normalize($provider, $payload);
        if ($tx['transaction_id'] === '' || $tx['amount'] <= 0) {
            return ['ok' => false, 'message' => 'Missing transaction_id or amount.'];
        }

        if ($this->payments->transactionExists($tx['transaction_id'])) {
            return [
                'ok' => true,
                'duplicate' => true,
                'matched' => false,
                'payment_id' => null,
                'pm_payment_id' => null,
                'message' => 'Duplicate transaction — already ingested.',
            ];
        }

        $match = $this->matcher->match($tx, $agentUserId);
        $options = [
            'payment_method' => $provider.'_paybill',
            'channel' => $provider.'_paybill',
            'source' => $provider.'_webhook',
            'provider' => $provider,
            'message' => 'Automatically settled from '.BankIntegrationRegistry::label($provider).' webhook.',
        ];
        if ($agentUserId !== null && $agentUserId > 0) {
            $options['agent_user_id'] = $agentUserId;
        }

        try {
            if (($match['tenant_id'] ?? null) !== null) {
                $payment = $this->payments->storeMatched($tx, (int) $match['tenant_id'], (string) ($match['matched_by'] ?? 'unknown'), $options);

                return [
                    'ok' => true,
                    'duplicate' => false,
                    'matched' => true,
                    'payment_id' => (int) $payment->id,
                    'pm_payment_id' => $payment->pm_payment_id ? (int) $payment->pm_payment_id : null,
                    'message' => 'Matched and settled.',
                ];
            }

            $payment = $this->payments->storeUnmatched($tx, (string) ($match['reason'] ?? 'No tenant match'), $options);

            return [
                'ok' => true,
                'duplicate' => false,
                'matched' => false,
                'payment_id' => (int) $payment->id,
                'pm_payment_id' => null,
                'message' => 'Unmatched — parked for agent assignment.',
            ];
        } catch (\Throwable $e) {
            Log::error('Bank transaction ingest failed', [
                'provider' => $provider,
                'transaction_id' => $tx['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     transaction_id:string,
     *     amount:float,
     *     account_number:?string,
     *     phone:?string,
     *     reference:?string,
     *     transaction_date:mixed,
     *     raw_payload:array<string,mixed>
     * }
     */
    public function normalize(string $provider, array $payload): array
    {
        $transactionId = trim((string) (
            $payload['transaction_id']
            ?? $payload['TransactionID']
            ?? $payload['TransID']
            ?? $payload['txn_id']
            ?? $payload['id']
            ?? $payload['reference_number']
            ?? ''
        ));

        $amount = (float) (
            $payload['amount']
            ?? $payload['Amount']
            ?? $payload['TransAmount']
            ?? $payload['transaction_amount']
            ?? 0
        );

        $account = trim((string) (
            $payload['account_number']
            ?? $payload['AccountNumber']
            ?? $payload['BillRefNumber']
            ?? $payload['account']
            ?? $payload['customer_reference']
            ?? ''
        ));

        $phone = trim((string) (
            $payload['phone']
            ?? $payload['MSISDN']
            ?? $payload['msisdn']
            ?? $payload['phone_number']
            ?? ''
        ));

        $reference = trim((string) (
            $payload['reference']
            ?? $payload['Narration']
            ?? $payload['InvoiceNumber']
            ?? $account
        ));

        $dateRaw = $payload['transaction_date']
            ?? $payload['TransTime']
            ?? $payload['paid_at']
            ?? $payload['value_date']
            ?? null;

        $transactionDate = now();
        if (is_string($dateRaw) && trim($dateRaw) !== '') {
            try {
                $transactionDate = Carbon::parse($dateRaw);
            } catch (\Throwable) {
                $transactionDate = now();
            }
        } elseif ($dateRaw instanceof \DateTimeInterface) {
            $transactionDate = Carbon::parse($dateRaw->format('c'));
        }

        return [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'account_number' => $account !== '' ? $account : null,
            'phone' => $phone !== '' ? $phone : null,
            'reference' => $reference !== '' ? $reference : null,
            'transaction_date' => $transactionDate,
            'raw_payload' => array_merge($payload, ['_provider' => $provider]),
        ];
    }
}
