<?php

namespace App\Services\Property;

use Illuminate\Support\Facades\Cache;

final class PropertyDashboardCache
{
    private const VERSION_KEY = 'property.dashboard.cache_version';

    private const SMS_BALANCE_KEY = 'property.dashboard.sms_provider_balance';

    private const LEASES_FORM_CONTEXT_VERSION_KEY = 'property.leases.form_context_version';

    public static function overviewKey(int $userId, bool $agentScoped): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return sprintf(
            'property.dashboard.overview:%d:%s:v%d',
            $userId,
            $agentScoped ? 'agent' : 'all',
            $version,
        );
    }

    public static function leasesFormContextKey(): string
    {
        $version = (int) Cache::get(self::LEASES_FORM_CONTEXT_VERSION_KEY, 1);

        return 'property.leases.form_context:v'.$version;
    }

    public static function forgetLeasesFormContext(): void
    {
        $leasesVersion = (int) Cache::get(self::LEASES_FORM_CONTEXT_VERSION_KEY, 1);
        Cache::forever(self::LEASES_FORM_CONTEXT_VERSION_KEY, $leasesVersion + 1);
    }

    public static function forgetAll(): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        Cache::forever(self::VERSION_KEY, $version + 1);
        Cache::forget(self::SMS_BALANCE_KEY);

        self::forgetLeasesFormContext();
    }

    public static function smsProviderBalanceKey(): string
    {
        return self::SMS_BALANCE_KEY;
    }
}
