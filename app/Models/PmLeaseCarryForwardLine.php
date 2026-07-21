<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmLeaseCarryForwardLine extends Model
{
    public const STATUS_UNINVOICED = 'uninvoiced';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_RETIRED = 'retired';

    public const SOURCE_LEASE_JSON = 'lease_json';

    public const SOURCE_TENANT_OPENING = 'tenant_opening';

    public const SOURCE_RENEWAL = 'renewal';

    protected $table = 'pm_lease_carry_forward_lines';

    protected $fillable = [
        'pm_lease_id',
        'pm_tenant_id',
        'row_key',
        'charge_type',
        'specific_charge',
        'period',
        'amount',
        'carry_forward_status',
        'invoiced_amount',
        'pm_invoice_ids',
        'source',
        'superseded_by_lease_id',
        'captured_at',
        'invoiced_at',
        'settled_at',
        'retired_at',
        'audit_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'invoiced_amount' => 'decimal:2',
            'pm_invoice_ids' => 'array',
            'audit_payload' => 'array',
            'captured_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'settled_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(PmLease::class, 'pm_lease_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function isActiveForDue(): bool
    {
        return $this->carry_forward_status === self::STATUS_UNINVOICED;
    }
}
