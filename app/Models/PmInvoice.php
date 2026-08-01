<?php

namespace App\Models;

use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\InvoiceStateIntegrityService;
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

    /** Only syncAmountPaidFromAllocations() may write amount_paid on existing rows. */
    public static bool $allowDirectAmountPaidWrite = false;

    /** @var array<int, float> */
    private static array $allocatedAmountMemo = [];

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

    public const KIND_RENT_SUPPLEMENT = 'rent_supplement';

    public const KIND_WATER_SUPPLEMENT = 'water_supplement';

    public const LEASE_OPENING_ARREARS_PREFIX = '[Lease Opening Arrears]';

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
        'is_past_due',
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
        'carry_forward_origin',
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
            'balance_due' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_past_due' => 'boolean',
            'carry_forward_origin' => 'array',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice) {
            $invoice->syncDerivedBalanceDue();

            if (! $invoice->exists || ! $invoice->isDirty('amount_paid') || static::$allowDirectAmountPaidWrite) {
                return;
            }

            $attempted = round((float) $invoice->amount_paid, 2);
            $derived = min($invoice->allocatedAmount(), round((float) $invoice->amount, 2));

            if (abs($attempted - $derived) > 0.009) {
                PmFinanceAuditLog::record(
                    PmFinanceAuditLog::ACTION_AMOUNT_PAID_MANUAL_WRITE,
                    'pm_invoice',
                    (int) $invoice->id,
                    [
                        'pm_invoice_id' => (int) $invoice->id,
                        'pm_tenant_id' => (int) ($invoice->pm_tenant_id ?? 0),
                        'pm_lease_id' => (int) ($invoice->pm_lease_id ?? 0),
                        'summary' => sprintf(
                            'Blocked direct amount_paid write on %s: attempted KES %s, derived KES %s',
                            (string) ($invoice->invoice_no ?? '#'.$invoice->id),
                            number_format($attempted, 2),
                            number_format($derived, 2),
                        ),
                        'payload' => [
                            'attempted' => $attempted,
                            'derived' => $derived,
                            'allocated_sum' => $invoice->allocatedAmount(),
                        ],
                    ]
                );
            }

            FinanceFirebreakService::$skipAmountPaidAudit = true;
            try {
                $invoice->amount_paid = $derived;
            } finally {
                FinanceFirebreakService::$skipAmountPaidAudit = false;
            }
        });

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
        return number_format($this->balanceFloat(), 2);
    }

    public function balanceFloat(): float
    {
        if ($this->balance_due !== null) {
            return round((float) $this->balance_due, 2);
        }

        return self::computeBalanceDue((float) $this->amount, (float) ($this->amount_paid ?? 0));
    }

    public static function computeBalanceDue(float $amount, float $amountPaid): float
    {
        return max(0.0, round($amount - $amountPaid, 2));
    }

    public function syncDerivedBalanceDue(): self
    {
        $this->balance_due = self::computeBalanceDue(
            (float) $this->amount,
            (float) ($this->amount_paid ?? 0)
        );

        return $this;
    }

    public function allocatedAmount(): float
    {
        $id = (int) ($this->id ?? 0);
        if ($id > 0 && array_key_exists($id, self::$allocatedAmountMemo)) {
            return self::$allocatedAmountMemo[$id];
        }

        $sum = round((float) $this->allocations()
            ->where(function (Builder $q) {
                $q->where('is_reversed', false)->orWhereNull('is_reversed');
            })
            ->sum('amount'), 2);

        if ($id > 0) {
            self::$allocatedAmountMemo[$id] = $sum;
        }

        return $sum;
    }

    public static function flushAllocatedAmountMemo(?int $invoiceId = null): void
    {
        if ($invoiceId === null) {
            self::$allocatedAmountMemo = [];

            return;
        }

        unset(self::$allocatedAmountMemo[$invoiceId]);
    }

    /**
     * Set amount_paid from non-reversed payment allocations (source of truth),
     * then derive payment status and is_past_due.
     */
    public function syncAmountPaidFromAllocations(): self
    {
        self::flushAllocatedAmountMemo((int) ($this->id ?? 0));

        static::$allowDirectAmountPaidWrite = true;
        FinanceFirebreakService::$skipAmountPaidAudit = true;
        try {
            $allocated = min($this->allocatedAmount(), round((float) $this->amount, 2));
            if ($this->allocations()->exists()) {
                $this->amount_paid = $allocated;
            } elseif ($allocated > 0.009) {
                $this->amount_paid = $allocated;
            }
            $this->syncDerivedBalanceDue();
            $this->applyComputedStatusFromAmounts();
        } finally {
            static::$allowDirectAmountPaidWrite = false;
            FinanceFirebreakService::$skipAmountPaidAudit = false;
        }

        return $this;
    }

    /**
     * @deprecated Use syncAmountPaidFromAllocations().
     */
    public function refreshComputedStatus(): void
    {
        $this->syncAmountPaidFromAllocations();
    }

    public function recomputeInvoiceState(): self
    {
        return $this->syncAmountPaidFromAllocations();
    }

    /**
     * Billable open invoices past due date with a positive balance.
     */
    public function scopePastDueOpen(Builder $query): Builder
    {
        return $query
            ->billableAr()
            ->where('is_past_due', true)
            ->where('balance_due', '>', 0);
    }

    /**
     * Billable invoices with an open balance (uses indexed balance_due).
     */
    public function scopeOpenBillable(Builder $query): Builder
    {
        return $query->billableAr()->where('balance_due', '>', 0);
    }

    public function isEffectivelyOverdue(): bool
    {
        return (bool) $this->is_past_due && $this->balanceFloat() > 0.009;
    }

    public static function countPastDueOpen(): int
    {
        return app(InvoiceStateIntegrityService::class)->countPastDueInvoices();
    }

    public static function countPastDueTenants(): int
    {
        return app(InvoiceStateIntegrityService::class)->countPastDueTenants();
    }

    /**
     * Invoices with a positive open balance (matches dashboard Outstanding AR).
     */
    public function scopeWithOutstandingBalance(Builder $query): Builder
    {
        return $query->openBillable();
    }

    /**
     * Billable accounts receivable: excludes draft and cancelled invoices.
     */
    public function scopeBillableAr(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->liveBalances()
            ->where("{$table}.status", '!=', self::STATUS_DRAFT);
    }

    /**
     * Rows that contribute to live balances (excludes cancelled; Eloquent also
     * excludes soft-deleted rows via SoftDeletes).
     */
    public function scopeLiveBalances(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where("{$table}.status", '!=', self::STATUS_CANCELLED);
    }

    /**
     * Apply live-balance constraints to raw query-builder joins on pm_invoices.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyLiveBalanceConstraints($query, string $alias = ''): void
    {
        $prefix = $alias !== '' ? $alias.'.' : '';
        $query->whereNull($prefix.'deleted_at')
            ->where($prefix.'status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Apply billable AR constraints to raw query-builder joins on pm_invoices.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyBillableArConstraints($query, string $alias = ''): void
    {
        self::applyLiveBalanceConstraints($query, $alias);
        $prefix = $alias !== '' ? $alias.'.' : '';
        $query->where($prefix.'status', '!=', self::STATUS_DRAFT);
    }

    public function isCreditNote(): bool
    {
        return (string) ($this->invoice_kind ?? self::KIND_INVOICE) === self::KIND_CREDIT_NOTE;
    }

    public function isOpen(): bool
    {
        if ((string) $this->status === self::STATUS_DRAFT) {
            return true;
        }

        return $this->balanceFloat() > 0.009
            && ! in_array((string) $this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }

    protected function applyComputedStatusFromAmounts(): void
    {
        $previous = (string) $this->status;
        $previousPastDue = (bool) $this->is_past_due;

        if ((string) $this->status === self::STATUS_CANCELLED) {
            $this->is_past_due = false;
            $this->saveQuietly();

            return;
        }

        if ((string) $this->status === self::STATUS_DRAFT) {
            $this->is_past_due = false;
            $this->saveQuietly();

            return;
        }

        $amount = round((float) $this->amount, 2);
        $paid = round((float) $this->amount_paid, 2);
        $balance = self::computeBalanceDue($amount, $paid);
        $this->balance_due = $balance;

        if ($balance <= 0.009) {
            $this->status = self::STATUS_PAID;
            $this->is_past_due = false;
        } elseif ($paid > 0.009) {
            $this->status = self::STATUS_PARTIAL;
            $this->is_past_due = app(InvoiceStateIntegrityService::class)->expectedPastDue($this, $balance);
        } else {
            $this->status = self::STATUS_SENT;
            $this->is_past_due = app(InvoiceStateIntegrityService::class)->expectedPastDue($this, $balance);
        }

        $this->saveQuietly();

        if ($previous !== (string) $this->status) {
            $eventName = match ((string) $this->status) {
                self::STATUS_PAID => PmInvoiceEvent::EVENT_PAID,
                self::STATUS_PARTIAL => PmInvoiceEvent::EVENT_PARTIALLY_PAID,
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

            if ((string) $this->status === self::STATUS_PAID
                && str_starts_with((string) $this->description, FinanceFirebreakService::CARRY_FORWARD_PREFIX)) {
                app(CarryForwardConsolidationService::class)->markLineSettledForInvoice($this);
            }
        }

        if (! $previousPastDue && (bool) $this->is_past_due) {
            PmInvoiceEvent::record(
                (int) $this->id,
                PmInvoiceEvent::EVENT_OVERDUE,
                null,
                'Invoice past due with open balance'
            );
        }
    }

    /**
     * Recompute status for open invoices whose stored status or is_past_due may be stale.
     */
    public static function refreshStaleStatuses(int $limit = 500): int
    {
        $changed = 0;
        $today = now()->toDateString();

        static::query()
            ->whereNotIn('status', [self::STATUS_DRAFT, self::STATUS_CANCELLED])
            ->where(function ($q) use ($today) {
                $q->where('balance_due', '>', 0)
                    ->orWhereDate('due_date', '<', $today)
                    ->orWhere('status', self::STATUS_OVERDUE)
                    ->orWhere(function (Builder $pastDue) {
                        $pastDue->where('is_past_due', false)
                            ->where('balance_due', '>', 0)
                            ->whereDate('due_date', '<', now()->toDateString());
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (self $invoice) use (&$changed) {
                $beforeStatus = (string) $invoice->status;
                $beforePastDue = (bool) $invoice->is_past_due;
                $invoice->syncAmountPaidFromAllocations();
                if ($beforeStatus !== (string) $invoice->status || $beforePastDue !== (bool) $invoice->is_past_due) {
                    $changed++;
                }
            });

        return $changed;
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
