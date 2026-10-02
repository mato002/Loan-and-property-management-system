<?php

namespace App\Services\Property;

use App\Models\Employee;
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
            $actorId = $context['actor_user_id'] ?? $actor?->id;
            $staff = self::staffContext(is_numeric($actorId) ? (int) $actorId : null);
            $payload = $context['payload'] ?? null;
            if (is_array($payload) || $payload === null) {
                $payload = array_merge($staff, is_array($payload) ? $payload : []);
            }

            $row = [
                'actor_user_id' => $actorId,
                'portal_role' => $context['portal_role'] ?? $actor?->property_portal_role,
                'source' => (string) ($context['source'] ?? 'system'),
                'action' => $action,
                'summary' => mb_substr(trim($summary), 0, 500),
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => isset($context['entity_id']) ? (int) $context['entity_id'] : null,
                'pm_lease_id' => isset($context['pm_lease_id']) ? (int) $context['pm_lease_id'] : null,
                'pm_tenant_id' => isset($context['pm_tenant_id']) ? (int) $context['pm_tenant_id'] : null,
                'pm_invoice_id' => isset($context['pm_invoice_id']) ? (int) $context['pm_invoice_id'] : null,
                'payload' => $payload,
                'occurred_at' => $context['occurred_at'] ?? now(),
            ];
            if (Schema::hasColumn('pm_activity_logs', 'employee_id')) {
                $row['employee_id'] = $staff['employee_id'];
            }
            if (Schema::hasColumn('pm_activity_logs', 'employee_role')) {
                $row['employee_role'] = $staff['employee_role'];
            }

            PmActivityLog::query()->create($row);
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

    /**
     * @return array{employee_id: ?int, employee_role: ?string}
     */
    private static function staffContext(?int $userId): array
    {
        if (! $userId || ! Schema::hasTable('employees')) {
            return ['employee_id' => null, 'employee_role' => null];
        }

        $employee = Employee::query()->where('user_id', $userId)->first(['id', 'job_title']);
        $roles = null;
        if (Schema::hasTable('pm_user_role') && Schema::hasTable('pm_roles')) {
            $roles = \Illuminate\Support\Facades\DB::table('pm_user_role as ur')
                ->join('pm_roles as r', 'r.id', '=', 'ur.pm_role_id')
                ->where('ur.user_id', $userId)
                ->orderBy('r.name')
                ->pluck('r.name')
                ->filter()
                ->join(', ');
        }

        $role = trim((string) $roles);
        if ($role === '') {
            $role = trim((string) ($employee?->job_title ?? ''));
        }

        return [
            'employee_id' => $employee?->id,
            'employee_role' => $role !== '' ? $role : null,
        ];
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
