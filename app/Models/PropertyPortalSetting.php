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
            $value = PropertyWorkspaceBranding::get($key, $default);
            if (in_array($key, ['company_logo_url', 'site_favicon_url'], true)) {
                $resolved = PropertyWorkspaceBranding::resolveAssetUrl($value);

                return $resolved !== '' ? $resolved : $default;
            }

            return $value;
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
            'workflow_auto_invoice_delivery',
            'workflow_auto_payment_receipts',
            'workflow_auto_scheduled_dispatch',
            'workflow_auto_sms_retry',
            'workflow_auto_landlord_alerts',
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
        if ($env === false) {
            return false;
        }

        $existsQuery = static::query()->where('key', $granularKey);
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $existsQuery->whereNull('agent_user_id');
        }
        if ($existsQuery->exists()) {
            return static::getGlobalValue($granularKey, '0') === '1';
        }

        if ($env === true) {
            return true;
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

    public static function isInvoiceDeliveryAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_invoice_delivery');
    }

    public static function isScheduledDispatchAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_scheduled_dispatch');
    }

    public static function isSmsRetryAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_sms_retry');
    }

    public static function isLandlordAlertAutomationEnabled(): bool
    {
        return self::granularAutomationEnabled('workflow_auto_landlord_alerts');
    }

    public static function isPaymentReceiptAutomationEnabled(): bool
    {
        $env = self::workflowAutomationEnvOverride();
        if ($env === false) {
            return false;
        }

        $existsQuery = static::query()->where('key', 'workflow_auto_payment_receipts');
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $existsQuery->whereNull('agent_user_id');
        }
        if ($existsQuery->exists()) {
            return static::getGlobalValue('workflow_auto_payment_receipts', '0') === '1';
        }

        // Default to the Payment settings toggle when the schedules switch has never been saved.
        return \App\Support\Property\MpesaIntegrationConfig::autoReceiptEnabled();
    }

    /**
     * Schedulers the operator can turn on or off from Communications → Schedules.
     *
     * @return list<array{key: string, group: string, label: string, command: string, when: string, sends: string, enabled: bool}>
     */
    public static function scheduledAutomationCatalog(): array
    {
        $rows = [
            ['key' => 'workflow_auto_rent_reminders', 'group' => 'Messages', 'label' => 'Rent reminders', 'command' => 'rent:send-reminders', 'when' => 'Daily 08:00', 'sends' => 'SMS and email by due-date stage (3 days before, 1 day before, due today, overdue).', 'enabled' => self::isRentReminderAutomationEnabled()],
            ['key' => 'workflow_auto_invoice_delivery', 'group' => 'Messages', 'label' => 'Invoice delivery', 'command' => 'invoices:deliver-pending', 'when' => 'Daily 08:30', 'sends' => 'Email and SMS for issued invoices that have not been delivered yet.', 'enabled' => self::isInvoiceDeliveryAutomationEnabled()],
            ['key' => 'workflow_auto_payment_receipts', 'group' => 'Messages', 'label' => 'Payment received feedback', 'command' => 'payments:dispatch-pending-receipts', 'when' => 'On payment + every 10 minutes', 'sends' => 'SMS/email confirmation when a tenant payment is received, including amount, reference, and what it paid (rent, water, etc.).', 'enabled' => self::isPaymentReceiptAutomationEnabled()],
            ['key' => 'workflow_auto_scheduled_dispatch', 'group' => 'Messages', 'label' => 'Scheduled campaigns', 'command' => 'communications:dispatch-scheduled', 'when' => 'Every 5 minutes', 'sends' => 'Releases bulk SMS and email that were scheduled for a later time.', 'enabled' => self::isScheduledDispatchAutomationEnabled()],
            ['key' => 'workflow_auto_sms_retry', 'group' => 'Messages', 'label' => 'Failed SMS retry', 'command' => 'communications:retry-failed-sms', 'when' => 'Every 15 minutes', 'sends' => 'Retries SMS that failed because the wallet was empty or the provider was busy.', 'enabled' => self::isSmsRetryAutomationEnabled()],
            ['key' => 'workflow_auto_landlord_alerts', 'group' => 'Messages', 'label' => 'Landlord portal alerts', 'command' => 'landlord:send-portal-alerts', 'when' => 'Daily 07:00', 'sends' => 'Email and SMS digest to landlords who opted in.', 'enabled' => self::isLandlordAlertAutomationEnabled()],
            ['key' => 'workflow_auto_rent_invoices', 'group' => 'Billing', 'label' => 'Rent invoices', 'command' => 'rent:generate-invoices', 'when' => 'Daily 00:15', 'sends' => 'Creates the month’s rent invoices. Delivery is a separate switch.', 'enabled' => self::isRentInvoiceAutomationEnabled()],
            ['key' => 'workflow_auto_water_invoices', 'group' => 'Billing', 'label' => 'Water invoices', 'command' => 'water:generate-invoices', 'when' => 'Daily 00:25', 'sends' => 'Creates water invoices from readings.', 'enabled' => self::isWaterInvoiceAutomationEnabled()],
            ['key' => 'workflow_auto_attached_utility_charges', 'group' => 'Billing', 'label' => 'Attached charges', 'command' => 'utility:materialize-attached-charges', 'when' => 'Daily 00:22', 'sends' => 'Creates garbage, service charge, and similar monthly lines.', 'enabled' => self::isAttachedUtilityChargeAutomationEnabled()],
            ['key' => 'workflow_auto_water_penalties', 'group' => 'Billing', 'label' => 'Water penalties', 'command' => 'water:apply-penalties', 'when' => 'Daily 00:40', 'sends' => 'Adds penalties on overdue water balances.', 'enabled' => self::isWaterPenaltyAutomationEnabled()],
        ];

        return $rows;
    }

    /** @return list<string> */
    public static function scheduledAutomationKeys(): array
    {
        return array_column(self::scheduledAutomationCatalog(), 'key');
    }
}
