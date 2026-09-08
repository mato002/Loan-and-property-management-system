<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;
use Illuminate\Support\Facades\Cache;

final class BankIntegrationConfig
{
    public const SELECTED_PROVIDER_KEY = 'collection_bank_provider';

    public const SYNC_ENABLED_KEY = 'bank_sync_enabled';

    public const SYNC_INTERVAL_KEY = 'bank_sync_interval_minutes';

    /** @var array<string, array<string, mixed>> */
    private static array $resolved = [];

    public static function selectedProvider(): string
    {
        $selected = trim((string) PropertyPortalSetting::getGlobalValue(self::SELECTED_PROVIDER_KEY, ''));
        if ($selected !== '' && BankIntegrationRegistry::isValidProvider($selected)) {
            return $selected;
        }

        if (self::hasLegacyEquityCredentials()) {
            return 'equity';
        }

        foreach (BankIntegrationRegistry::providers() as $id => $_meta) {
            if (self::hasStoredCredentials($id)) {
                return $id;
            }
        }

        return 'equity';
    }

    /**
     * @return array{
     *     provider: string,
     *     label: string,
     *     auth_type: string,
     *     sync_driver: ?string,
     *     base_url: string,
     *     username: string,
     *     password: string,
     *     api_key: string,
     *     api_secret: string,
     *     merchant_code: string,
     *     auth_endpoint: string,
     *     transactions_endpoint: string,
     *     balance_endpoint: string,
     *     timeout_seconds: int,
     *     retry_times: int,
     *     retry_sleep_ms: int,
     *     sync_interval_minutes: int,
     *     sync_enabled: bool,
     *     paybill_number: string,
     *     webhook_secret: string,
     *     notes: string,
     *     source: string
     * }
     */
    public static function resolve(?string $provider = null): array
    {
        $provider = $provider ?? self::selectedProvider();
        if (! BankIntegrationRegistry::isValidProvider($provider)) {
            $provider = 'equity';
        }

        if (isset(self::$resolved[$provider])) {
            return self::$resolved[$provider];
        }

        $meta = BankIntegrationRegistry::provider($provider) ?? [];
        $defaults = (array) ($meta['default_endpoints'] ?? []);
        $envProvider = strtoupper($provider);
        $fromDb = self::hasStoredCredentials($provider);

        $config = [
            'provider' => $provider,
            'label' => BankIntegrationRegistry::label($provider),
            'auth_type' => BankIntegrationRegistry::authType($provider),
            'sync_driver' => BankIntegrationRegistry::syncDriver($provider),
            'base_url' => self::pick($provider, 'base_url', "PROPERTY_BANK_{$envProvider}_BASE_URL", 'EQUITY_API_BASE_URL'),
            'username' => self::pick($provider, 'username', 'EQUITY_API_USERNAME'),
            'password' => self::pick($provider, 'password', 'EQUITY_API_PASSWORD'),
            'api_key' => self::pick($provider, 'api_key', "PROPERTY_BANK_{$envProvider}_API_KEY", 'EQUITY_API_KEY'),
            'api_secret' => self::pick($provider, 'api_secret', "PROPERTY_BANK_{$envProvider}_API_SECRET", 'EQUITY_API_SECRET'),
            'merchant_code' => self::pick($provider, 'merchant_code', "PROPERTY_BANK_{$envProvider}_MERCHANT_CODE"),
            'auth_endpoint' => self::pick($provider, 'auth_endpoint', 'EQUITY_API_AUTH_ENDPOINT') ?: (string) ($defaults['auth'] ?? '/oauth/token'),
            'transactions_endpoint' => self::pick($provider, 'transactions_endpoint', 'EQUITY_API_TRANSACTIONS_ENDPOINT') ?: (string) ($defaults['transactions'] ?? '/paybill/transactions'),
            'balance_endpoint' => self::pick($provider, 'balance_endpoint', 'EQUITY_API_BALANCE_ENDPOINT') ?: (string) ($defaults['balance'] ?? '/accounts/balance'),
            'timeout_seconds' => max(5, (int) (self::portalValue('bank_api_timeout_seconds') ?: config('services.property_banks.timeout_seconds', 25))),
            'retry_times' => max(0, (int) (self::portalValue('bank_api_retry_times') ?: 3)),
            'retry_sleep_ms' => max(0, (int) (self::portalValue('bank_api_retry_sleep_ms') ?: 500)),
            'sync_interval_minutes' => max(1, (int) (self::portalValue(self::SYNC_INTERVAL_KEY) ?: config('equity.sync_interval_minutes', 5))),
            'sync_enabled' => self::portalValue(self::SYNC_ENABLED_KEY) === '1'
                || self::portalValue('equity_sync_enabled', '0') === '1',
            'paybill_number' => trim((string) self::portalValue(self::providerKey($provider, 'paybill_number'), '')),
            'webhook_secret' => self::pick($provider, 'webhook_secret', "PROPERTY_BANK_{$envProvider}_WEBHOOK_SECRET"),
            'notes' => trim((string) self::portalValue(self::providerKey($provider, 'notes'), '')),
            'source' => $fromDb ? 'portal_settings' : (self::hasEnvCredentials($provider) ? 'env' : 'none'),
        ];

        self::$resolved[$provider] = $config;

        return $config;
    }

