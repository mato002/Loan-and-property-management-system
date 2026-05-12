<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PmInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'pm_invoices';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const TYPE_RENT = 'rent';

    public const TYPE_WATER = 'water';

    public const TYPE_MIXED = 'mixed';

    public const KIND_INVOICE = 'invoice';

    public const KIND_CREDIT_NOTE = 'credit_note';

    protected $fillable = [
        'pm_lease_id',
        'property_unit_id',
        'pm_tenant_id',
        'agent_user_id',
        'created_by_user_id',
        'invoice_no',
        'issue_date',
        'due_date',
        'amount',
        'amount_paid',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'sent_at',
        'sent_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancelled_reason',
        'invoice_type',
        'invoice_kind',
        'original_invoice_id',
        'billing_period',
        'description',
        'notes',
        'share_token',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

            $query->where(function (Builder $q) use ($user) {
                if (Schema::hasColumn('pm_invoices', 'agent_user_id')) {
                    $q->where('pm_invoices.agent_user_id', $user->id);
                }
                $q->orWhereIn('property_unit_id', function ($sub) use ($user) {
                    $sub->select('pu.id')
                        ->from('property_units as pu')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->where('p.agent_user_id', $user->id);
                });
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

    public function items(): HasMany
    {
        return $this->hasMany(PmInvoiceItem::class, 'pm_invoice_id')->orderBy('line_no');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PmInvoiceEvent::class, 'pm_invoice_id')->latest('occurred_at');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function balanceDue(): string
    {
        $b = (float) $this->amount - (float) $this->amount_paid;

        return number_format(max(0, $b), 2);
    }

    public function balanceFloat(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->amount_paid), 2);
    }

    public function isCreditNote(): bool
    {
        return (string) ($this->invoice_kind ?? self::KIND_INVOICE) === self::KIND_CREDIT_NOTE;
    }

    public function isOpen(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_PARTIAL,
            self::STATUS_OVERDUE,
        ], true);
    }

    public function refreshComputedStatus(): void
    {
        $due = (float) $this->amount;
        $paid = (float) $this->amount_paid;
        $previous = (string) $this->status;

        if ($paid >= $due) {
            $this->status = self::STATUS_PAID;
        } elseif ($paid > 0) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($previous === self::STATUS_DRAFT) {
            $this->saveQuietly();

            return;
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->status = self::STATUS_OVERDUE;
        } else {
            $this->status = self::STATUS_SENT;
        }
        $this->saveQuietly();

        // Surface status transitions in the event log so the activity panel
        // has a complete trail. We only log when the status actually changed
        // to avoid log spam from idempotent refreshes.
        if ($previous !== (string) $this->status) {
            $eventName = match ((string) $this->status) {
                self::STATUS_PAID => PmInvoiceEvent::EVENT_PAID,
                self::STATUS_PARTIAL => PmInvoiceEvent::EVENT_PARTIALLY_PAID,
                self::STATUS_OVERDUE => PmInvoiceEvent::EVENT_OVERDUE,
                default => null,
            };
            if ($eventName) {
                PmInvoiceEvent::record(
                    (int) $this->id,
                    $eventName,
                    null,
                    'Status: '.ucfirst((string) $this->status)
                );
            }
        }
    }

    public static function nextInvoiceNumber(): string
    {
        $next = (int) (static::query()->withoutGlobalScopes()->withTrashed()->max('id') ?? 0) + 1;

        while (true) {
            $candidate = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $exists = static::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->where('invoice_no', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $next++;
        }
    }

    /**
     * Generate (or return existing) opaque token for a public share / download
     * link. Stored on the row so the link is stable.
     */
    public function ensureShareToken(): string
    {
        if (! empty($this->share_token)) {
            return (string) $this->share_token;
        }

        $token = Str::random(40);
        $this->share_token = $token;
        $this->saveQuietly();

        return $token;
    }
}
