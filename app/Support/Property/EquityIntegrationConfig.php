<?php

namespace App\Support\Property;

/**
 * Backward-compatible alias for Equity-specific call sites.
 *
 * @deprecated Prefer BankIntegrationConfig with provider "equity".
 */
final class EquityIntegrationConfig
{
    public const TOKEN_CACHE_KEY = 'equity_api_access_token';

    /** @return array<string, mixed> */
    public static function resolve(): array
    {
        return BankIntegrationConfig::resolve('equity');
    }

    public static function isConfigured(): bool
    {
        return BankIntegrationConfig::isConfigured('equity');
    }

    public static function syncEnabled(): bool
    {
        return BankIntegrationConfig::selectedProvider() === 'equity'
            && BankIntegrationConfig::syncEnabled();
    }

    public static function hasStoredPassword(): bool
    {
        return BankIntegrationConfig::hasStoredSecret('equity', 'password');
    }

    public static function hasStoredApiSecret(): bool
    {
        return BankIntegrationConfig::hasStoredSecret('equity', 'api_secret');
    }

    public static function hasStoredWebhookSecret(): bool
    {
        return BankIntegrationConfig::hasStoredSecret('equity', 'webhook_secret');
    }

    public static function forgetCachedToken(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget(self::TOKEN_CACHE_KEY);
            \Illuminate\Support\Facades\Cache::forget(BankIntegrationConfig::tokenCacheKey('equity'));
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function mergeForTest(array $overrides): array
    {
        return BankIntegrationConfig::mergeForTest('equity', $overrides);
    }
}
