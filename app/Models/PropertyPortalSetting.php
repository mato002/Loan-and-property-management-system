<?php

namespace App\Models;

use App\Services\Property\PropertyDashboardCache;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PropertyPortalSetting extends Model
{
    protected $table = 'property_portal_settings';

    /** @var array<string, ?string> */
    private static array $valueCache = [];

    protected $fillable = [
        'agent_user_id',
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (PropertyWorkspaceBranding::isBrandingKey($key) && Auth::check()) {
            return PropertyWorkspaceBranding::get($key, $default);
        }

        return static::getGlobalValue($key, $default);
    }

    public static function getGlobalValue(string $key, ?string $default = null): ?string
    {
        $cacheKey = 'global|'.$key;
        if (! array_key_exists($cacheKey, static::$valueCache)) {
            $query = static::query()->where('key', $key);
            if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
                $query->whereNull('agent_user_id');
            }
            static::$valueCache[$cacheKey] = $query->value('value');
        }

        return static::$valueCache[$cacheKey] ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        if (PropertyWorkspaceBranding::isBrandingKey($key)) {
            PropertyWorkspaceBranding::set($key, $value);
            static::$valueCache[static::brandingCacheKey($key)] = $value;
            PropertyDashboardCache::forgetAll();

            return;
        }

        static::setGlobalValue($key, $value);
    }

    public static function setGlobalValue(string $key, ?string $value): void
    {
        $attributes = ['key' => $key];
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $attributes['agent_user_id'] = null;
        }

        static::query()->updateOrCreate($attributes, ['value' => $value]);
        static::$valueCache['global|'.$key] = $value;
        PropertyDashboardCache::forgetAll();
    }

    private static function brandingCacheKey(string $key): string
    {
        return $key.'|'.PropertyWorkspaceBranding::cacheScopeKey();
    }

    /**
     * Global env override for all scheduled property automation.
     * Null means "not set" — use database rules.
     */
    public static function workflowAutomationEnvOverride(): ?bool
    {
        $override = config('property.workflow_automation_enabled');
        if ($override === null || $override === '') {
            return null;
        }

        return filter_var($override, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the legacy single checkbox (workflow_auto_reminders) is on.
     * Granular keys fall back to this when they have never been saved.
     *
     * @see config/property.php PROPERTY_WORKFLOW_AUTOMATION_ENABLED
     */
    public static function isWorkflowAutomationEnabled(): bool
    {
        $env = self::workflowAutomationEnvOverride();
        if ($env !== null) {
            return $env;
        }

        return static::getGlobalValue('workflow_auto_reminders', '0') === '1';
    }

    /**
     * True when any rent/water/reminder/penalty automation is enabled
     * (used for high-level "scheduler active" hints in the UI).
     */
    public static function isAnyScheduledPropertyAutomationOn(): bool
    {
        $env = self::workflowAutomationEnvOverride();
        if ($env !== null) {
            return $env;
        }

        if (static::getGlobalValue('workflow_auto_reminders', '0') === '1') {
            return true;
        }

        foreach ([
            'workflow_auto_rent_invoices',
            'workflow_auto_water_invoices',
            'workflow_auto_rent_reminders',
            'workflow_auto_water_penalties',
            'workflow_auto_attached_utility_charges',
        ] as $key) {
            $query = static::query()->where('key', $key)->where('value', '1');
            if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
                $query->whereNull('agent_user_id');
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function granularAutomationEnabled(string $granularKey): bool
    {
        $env = self::workflowAutomationEnvOverride();
        if ($env !== null) {
            return $env;
        }

        $existsQuery = static::query()->where('key', $granularKey);
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $existsQuery->whereNull('agent_user_id');
        }
        if ($existsQuery->exists()) {
            return static::getGlobalValue($granularKey, '0') === '1';
        }

        return static::getGlobalValue('workflow_auto_reminders', '0') === '1';
    }

    public static function isRentInvoiceAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_rent_invoices');
    }

    public static function isWaterInvoiceAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_water_invoices');
    }

    public static function isRentReminderAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_rent_reminders');
    }

    public static function isWaterPenaltyAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_water_penalties');
    }

    public static function isAttachedUtilityChargeAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_attached_utility_charges');
    }
}
