<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Parses forwarded M-Pesa / Equity-style payment confirmation SMS payloads.
 * Shared by property and loan SMS ingest webhooks.
 */
final class MpesaSmsForwarderParser
{
    /**
     * @return array{provider_txn_code?:string,amount?:float,phone?:string}
     */
    public static function extractMpesaFields(string $message): array
    {
        if (trim($message) === '') {
            return [];
        }

        $out = [];

        if (preg_match('/\b(?:m-?pesa\s+)?ref(?:erence)?\.?\s*[:\-]?\s*([A-Z0-9]{8,12})\b/iu', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if (self::isLikelyTxnCode($candidate)) {
                $out['provider_txn_code'] = $candidate;
            }
        }

        if (! isset($out['provider_txn_code']) && preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/iu', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if (self::isLikelyTxnCode($candidate)) {
                $out['provider_txn_code'] = $candidate;
            }
        } elseif (! isset($out['provider_txn_code']) && preg_match('/\b([A-Z0-9]{8,12})\b/u', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if (self::isLikelyTxnCode($candidate)) {
                $out['provider_txn_code'] = $candidate;
            }
        }

        if (preg_match('/(?:KSH|KES)\.?\s*([0-9,]+(?:\.[0-9]{1,2})?)/iu', $message, $m) === 1) {
            $value = str_replace(',', '', (string) $m[1]);
            $out['amount'] = (float) $value;
        }

        if (preg_match('/\b(\+?2547\d{8}|\+?2541\d{8}|07\d{8}|01\d{8})\b/u', $message, $m) === 1) {
            $out['phone'] = (string) $m[1];
        }

        return $out;
    }

    public static function extractMpesaPaidAt(string $message): ?Carbon
    {
        if (trim($message) === '') {
            return null;
        }

        $day = $month = $year = $hour = $minute = null;
        if (preg_match('/\bon\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\s+at\s+(\d{1,2}):(\d{2})\s*(AM|PM)\b/iu', $message, $m) === 1) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            $hour = (int) $m[4];
            $minute = (int) $m[5];
            $meridiem = strtoupper((string) $m[6]);
            if ($year < 100) {
                $year += 2000;
            }
            if ($meridiem === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($meridiem === 'AM' && $hour === 12) {
                $hour = 0;
            }
        } elseif (preg_match('/\bon\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\s+at\s+(\d{1,2}):(\d{2})\b/u', $message, $m) === 1) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            $hour = (int) $m[4];
            $minute = (int) $m[5];
            if ($year < 100) {
                $year += 2000;
            }
        } else {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day, $hour, $minute, 0, 'Africa/Nairobi')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isLikelyTxnCode(string $value): bool
    {
        return preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{8,12}$/', $value) === 1;
    }

    public static function isLikelyIncomingPaymentMessage(string $message): bool
    {
        $text = Str::lower(trim($message));
        if ($text === '') {
            return false;
        }

        $negativeSignals = [
            ' sent to ',
            'fuliza',
            'airtime',
            'withdraw',
            'withdrawn',
            'moved from your m-pesa account',
            'okoa jahazi',
        ];
        foreach ($negativeSignals as $signal) {
            if (str_contains($text, $signal)) {
                return false;
            }
        }

        $positiveSignals = [
            'received',
            'received from',
            'you have received',
            'paid',
            'payment received',
            'confirmed',
        ];
        foreach ($positiveSignals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    public static function isAllowedSmsProvider(string $provider, string $message): bool
    {
        $p = strtolower(trim($provider));
        if (in_array($p, ['mpesa', 'equity'], true)) {
            return true;
        }

        $text = Str::lower(trim($message));
        if ($text === '') {
            return false;
        }

        return str_contains($text, 'm-pesa')
            || str_contains($text, 'mpesa')
            || str_contains($text, 'equity');
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function normalizePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public static function normalizePaidAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