    public static function isConfigured(?string $provider = null): bool
    {
        $provider = $provider ?? self::selectedProvider();
        $config = self::resolve($provider);
        $baseUrl = trim((string) ($config['base_url'] ?? ''));

        if ($baseUrl === '' || preg_match('#^https?://#i', $baseUrl) !== 1) {
            return false;
        }

        if (($config['auth_type'] ?? '') === 'oauth') {
            return trim((string) ($config['username'] ?? '')) !== ''
                && trim((string) ($config['password'] ?? '')) !== ''
                && trim((string) ($config['api_key'] ?? '')) !== ''
                && trim((string) ($config['api_secret'] ?? '')) !== '';
        }

        return trim((string) ($config['api_key'] ?? '')) !== ''
            && trim((string) ($config['api_secret'] ?? '')) !== ''
            && trim((string) ($config['merchant_code'] ?? '')) !== '';
    }

    public static function syncEnabled(): bool
    {
        $provider = self::selectedProvider();
        $driver = BankIntegrationRegistry::syncDriver($provider);

        return $driver !== null
            && self::isConfigured($provider)
            && (bool) self::resolve($provider)['sync_enabled'];
    }

    public static function tokenCacheKey(string $provider): string
    {
        return 'bank_api_access_token_'.$provider;
    }

    public static function forgetCachedToken(?string $provider = null): void
    {
        $provider = $provider ?? self::selectedProvider();
        try {
            Cache::forget(self::tokenCacheKey($provider));
            if ($provider === 'equity') {
                Cache::forget(EquityIntegrationConfig::TOKEN_CACHE_KEY);
            }
        } catch (\Throwable) {
            // ignore
        }
        unset(self::$resolved[$provider]);
    }

