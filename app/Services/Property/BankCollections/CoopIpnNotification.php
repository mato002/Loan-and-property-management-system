<?php

namespace App\Services\Property\BankCollections;

use Carbon\Carbon;

/**
 * Maps a Co-operative Bank IPN (instant payment notification) into a collection ingest payload.
 *
 * B2B IPN 2025 posts each core-banking event. AcctNo is the institution account.
 * The tenant Ac/No, phone, and name are read from Narration / CustMemoLine1-3.
 * PaymentRef is the bank's unique payment reference, not the tenant account.
 */
final class CoopIpnNotification
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     transaction_id:string,
     *     message_reference:string,
     *     amount:float,
     *     currency:string,
     *     event_type:string,
     *     is_credit:bool,
     *     bank_account_number:string,
     *     payer_reference:string,
     *     narration:string,
     *     payer_name:string,
     *     payer_phone:string,
     *     transaction_date:?string,
     *     ingest:array<string, mixed>
     * }
     */
    public static function normalize(array $payload): array
    {
        $body = self::notificationBody($payload);
        $event = strtoupper(trim((string) (
            $body['EventType']
            ?? $body['eventType']
            ?? $body['DebitCredit']
            ?? $body['debitCredit']
            ?? ''
        )));
        $isDebit = in_array($event, ['DEBIT', 'D', 'DR'], true);
        $bankAccount = trim((string) ($body['AcctNo'] ?? $body['AccountNumber'] ?? $body['accountNumber'] ?? ''));
        $paymentRef = trim((string) ($body['PaymentRef'] ?? $body['paymentRef'] ?? ''));
        $narration = trim((string) ($body['Narration'] ?? $body['narration'] ?? ''));
        $memo = self::memoText($body);
        $parsed = self::parseNarration($narration !== '' ? $narration : $memo);
        $payerReference = self::payerReference($bankAccount, $paymentRef, $narration, $memo, $parsed['account']);
        $transactionId = trim((string) ($body['TransactionId'] ?? $body['TransactionID'] ?? $body['transactionId'] ?? ''));
        $amount = self::amount($body['Amount'] ?? $body['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($body['Currency'] ?? $body['currency'] ?? 'KES'))) ?: 'KES';
        $payerName = $parsed['name'];
        $payerPhone = $parsed['phone'];
        $transactionDate = self::dateString(
            $body['TransactionDate']
            ?? $body['transactionDate']
            ?? $body['PostingDate']
            ?? $body['ValueDate']
            ?? null
        );
        $referenceText = $payerReference !== '' ? $payerReference : $paymentRef;

        return [
            'transaction_id' => $transactionId,
            'message_reference' => $paymentRef,
            'amount' => $amount,
            'currency' => $currency,
            'event_type' => $event !== '' ? $event : 'CREDIT',
            'is_credit' => ! $isDebit,
            'bank_account_number' => $bankAccount,
            'payer_reference' => $payerReference,
            'narration' => trim($narration.' '.$memo),
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'transaction_date' => $transactionDate,
            'ingest' => [
                'external_transaction_reference' => $transactionId,
                'provider_reference' => $paymentRef !== '' ? $paymentRef : null,
                'amount' => $amount,
                'currency' => $currency,
                'tenant_account_number' => $payerReference !== '' ? $payerReference : null,
                'reference' => $referenceText !== '' ? $referenceText : null,
                'payer_name' => $payerName !== '' ? $payerName : null,
                'payer_phone' => $payerPhone !== '' ? $payerPhone : null,
                'transaction_date' => $transactionDate,
                'received_at' => $transactionDate,
                'raw_payload' => $payload,
            ],
        ];
    }

    /**
     * B2B IPN 2025 acknowledgement. MessageCode 200 or 201 is success. Anything else is retried.
     *
     * @return array{MessageCode:string, Message:string}
     */
    public static function acknowledgement(string $code, string $message): array
    {
        return [
            'MessageCode' => $code,
            'Message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function notificationBody(array $payload): array
    {
        $nested = $payload['requestPayload']['additionalData']['notificationData']
            ?? $payload['Notification']
            ?? $payload['notification']
            ?? $payload['payload']
            ?? null;

        if (! is_array($nested)) {
            return $payload;
        }

        return array_merge($payload, $nested);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function memoText(array $body): string
    {
        $nested = $body['CustMemo'] ?? $body['custMemo'] ?? null;
        $lines = [];
        foreach (['CustMemoLine1', 'CustMemoLine2', 'CustMemoLine3'] as $key) {
            $line = trim((string) ($body[$key] ?? (is_array($nested) ? ($nested[$key] ?? '') : '')));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return trim(implode(' ', $lines));
    }

    /**
     * M-Pesa narrations arrive as receipt~account#name~phone~channel~customer.
     *
     * @return array{account:string, phone:string, name:string}
     */
    private static function parseNarration(string $text): array
    {
        $account = '';
        $phone = '';
        $name = '';
        foreach (array_values(array_filter(array_map('trim', explode('~', $text)), static fn (string $part): bool => $part !== '')) as $part) {
            $digits = preg_replace('/\D+/', '', $part) ?? '';
            if ($phone === '' && preg_match('/^(?:254\d{9}|0\d{9})$/', $digits) === 1) {
                $phone = str_starts_with($digits, '0') ? '254'.substr($digits, 1) : $digits;

                continue;
            }
            if (str_contains($part, '#')) {
                [$left, $right] = array_pad(explode('#', $part, 2), 2, '');
                if ($account === '' && trim($left) !== '') {
                    $account = trim($left);
                }
                if ($name === '' && trim($right) !== '' && ! str_contains($right, ' ')) {
                    $name = trim($right);
                }

                continue;
            }
            if (preg_match('/MPESA|PESALINK|RTGS|EFT|IFT/i', $part) === 1) {
                continue;
            }
            if (preg_match("/^[A-Za-z][A-Za-z .'-]{2,}$/", $part) === 1 && str_contains($part, ' ')) {
                $name = trim($part);
            }
        }

        if (preg_match('/\b(TNT[A-Z0-9\-]+)\b/i', $text, $match) === 1) {
            $account = strtoupper($match[1]);
        }

        return [
            'account' => $account,
            'phone' => $phone,
            'name' => $name,
        ];
    }

    private static function payerReference(string $bankAccount, string $paymentRef, string $narration, string $memo, string $parsedAccount): string
    {
        $bank = self::compact($bankAccount);
        $blob = trim($paymentRef.' '.$narration.' '.$memo);
        if (preg_match('/\b(TNT[A-Z0-9\-]+)\b/i', $blob, $match) === 1) {
            $token = strtoupper($match[1]);
            if (self::compact($token) !== $bank) {
                return $token;
            }
        }

        if ($parsedAccount !== '' && self::compact($parsedAccount) !== $bank) {
            return $parsedAccount;
        }

        if ($paymentRef !== '' && self::compact($paymentRef) !== $bank && preg_match('/^\d{6,8}_\d+$/', $paymentRef) !== 1) {
            return $paymentRef;
        }

        return '';
    }

    private static function compact(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '_'], '', trim($value)));
    }

    private static function amount(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $raw = str_replace([',', ' '], '', trim((string) $value));
        if ($raw === '' || ! is_numeric($raw)) {
            return 0.0;
        }

        return round((float) $raw, 2);
    }

    private static function dateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->toIso8601String();
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{14}$/', $raw) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('YmdHis', $raw);

            return $parsed instanceof \DateTimeImmutable ? $parsed->format('c') : null;
        }
        if (preg_match('/^\d{8}$/', $raw) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('Ymd', $raw);

            return $parsed instanceof \DateTimeImmutable ? $parsed->format('Y-m-d') : null;
        }
        try {
            return Carbon::parse($raw)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
