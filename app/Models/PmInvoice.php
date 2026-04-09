<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmInvoice extends Model
{
    protected $table = 'pm_invoices';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';
    public const TYPE_RENT = 'rent';
    public const TYPE_WATER = 'water';
    public const TYPE_MIXED = 'mixed';

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
        'invoice_type',
        'billing_period',
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

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->whereIn('property_unit_id', function ($sub) use ($user) {
                $sub->select('pu.id')
                    ->from('property_units as pu')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->where('p.agent_user_id', $user->id);
            });
        });
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

    public static function nextInvoiceNumber(): string
    {
        $next = (int) (static::query()->withoutGlobalScopes()->max('id') ?? 0) + 1;

        while (true) {
            $candidate = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $exists = static::query()
                ->withoutGlobalScopes()
                ->where('invoice_no', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $next++;
        }
    }
}
