<?php

namespace App\Services\Property;

use App\Models\PmActivityLog;
use App\Models\PmLease;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class PropertyActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $action, string $summary, array $context = []): void
    {
        try {
            if (! Schema::hasTable('pm_activity_logs')) {
                return;
            }

            $actor = auth()->user();

            PmActivityLog::query()->create([
                'actor_user_id' => $context['actor_user_id'] ?? $actor?->id,
                'portal_role' => $context['portal_role'] ?? $actor?->property_portal_role,
                'source' => (string) ($context['source'] ?? 'system'),
                'action' => $action,
                'summary' => mb_substr(trim($summary), 0, 500),
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => isset($context['entity_id']) ? (int) $context['entity_id'] : null,
                'pm_lease_id' => isset($context['pm_lease_id']) ? (int) $context['pm_lease_id'] : null,
                'pm_tenant_id' => isset($context['pm_tenant_id']) ? (int) $context['pm_tenant_id'] : null,
                'pm_invoice_id' => isset($context['pm_invoice_id']) ? (int) $context['pm_invoice_id'] : null,
                'payload' => $context['payload'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function settingsChanged(string $section, string $summary, array $payload = []): void
    {
        self::record('settings.updated', $summary, [
            'source' => 'settings',
            'entity_type' => 'settings_section',
            'payload' => array_merge(['section' => $section], $payload),
        ]);
    }

    /**
     * @param  array<string, array{from:mixed,to:mixed}>  $changes
     */
    public static function leaseUpdated(PmLease $lease, array $changes, ?User $actor = null): void
    {
        if ($changes === []) {
            return;
        }

        $parts = [];
        foreach ($changes as $field => $diff) {
            $from = self::stringifyChangeValue($diff['from'] ?? null);
            $to = self::stringifyChangeValue($diff['to'] ?? null);
            $parts[] = str_replace('_', ' ', (string) $field).': '.$from.' → '.$to;
        }

        self::record('lease.updated', 'Lease #'.$lease->id.' updated — '.implode('; ', $parts), [
            'source' => 'lease',
            'entity_type' => 'pm_lease',
            'entity_id' => (int) $lease->id,
            'pm_lease_id' => (int) $lease->id,
            'pm_tenant_id' => (int) ($lease->pm_tenant_id ?? 0) ?: null,
            'payload' => ['changes' => $changes],
            'actor_user_id' => $actor?->id,
            'portal_role' => $actor?->property_portal_role,
        ]);
    }

    private static function stringifyChangeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return (string) $value;
    }
}
