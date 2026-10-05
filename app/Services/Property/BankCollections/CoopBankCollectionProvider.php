<?php

namespace App\Services\Property\BankCollections;

use App\Support\Property\BankIntegrationConfig;
use App\Support\Property\BankIntegrationRegistry;

/**
 * Co-operative Bank collection adapter.
 *
 * Does not call the live bank API until official credentials and docs arrive.
 * Configuration and webhook architecture are prepared here.
 */
final class CoopBankCollectionProvider
{
    public const PROVIDER = 'coop';

    public function label(): string
    {
        return BankIntegrationRegistry::label(self::PROVIDER);
    }

    public function isSelected(): bool
    {
        return BankIntegrationConfig::selectedProvider() === self::PROVIDER;
    }

    /**
     * Soft readiness: collection till / paybill is set for tenant instructions.
     * Live API readiness still uses BankIntegrationConfig::isConfigured().
     */
    public function hasCollectionInstructions(): bool
    {
        $config = BankIntegrationConfig::resolve(self::PROVIDER);
        $till = trim((string) ($config['paybill_number'] ?: ($config['merchant_code'] ?? '')));

        return $till !== '';
    }

    public function isApiConfigured(): bool
    {
        return BankIntegrationConfig::isConfigured(self::PROVIDER);
    }

    /**
     * @return array{
     *     provider:string,
     *     label:string,
     *     environment:string,
     *     collection_number:string,
     *     base_url:string,
     *     client_id:string,
     *     has_client_secret:bool,
     *     has_api_key:bool,
     *     has_api_secret:bool,
     *     has_webhook_secret:bool,
     *     webhook_url:string,
     *     live_api_enabled:bool
     * }
     */
    public function publicConfig(): array
    {
        $config = BankIntegrationConfig::resolve(self::PROVIDER);

        return [
            'provider' => self::PROVIDER,
            'label' => $this->label(),
            'environment' => (string) ($config['environment'] ?? 'sandbox'),
            'collection_number' => trim((string) ($config['paybill_number'] ?: ($config['merchant_code'] ?? ''))),
            'base_url' => (string) ($config['base_url'] ?? ''),
            'client_id' => (string) ($config['client_id'] ?? $config['api_key'] ?? ''),
            'has_client_secret' => BankIntegrationConfig::hasStoredSecret(self::PROVIDER, 'client_secret')
                || BankIntegrationConfig::hasStoredSecret(self::PROVIDER, 'api_secret'),
            'has_api_key' => trim((string) ($config['api_key'] ?? '')) !== '',
            'has_api_secret' => BankIntegrationConfig::hasStoredSecret(self::PROVIDER, 'api_secret'),
            'has_webhook_secret' => BankIntegrationConfig::hasStoredSecret(self::PROVIDER, 'webhook_secret'),
            'webhook_url' => BankIntegrationConfig::webhookUrl(self::PROVIDER),
            // Real outbound Co-op API calls stay disabled until docs/credentials are confirmed.
            'live_api_enabled' => false,
        ];
    }

    /**
     * Placeholder for future authenticated Co-op API calls.
     *
     * @return never
     */
    public function fetchTransactions(): never
    {
        throw new \RuntimeException(
            'Co-operative Bank live API sync is not enabled yet. Configure the webhook and wait for official API documentation and credentials.'
        );
    }
}
