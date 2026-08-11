<?php

namespace App\Services\Property;

use App\Models\PmAccountingAuditLog;
use App\Models\PmActivityLog;
use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoiceEvent;
use App\Models\PmMessageLog;
use App\Models\PmPortalAction;
use App\Models\User;
use App\Models\UtilityAuditLog;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PropertyActivityLogQueryService
{
    /** @var array<string, string> */
    public const SOURCE_LABELS = [
        'activity' => 'Activity log',
        'portal' => 'Portal action',
        'finance' => 'Finance audit',
        'accounting' => 'Accounting audit',
        'utility' => 'Utility billing',
        'invoice' => 'Invoice event',
        'login' => 'Login / access',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $entries = $this->collect($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 30)));
        $total = $entries->count();
        $items = $entries->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectForExport(array $filters, int $limit = 5000): Collection
    {
        return $this->collect(array_merge($filters, ['export_limit' => $limit]));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function collect(array $filters): Collection
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $source = strtolower(trim((string) ($filters['source'] ?? '')));
        $q = trim((string) ($filters['q'] ?? ''));
        $userId = (int) ($filters['user_id'] ?? 0);
        $exportLimit = (int) ($filters['export_limit'] ?? 0);
        $perSourceLimit = $exportLimit > 0 ? min(500, $exportLimit) : 150;

        $entries = collect();

        if ($source === '' || $source === 'activity') {
            $entries = $entries->merge($this->fromActivityLogs($from, $to, $q, $userId, $perSourceLimit));
        }
        if ($source === '' || $source === 'portal') {
            $entries = $entries->merge($this->fromPortalActions($from, $to, $q, $userId, $perSourceLimit));
        }
        if ($source === '' || $source === 'finance') {
            $entries = $entries->merge($this->fromFinanceAuditLogs($from, $to, $q, $userId, $perSourceLimit));
        }
        if ($source === '' || $source === 'accounting') {
            $entries = $entries->merge($this->fromAccountingAuditLogs($from, $to, $q, $userId, $perSourceLimit));
        }
        if ($source === '' || $source === 'utility') {
            $entries = $entries->merge($this->fromUtilityAuditLogs($from, $to, $q, $userId, $perSourceLimit));
        }
        if ($source === '' || $source === 'invoice') {
            $entries = $entries->merge($this->fromInvoiceEvents($from, $to, $q, $userId, min(100, $perSourceLimit)));
        }
        if ($source === '' || $source === 'login') {
            $entries = $entries->merge($this->fromLoginLogs($from, $to, $q, $userId, $perSourceLimit));
        }

        $sorted = $entries
            ->sortByDesc(fn (array $entry): int => (int) ($entry['occurred_at_ts'] ?? 0))
            ->values();

        if ($exportLimit > 0) {
            return $sorted->take($exportLimit)->values();
        }

        return $sorted;
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveDateRange(array $filters): array
    {
        $fromRaw = trim((string) ($filters['from'] ?? ''));
        $toRaw = trim((string) ($filters['to'] ?? ''));

        if ($fromRaw !== '' && $toRaw !== '') {
            return [
                Carbon::parse($fromRaw)->startOfDay(),
                Carbon::parse($toRaw)->endOfDay(),
            ];
        }

        return [now()->subDays(30)->startOfDay(), now()->endOfDay()];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromActivityLogs(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_activity_logs')) {
            return collect();
        }

        $query = PmActivityLog::query()
            ->with('actor:id,name,email')
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('actor_user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('summary', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('source', 'like', $like);
            });
        }

        return $query->get()->map(fn (PmActivityLog $row) => $this->normalizeRow(
            'activity',
            (int) $row->id,
            $row->occurred_at ?? $row->created_at,
            (string) ($row->actor?->name ?? 'System'),
            (int) ($row->actor_user_id ?? 0),
            (string) $row->action,
            (string) $row->summary,
            (string) ($row->entity_type ?? ''),
            (int) ($row->entity_id ?? 0),
            is_array($row->payload) ? $row->payload : [],
            $this->resolveEntityUrl($row->entity_type, (int) ($row->entity_id ?? 0), (int) ($row->pm_invoice_id ?? 0), (int) ($row->pm_lease_id ?? 0), (int) ($row->pm_tenant_id ?? 0)),
        ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromPortalActions(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_portal_actions')) {
            return collect();
        }

        $query = PmPortalAction::query()
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('action_key', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('portal_role', 'like', $like);
            });
        }

        return $query->get()->map(function (PmPortalAction $row) {
            $summary = trim((string) ($row->notes ?: str_replace('_', ' ', (string) $row->action_key)));

            return $this->normalizeRow(
                'portal',
                (int) $row->id,
                $row->created_at,
                (string) ($row->user?->name ?? 'System'),
                (int) ($row->user_id ?? 0),
                (string) $row->action_key,
                $summary !== '' ? $summary : (string) $row->action_key,
                'portal_action',
                (int) $row->id,
                is_array($row->context) ? $row->context : [],
                null,
                ucfirst((string) ($row->portal_role ?? 'portal')),
            );
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromFinanceAuditLogs(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_finance_audit_logs')) {
            return collect();
        }

        $query = PmFinanceAuditLog::query()
            ->with('actor:id,name,email')
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('actor_user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('summary', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like);
            });
        }

        return $query->get()->map(fn (PmFinanceAuditLog $row) => $this->normalizeRow(
            'finance',
            (int) $row->id,
            $row->occurred_at ?? $row->created_at,
            (string) ($row->actor?->name ?? 'System'),
            (int) ($row->actor_user_id ?? 0),
            (string) $row->action,
            (string) ($row->summary ?: str_replace('_', ' ', (string) $row->action)),
            (string) ($row->entity_type ?? ''),
            (int) ($row->entity_id ?? 0),
            is_array($row->payload) ? $row->payload : [],
            $this->resolveEntityUrl($row->entity_type, (int) ($row->entity_id ?? 0), (int) ($row->pm_invoice_id ?? 0), (int) ($row->pm_lease_id ?? 0), (int) ($row->pm_tenant_id ?? 0)),
        ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromAccountingAuditLogs(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_accounting_audit_logs')) {
            return collect();
        }

        $query = PmAccountingAuditLog::query()
            ->with('actor:id,name,email')
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('actor_user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('summary', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like);
            });
        }

        return $query->get()->map(fn (PmAccountingAuditLog $row) => $this->normalizeRow(
            'accounting',
            (int) $row->id,
            $row->occurred_at ?? $row->created_at,
            (string) ($row->actor?->name ?? 'System'),
            (int) ($row->actor_user_id ?? 0),
            (string) $row->action,
            (string) ($row->summary ?: str_replace('_', ' ', (string) $row->action)),
            (string) ($row->entity_type ?? ''),
            (int) ($row->entity_id ?? 0),
            is_array($row->payload) ? $row->payload : [],
            $this->resolveEntityUrl($row->entity_type, (int) ($row->entity_id ?? 0), (int) ($row->pm_invoice_id ?? 0), (int) ($row->pm_lease_id ?? 0), (int) ($row->pm_tenant_id ?? 0)),
        ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromUtilityAuditLogs(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('utility_audit_logs')) {
            return collect();
        }

        $query = UtilityAuditLog::query()
            ->with('actor:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('actor_user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('notes', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like);
            });
        }

        return $query->get()->map(fn (UtilityAuditLog $row) => $this->normalizeRow(
            'utility',
            (int) $row->id,
            $row->created_at,
            (string) ($row->actor?->name ?? 'System'),
            (int) ($row->actor_user_id ?? 0),
            (string) $row->action,
            (string) ($row->notes ?: str_replace('_', ' ', (string) $row->action)),
            (string) ($row->entity_type ?? ''),
            (int) ($row->entity_id ?? 0),
            is_array($row->payload) ? $row->payload : [],
            $this->resolveEntityUrl($row->entity_type, (int) ($row->entity_id ?? 0), (int) ($row->pm_invoice_id ?? 0), 0, (int) ($row->pm_tenant_id ?? 0)),
        ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromInvoiceEvents(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_invoice_events')) {
            return collect();
        }

        $query = PmInvoiceEvent::query()
            ->with(['user:id,name,email', 'invoice:id,invoice_no,pm_lease_id,pm_tenant_id'])
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('summary', 'like', $like)
                    ->orWhere('event', 'like', $like);
            });
        }

        return $query->get()->map(function (PmInvoiceEvent $row) {
            $invoiceNo = (string) ($row->invoice?->invoice_no ?? '');
            $summary = trim((string) ($row->summary ?: ucfirst(str_replace('_', ' ', (string) $row->event))));
            if ($invoiceNo !== '') {
                $summary = $invoiceNo.($summary !== '' ? ' — '.$summary : '');
            }

            return $this->normalizeRow(
                'invoice',
                (int) $row->id,
                $row->occurred_at ?? $row->created_at,
                (string) ($row->user?->name ?? 'System'),
                (int) ($row->user_id ?? 0),
                (string) $row->event,
                $summary,
                'pm_invoice',
                (int) ($row->pm_invoice_id ?? 0),
                is_array($row->payload) ? $row->payload : [],
                $row->pm_invoice_id ? route('property.revenue.invoices.show', ['invoice' => $row->pm_invoice_id], false) : null,
            );
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fromLoginLogs(Carbon $from, Carbon $to, string $q, int $userId, int $limit): Collection
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return collect();
        }

        $query = PmMessageLog::query()
            ->with('user:id,name,email')
            ->where('channel', 'system')
            ->where('subject', 'like', '[LOGIN]%')
            ->whereBetween('sent_at', [$from, $to])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('subject', 'like', $like)
                    ->orWhere('body', 'like', $like)
                    ->orWhere('to_address', 'like', $like);
            });
        }

        return $query->get()->map(fn (PmMessageLog $row) => $this->normalizeRow(
            'login',
            (int) $row->id,
            $row->sent_at ?? $row->created_at,
            (string) ($row->user?->name ?? 'Unknown user'),
            (int) ($row->user_id ?? 0),
            'login',
            trim((string) ($row->subject ?: 'User login')),
            'user',
            (int) ($row->user_id ?? 0),
            ['delivery_status' => $row->delivery_status, 'body' => $row->body],
            null,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeRow(
        string $source,
        int $id,
        mixed $occurredAt,
        string $actorName,
        int $actorUserId,
        string $action,
        string $summary,
        string $entityType,
        int $entityId,
        array $payload,
        ?string $url,
        ?string $roleLabel = null,
    ): array {
        $at = $occurredAt instanceof Carbon ? $occurredAt : Carbon::parse((string) $occurredAt);

        return [
            'uid' => $source.':'.$id,
            'source' => $source,
            'source_label' => self::SOURCE_LABELS[$source] ?? ucfirst($source),
            'occurred_at' => $at,
            'occurred_at_ts' => $at->timestamp,
            'occurred_at_label' => $at->format('Y-m-d H:i'),
            'actor_name' => $actorName !== '' ? $actorName : 'System',
            'actor_user_id' => $actorUserId,
            'role_label' => $roleLabel,
            'action' => $action,
            'summary' => $summary,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'url' => $url,
            'detail_preview' => $this->payloadPreview($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadPreview(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        if (isset($payload['changes']) && is_array($payload['changes'])) {
            $parts = [];
            foreach ($payload['changes'] as $field => $diff) {
                if (! is_array($diff)) {
                    continue;
                }
                $parts[] = str_replace('_', ' ', (string) $field).': '.($diff['from'] ?? '—').' → '.($diff['to'] ?? '—');
            }
            if ($parts !== []) {
                return implode(' · ', $parts);
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return null;
        }

        return Str::length($json) > 180 ? Str::limit($json, 180) : $json;
    }

    private function resolveEntityUrl(
        ?string $entityType,
        int $entityId,
        int $invoiceId,
        int $leaseId,
        int $tenantId,
    ): ?string {
        if ($invoiceId > 0) {
            return route('property.revenue.invoices.show', ['invoice' => $invoiceId], false);
        }

        if ($leaseId > 0) {
            return route('property.leases.show', ['lease' => $leaseId], false);
        }

        if ($tenantId > 0) {
            return route('property.tenants.show', ['tenant' => $tenantId], false);
        }

        $type = strtolower(trim((string) $entityType));
        if ($entityId <= 0) {
            return null;
        }

        return match ($type) {
            'pm_invoice', 'invoice' => route('property.revenue.invoices.show', ['invoice' => $entityId], false),
            'pm_lease', 'lease' => route('property.leases.show', ['lease' => $entityId], false),
            'pm_tenant', 'tenant' => route('property.tenants.show', ['tenant' => $entityId], false),
            default => null,
        };
    }

    /**
     * @return Collection<int, User>
     */
    public function actorOptions(): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        return User::query()
            ->whereNotNull('property_portal_role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'property_portal_role']);
    }
}
