<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MpesaDarajaService
{
    private function config(): array
    {
        return (array) config('services.mpesa', []);
    }

    /**
     * @return list<string>
     */
    public function missingConfigKeys(): array
    {
        $cfg = $this->config();
        $missing = [];

        foreach ([
            'consumer_key',
            'consumer_secret',
            'passkey',
            'stk_shortcode',
            'stk_callback_url',
        ] as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '') {
                $missing[] = $k;
            }
        }

        return $missing;
    }

    private function baseUrl(): string
    {
        $env = strtolower((string) ($this->config()['env'] ?? 'sandbox'));

        return $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function isConfigured(): bool
    {
        return $this->missingConfigKeys() === [];
    }

    public function normalizeMsisdn(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (Str::startsWith($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }
        if (Str::startsWith($digits, '7') || Str::startsWith($digits, '1')) {
            // e.g. 712... / 112... -> assume Kenya and prepend 254
            $digits = '254'.$digits;
        }
        if (Str::startsWith($digits, '2540')) {
            $digits = '254'.substr($digits, 4);
        }

        return $digits;
    }

    public function getAccessToken(): string
    {
        $cfg = $this->config();

        $key = (string) ($cfg['consumer_key'] ?? '');
        $secret = (string) ($cfg['consumer_secret'] ?? '');

        if ($key === '' || $secret === '') {
            throw new RuntimeException('Daraja is not configured (missing consumer key/secret).');
        }

        $res = Http::withBasicAuth($key, $secret)
            ->timeout(20)
            ->acceptJson()
            ->get($this->baseUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        if (! $res->ok()) {
            throw new RuntimeException('Daraja token request failed: '.$res->status().' '.$res->body());
        }

        $token = (string) ($res->json('access_token') ?? '');
        if ($token === '') {
            throw new RuntimeException('Daraja token response missing access_token.');
        }

        return $token;
    }

    /**
     * Initiate STK push.
     *
     * @return array{ok:bool, status:int|null, body:array<string,mixed>|null, message:string|null}
     */
    public function stkPush(array $payload): array
    {
        $cfg = $this->config();

        $shortcode = (string) ($cfg['stk_shortcode'] ?? '');
        $passkey = (string) ($cfg['passkey'] ?? '');
        $callbackUrl = (string) ($cfg['stk_callback_url'] ?? '');

        if ($shortcode === '' || $passkey === '' || $callbackUrl === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'message' => 'Daraja not configured (shortcode/passkey/callback).'];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        $token = $this->getAccessToken();

        $res = Http::timeout(25)
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->post($this->baseUrl().'/mpesa/stkpush/v1/processrequest', array_merge([
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'CallBackURL' => $callbackUrl,
            ], $payload));

        $body = $res->json();
        $message = is_array($body)
            ? (string) (
                $body['CustomerMessage']
                ?? $body['errorMessage']
                ?? $body['ResponseDescription']
                ?? $body['responseDescription']
                ?? $body['ResponseDescription']
                ?? null
            )
            : null;

        // Daraja often returns HTTP 200 even when initiation fails (ResponseCode != "0").
        $responseCode = is_array($body) ? (string) ($body['ResponseCode'] ?? '') : '';
        $ok = $res->ok() && ($responseCode === '' || $responseCode === '0');

        return [
            'ok' => $ok,
            'status' => $res->status(),
            'body' => is_array($body) ? $body : null,
            'message' => $message,
        ];
    }
}

