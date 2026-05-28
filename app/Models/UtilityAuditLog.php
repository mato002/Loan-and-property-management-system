<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityAuditLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'billing_month',
        'property_unit_id',
        'pm_tenant_id',
        'pm_invoice_id',
        'actor_user_id',
        'payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public static function record(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $context = [],
    ): self {
        return self::query()->create([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'billing_month' => $context['billing_month'] ?? null,
            'property_unit_id' => $context['property_unit_id'] ?? null,
            'pm_tenant_id' => $context['pm_tenant_id'] ?? null,
            'pm_invoice_id' => $context['pm_invoice_id'] ?? null,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'payload' => $context['payload'] ?? null,
            'notes' => $context['notes'] ?? null,
        ]);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
