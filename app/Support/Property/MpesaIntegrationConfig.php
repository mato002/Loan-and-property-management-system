<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;

/**
 * Resolves M-Pesa Daraja credentials from portal settings first, then .env.
 */
final class MpesaIntegrationConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $resolved = null;

    /**
     * @return array<string, mixed>
     */
    public static function resolve(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $env = (array) config('services.mpesa', []);
        $shortcode = self::pick('mpesa_shortcode', (string) ($env['stk_shortcode'] ?? $env['shortcode'] ?? ''));
        $callback = self::pick('mpesa_callback_url', (string) ($env['stk_callback_url'] ?? ''));
        if ($callback === '') {
            $callback = url('/webhooks/mpesa/stk-callback');
        }

        $b2cResult = self::pick('mpesa_b2c_result_url', (string) ($env['b2c_result_url'] ?? ''));
        if ($b2cResult === '') {
            $b2cResult = url('/webhooks/mpesa/b2c-result');
        }
        $b2cTimeout = self::pick('mpesa_b2c_timeout_url', (string) ($env['b2c_timeout_url'] ?? ''));
        if ($b2cTimeout === '') {
            $b2cTimeout = $b2cResult;
        }

        self::$resolved = array_merge($env, [
            'consumer_key' => self::pick('mpesa_consumer_key', (string) ($env['consumer_key'] ?? '')),
            'consumer_secret' => self::pick('mpesa_consumer_secret', (string) ($env['consumer_secret'] ?? '')),
            'passkey' => self::pick('mpesa_passkey', (string) ($env['passkey'] ?? '')),
            'shortcode' => $shortcode !== '' ? $shortcode : (string) ($env['shortcode'] ?? ''),
            'stk_shortcode' => $shortcode !== '' ? $shortcode : (string) ($env['stk_shortcode'] ?? $env['shortcode'] ?? ''),
            'stk_callback_url' => $callback,
            'b2c_shortcode' => self::pick('mpesa_b2c_shortcode', (string) ($env['b2c_shortcode'] ?? $shortcode)),
            'b2c_initiator_name' => self::pick('mpesa_b2c_initiator_name', (string) ($env['b2c_initiator_name'] ?? '')),
            'b2c_security_credential' => self::pick('mpesa_b2c_security_credential', (string) ($env['b2c_security_credential'] ?? '')),
            'b2c_result_url' => $b2cResult,
            'b2c_timeout_url' => $b2cTimeout,
            'c2b_shortcode' => self::pick('mpesa_c2b_shortcode', (string) ($env['c2b_shortcode'] ?? $shortcode)),
            'c2b_validation_url' => self::pick('mpesa_c2b_validation_url', (string) ($env['c2b_validation_url'] ?? '')),
            'c2b_confirmation_url' => self::pick('mpesa_c2b_confirmation_url', (string) ($env['c2b_confirmation_url'] ?? '')),
            'auto_receipt_enabled' => self::portalFlag('payment_auto_receipt_enabled', true),
            'auto_receipt_channel' => self::pick('payment_auto_receipt_channel', 'sms') ?: 'sms',
        ]);

        return self::$resolved;
    }

    public static function forget(): void
    {
        self::$resolved = null;
    }

    public static function autoReceiptEnabled(): bool
    {
        return (bool) (self::resolve()['auto_receipt_enabled'] ?? true);
    }

    public static function autoReceiptChannel(): string
    {
        $channel = strtolower(trim((string) (self::resolve()['auto_receipt_channel'] ?? 'sms')));

        return in_array($channel, ['sms', 'email', 'both'], true) ? $channel : 'sms';
    }

    public static function hasStoredSecret(string $portalKey): bool
    {
        return trim((string) PropertyPortalSetting::getGlobalValue($portalKey, '')) !== '';
    }

    private static function pick(string $portalKey, string $fallback): string
    {
        $portal = trim((string) PropertyPortalSetting::getGlobalValue($portalKey, ''));

        return $portal !== '' ? $portal : trim($fallback);
    }

    private static function portalFlag(string $key, bool $default): bool
    {
        $value = PropertyPortalSetting::getGlobalValue($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return $value === '1' || strtolower((string) $value) === 'true';
    }
}
