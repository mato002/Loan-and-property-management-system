<?php

namespace App\Support\Property;

final class BankIntegrationRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function providers(): array
    {
        $providers = config('property_banks.providers', []);

        return is_array($providers) ? $providers : [];
    }

    /**
     * @return list<array{id:string,label:string,auth_type:string,sync_driver:?string,supports_webhook:bool,has_auto_sync:bool}>
     */
    public static function optionsForUi(): array
    {
        $options = [];
        foreach (self::providers() as $id => $meta) {
            if (! is_string($id) || $id === '' || ! is_array($meta)) {
                continue;
            }
            $syncDriver = $meta['sync_driver'] ?? null;
            $options[] = [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? strtoupper($id)),
                'auth_type' => (string) ($meta['auth_type'] ?? 'api_key'),
                'sync_driver' => is_string($syncDriver) && $syncDriver !== '' ? $syncDriver : null,
                'supports_webhook' => (bool) ($meta['supports_webhook'] ?? true),
                'has_auto_sync' => is_string($syncDriver) && $syncDriver !== '',
            ];
        }

        return $options;
    }

    public static function isValidProvider(string $provider): bool
    {
        return array_key_exists($provider, self::providers());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function provider(string $provider): ?array
    {
        $meta = self::providers()[$provider] ?? null;

        return is_array($meta) ? $meta : null;
    }

    public static function label(string $provider): string
    {
        return (string) (self::provider($provider)['label'] ?? strtoupper($provider));
    }

    public static function syncDriver(string $provider): ?string
    {
        $driver = self::provider($provider)['sync_driver'] ?? null;

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    public static function authType(string $provider): string
    {
        return (string) (self::provider($provider)['auth_type'] ?? 'api_key');
    }

    public static function supportsWebhook(string $provider): bool
    {
        return (bool) (self::provider($provider)['supports_webhook'] ?? true);
    }
}
