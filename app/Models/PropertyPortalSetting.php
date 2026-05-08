<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPortalSetting extends Model
{
    protected $table = 'property_portal_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
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

        return static::getValue('workflow_auto_reminders', '0') === '1';
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

        if (static::getValue('workflow_auto_reminders', '0') === '1') {
            return true;
        }

        foreach ([
            'workflow_auto_rent_invoices',
            'workflow_auto_water_invoices',
            'workflow_auto_rent_reminders',
            'workflow_auto_water_penalties',
        ] as $key) {
            if (static::query()->where('key', $key)->where('value', '1')->exists()) {
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

        if (static::query()->where('key', $granularKey)->exists()) {
            return static::getValue($granularKey, '0') === '1';
        }

        return static::getValue('workflow_auto_reminders', '0') === '1';
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
}
