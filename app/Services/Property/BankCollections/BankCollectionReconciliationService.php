<?php

namespace App\Services\Property\BankCollections;

use App\Models\PmBankCollectionAuditLog;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Support\Property\BankIntegrationRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles inbound bank collection notifications against existing tenant Ac/No.
 */
final class BankCollectionReconciliationService
{
    public function __construct(
        private readonly TenantAccountNumberMatcher $matcher,
        private readonly EquityPaymentRepository $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     ok:bool,
     *     duplicate?:bool,
     *     matched?:bool,
     *     payment_id?:int|null,
     *     pm_payment_id?:int|null,
     *     message:string,
     *     tenant_account_number?:string
     * }
     */
    public function ingest(string $provider, array $payload, ?int $agentUserId = null): array
    {
        $provider = strtolower(trim($provider));
        if (! BankIntegrationRegistry::isValidProvider($provider)) {
            return ['ok' => false, 'message' => 'Unknown bank provider.'];
        }

        $tx = $this->normalize($provider, $payload);
        if ($tx['external_transaction_reference'] === '' || $tx['amount'] <= 0) {
            $this->audit($provider, 'ingest_rejected', $tx, null, null, 'rejected', 'Missing transaction reference or amount.');

            return ['ok' => false, 'message' => 'Missing transaction reference or amount.'];
        }

        if ($this->payments->transactionExists($tx['external_transaction_reference'])) {
            $this->audit($provider, 'duplicate', $tx, null, null, 'duplicate', 'Duplicate transaction — already ingested.');

            return [
                'ok' => true,
                'duplicate' => true,
                'matched' => false,
                'payment_id' => null,
                'pm_payment_id' => null,
                'tenant_account_number' => $tx['tenant_account_number'],
                'message' => 'Duplicate transaction — already ingested.',
            ];
        }

        // Preferred: existing tenant Ac/No. Fallback: unique phone, then unique name.
        // Amount alone never auto-assigns.
        $match = $this->matcher->matchTransaction([
            'account_number' => $tx['tenant_account_number'],
            'reference' => $tx['reference'],
            'payer_phone' => $tx['payer_phone'],
            'payer_name' => $tx['payer_name'],
        ], $agentUserId);
        $tx['tenant_account_number'] = $match['account_number'] !== ''
            ? $match['account_number']
            : $tx['tenant_account_number'];

        $options = [
            'payment_method' => $provider.'_collection',
            'channel' => $provider.'_collection',
            'source' => $provider.'_webhook',
            'provider' => $provider,
            'message' => 'Automatically settled from '.BankIntegrationRegistry::label($provider).' collection webhook.',
            'match_mode' => 'account_phone_name_with_phone',
        ];
        if ($agentUserId !== null && $agentUserId > 0) {
            $options['agent_user_id'] = $agentUserId;
        }

        $legacyTx = [
            'transaction_id' => $tx['external_transaction_reference'],
            'amount' => $tx['amount'],
            'account_number' => $tx['tenant_account_number'] ?: null,
            'phone' => $tx['payer_phone'],
            'reference' => $tx['reference'] ?: $tx['tenant_account_number'],
            'transaction_date' => $tx['transaction_date'],
            'raw_payload' => $tx['raw_payload'],
            'currency' => $tx['currency'],
            'payment_provider' => $provider,
            'payment_channel' => $provider.'_collection',
            'external_transaction_reference' => $tx['external_transaction_reference'],
            'provider_reference' => $tx['provider_reference'],
            'payer_name' => $tx['payer_name'],
            'payer_phone' => $tx['payer_phone'],
            'received_at' => $tx['received_at'],
            'invoice_id' => $tx['invoice_id'],
            'tenant_account_number' => $tx['tenant_account_number'],
            'reconciliation_notes' => null,
        ];

        try {
            if (($match['tenant_id'] ?? null) !== null) {
                $payment = $this->payments->storeMatched(
                    $legacyTx,
                    (int) $match['tenant_id'],
                    (string) ($match['matched_by'] ?? 'account_number'),
                    $options
                );
                $this->audit(
                    $provider,
                    'matched',
                    $tx,
                    (int) $match['tenant_id'],
                    (int) $payment->id,
                    'matched',
                    'Matched to tenant Ac/No '.$tx['tenant_account_number'].'.'
                );

                return [
                    'ok' => true,
                    'duplicate' => false,
                    'matched' => true,
                    'payment_id' => (int) $payment->id,
                    'pm_payment_id' => $payment->pm_payment_id ? (int) $payment->pm_payment_id : null,
                    'tenant_account_number' => $tx['tenant_account_number'],
                    'message' => 'Matched and settled.',
                ];
            }

            $legacyTx['reconciliation_notes'] = (string) ($match['reason'] ?? 'No tenant match');
            $payment = $this->payments->storeUnmatched($legacyTx, (string) ($match['reason'] ?? 'No tenant match'), $options);
            $this->audit(
                $provider,
                'unmatched',
                $tx,
                null,
                (int) $payment->id,
                'unmatched',
                (string) ($match['reason'] ?? 'No tenant match')
            );

            return [
                'ok' => true,
                'duplicate' => false,
                'matched' => false,
                'payment_id' => (int) $payment->id,
                'pm_payment_id' => null,
                'tenant_account_number' => $tx['tenant_account_number'],
                'message' => 'Unmatched — parked for agent assignment.',
            ];
        } catch (\Throwable $e) {
            Log::error('Bank collection reconciliation failed', [
                'provider' => $provider,
                'transaction' => $tx['external_transaction_reference'],
                'error' => $e->getMessage(),
            ]);
            $this->audit($provider, 'error', $tx, null, null, 'error', $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     external_transaction_reference:string,
     *     provider_reference:?string,
     *     amount:float,
     *     currency:string,
     *     tenant_account_number:?string,
     *     reference:?string,
     *     payer_name:?string,
     *     payer_phone:?string,
     *     invoice_id:?int,
     *     transaction_date:mixed,
     *     received_at:mixed,
     *     raw_payload:array<string,mixed>
     * }
     */
    public function normalize(string $provider, array $payload): array
    {
        $transactionId = trim((string) (
            $payload['external_transaction_reference']
            ?? $payload['transaction_id']
            ?? $payload['TransactionID']
            ?? $payload['TransID']
            ?? $payload['txn_id']
            ?? $payload['id']
            ?? $payload['reference_number']
            ?? ''
        ));

        $providerReference = trim((string) (
            $payload['provider_reference']
            ?? $payload['BankReference']
            ?? $payload['bank_reference']
            ?? $payload['ReceiptNo']
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
            $payload['tenant_account_number']
            ?? $payload['account_number']
            ?? $payload['AccountNumber']
            ?? $payload['BillRefNumber']
            ?? $payload['account']
            ?? $payload['customer_reference']
            ?? $payload['PaymentReference']
            ?? ''
        ));

        $phone = trim((string) (
            $payload['payer_phone']
            ?? $payload['phone']
            ?? $payload['MSISDN']
            ?? $payload['msisdn']
            ?? $payload['phone_number']
            ?? ''
        ));

        $payerName = trim((string) (
            $payload['payer_name']
            ?? $payload['CustomerName']
            ?? $payload['customer_name']
            ?? $payload['FirstName']
            ?? ''
        ));

        $reference = trim((string) (
            $payload['reference']
            ?? $payload['Narration']
            ?? $payload['InvoiceNumber']
            ?? $account
        ));

        $currency = strtoupper(trim((string) ($payload['currency'] ?? $payload['Currency'] ?? 'KES'))) ?: 'KES';
        $invoiceId = (int) ($payload['invoice_id'] ?? $payload['InvoiceId'] ?? 0);
        $invoiceId = $invoiceId > 0 ? $invoiceId : null;

        $dateRaw = $payload['transaction_date']
            ?? $payload['TransTime']
            ?? $payload['paid_at']
            ?? $payload['value_date']
            ?? null;
        $receivedRaw = $payload['received_at'] ?? $dateRaw;

        return [
            'external_transaction_reference' => $transactionId,
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'amount' => $amount,
            'currency' => $currency,
            'tenant_account_number' => $account !== '' ? strtoupper($account) : null,
            'reference' => $reference !== '' ? $reference : null,
            'payer_name' => $payerName !== '' ? $payerName : null,
            'payer_phone' => $phone !== '' ? $phone : null,
            'invoice_id' => $invoiceId,
            'transaction_date' => $this->parseDate($dateRaw),
            'received_at' => $this->parseDate($receivedRaw),
            'raw_payload' => array_merge($payload, ['_provider' => $provider]),
        ];
    }

    private function parseDate(mixed $dateRaw): Carbon
    {
        if (is_string($dateRaw) && trim($dateRaw) !== '') {
            try {
                return Carbon::parse($dateRaw);
            } catch (\Throwable) {
                return now();
            }
        }
        if ($dateRaw instanceof \DateTimeInterface) {
            return Carbon::parse($dateRaw->format('c'));
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private function audit(
        string $provider,
        string $event,
        array $tx,
        ?int $tenantId,
        ?int $paymentId,
        string $outcome,
        string $message,
    ): void {
        if (! Schema::hasTable('pm_bank_collection_audit_logs')) {
            return;
        }

        PmBankCollectionAuditLog::query()->create([
            'provider' => $provider,
            'event' => $event,
            'external_transaction_reference' => $tx['external_transaction_reference'] ?? null,
            'tenant_account_number' => $tx['tenant_account_number'] ?? null,
            'tenant_id' => $tenantId,
            'payment_id' => $paymentId,
            'outcome' => $outcome,
            'message' => $message,
            'payload' => $tx['raw_payload'] ?? $tx,
        ]);
    }
}
