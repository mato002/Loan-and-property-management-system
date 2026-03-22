<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmInvoice extends Model
{
    protected $table = 'pm_invoices';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'pm_lease_id',
        'property_unit_id',
        'pm_tenant_id',
        'invoice_no',
        'issue_date',
        'due_date',
        'amount',
        'amount_paid',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(PmLease::class, 'pm_lease_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PmPaymentAllocation::class, 'pm_invoice_id');
    }

    public function balanceDue(): string
    {
        $b = (float) $this->amount - (float) $this->amount_paid;

        return number_format(max(0, $b), 2);
    }

    public function refreshComputedStatus(): void
    {
        $due = (float) $this->amount;
        $paid = (float) $this->amount_paid;
        if ($paid >= $due) {
            $this->status = self::STATUS_PAID;
        } elseif ($paid > 0) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($this->status === self::STATUS_DRAFT) {
            $this->saveQuietly();

            return;
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = self::STATUS_OVERDUE;
        } else {
            $this->status = self::STATUS_SENT;
        }
        $this->saveQuietly();
    }
}