    public static function hasStoredSecret(string $provider, string $field): bool
    {
        $portalKey = self::providerKey($provider, $field);
        if (trim((string) self::portalValue($portalKey, '')) !== '') {
            return true;
        }

        if ($provider === 'equity') {
            $legacy = [
                'password' => 'equity_api_password',
                'api_secret' => 'equity_api_secret',
                'webhook_secret' => 'equity_bank_webhook_secret',
            ][$field] ?? null;
            if ($legacy && trim((string) self::portalValue($legacy, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function mergeForTest(string $provider, array $overrides): array
    {
        $base = self::resolve($provider);
        foreach (['base_url', 'username', 'password', 'api_key', 'api_secret', 'merchant_code', 'auth_endpoint'] as $key) {
            $value = trim((string) ($overrides[$key] ?? ''));
            if ($value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public static function providerKey(string $provider, string $field): string
    {
        return 'bank_'.$provider.'_'.$field;
    }

    public static function setSelectedProvider(string $provider): void
    {
        if (! BankIntegrationRegistry::isValidProvider($provider)) {
            return;
        }
        PropertyPortalSetting::setGlobalValue(self::SELECTED_PROVIDER_KEY, $provider);
        unset(self::$resolved[$provider]);
    }

    public static function webhookUrl(string $provider): string
    {
        return url('/webhooks/property/payments/bank/'.$provider);
    }

    private static function hasStoredCredentials(string $provider): bool
    {
        foreach (self::credentialFields($provider) as $field) {
            if (trim((string) self::portalValue(self::providerKey($provider, $field), '')) !== '') {
                return true;
            }
        }

        if ($provider === 'equity' && self::hasLegacyEquityCredentials()) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function credentialFields(string $provider): array
    {
        if (BankIntegrationRegistry::authType($provider) === 'oauth') {
            return ['base_url', 'username', 'password', 'api_key', 'api_secret'];
        }

        return ['base_url', 'api_key', 'api_secret', 'merchant_code'];
    }

    private static function hasLegacyEquityCredentials(): bool
    {
        foreach (['equity_api_base_url', 'equity_api_username', 'equity_api_password', 'equity_api_key', 'equity_api_secret'] as $key) {
            if (trim((string) PropertyPortalSetting::getGlobalValue($key, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function hasEnvCredentials(string $provider): bool
    {
        $env = strtoupper($provider);
        $base = trim((string) env("PROPERTY_BANK_{$env}_BASE_URL", ''));
        if ($provider === 'equity' && $base === '') {
            $base = trim((string) env('EQUITY_API_BASE_URL', ''));
        }

        if ($base === '') {
            return false;
        }

        if (BankIntegrationRegistry::authType($provider) === 'oauth') {
            return trim((string) env('EQUITY_API_USERNAME', '')) !== ''
                && trim((string) env('EQUITY_API_KEY', env("PROPERTY_BANK_{$env}_API_KEY", ''))) !== '';
        }

        return trim((string) env("PROPERTY_BANK_{$env}_API_KEY", '')) !== '';
    }

    private static function portalValue(string $key, ?string $default = null): ?string
    {
        $value = PropertyPortalSetting::getGlobalValue($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @param  non-empty-string  ...$envKeys
     */
    private static function pick(string $provider, string $field, string ...$envKeys): string
    {
        $portal = trim((string) self::portalValue(self::providerKey($provider, $field), ''));
        if ($portal !== '') {
            return $portal;
        }

        if ($provider === 'equity') {
            $legacyMap = [
                'base_url' => 'equity_api_base_url',
                'username' => 'equity_api_username',
                'password' => 'equity_api_password',
                'api_key' => 'equity_api_key',
                'api_secret' => 'equity_api_secret',
                'auth_endpoint' => 'equity_api_auth_endpoint',
                'transactions_endpoint' => 'equity_api_transactions_endpoint',
                'balance_endpoint' => 'equity_api_balance_endpoint',
                'paybill_number' => 'equity_paybill_number',
                'webhook_secret' => 'equity_bank_webhook_secret',
                'notes' => 'equity_integration_notes',
            ];
            if (isset($legacyMap[$field])) {
                $legacy = trim((string) self::portalValue($legacyMap[$field], ''));
                if ($legacy !== '') {
                    return $legacy;
                }
            }
        }

        $services = (array) config('services.property_banks.providers.'.$provider, []);
        $servicesMap = [
            'base_url' => 'base_url',
            'api_key' => 'api_key',
            'api_secret' => 'api_secret',
            'merchant_code' => 'merchant_code',
            'webhook_secret' => 'webhook_secret',
        ];
        if (isset($servicesMap[$field])) {
            $fromServices = trim((string) ($services[$servicesMap[$field]] ?? ''));
            if ($fromServices !== '') {
                return $fromServices;
            }
        }

        foreach ($envKeys as $envKey) {
            $env = trim((string) env($envKey, ''));
            if ($env !== '') {
                return $env;
            }
        }

        if ($provider === 'equity') {
            $equityConfig = config('equity.'.$field);

            return is_string($equityConfig) ? trim($equityConfig) : (string) ($equityConfig ?? '');
        }

        return '';
    }
}
