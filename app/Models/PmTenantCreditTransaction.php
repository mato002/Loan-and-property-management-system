<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTenantCreditTransaction extends Model
{
    public const TYPE_CREDIT_CREATED = 'credit_created';

    public const TYPE_CREDIT_APPLIED = 'credit_applied';

    public const TYPE_CREDIT_REFUNDED = 'credit_refunded';

    public const TYPE_CREDIT_REVERSED = 'credit_reversed';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const MODE_AUTO = 'automatic';

    public const MODE_MANUAL = 'manual';

    protected $fillable = [
        'pm_tenant_id',
        'pm_payment_id',
        'pm_invoice_id',
        'type',
        'amount',
        'reference',
        'notes',
        'application_mode',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CREDIT_CREATED => 'Advance created',
            self::TYPE_CREDIT_APPLIED => 'Applied to invoice',
            self::TYPE_CREDIT_REFUNDED => 'Refunded',
            self::TYPE_CREDIT_REVERSED => 'Reversed',
            self::TYPE_MANUAL_ADJUSTMENT => 'Manual adjustment',
            default => ucfirst(str_replace('_', ' ', (string) $this->type)),
        };
    }
}
