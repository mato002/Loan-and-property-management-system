<?php

namespace App\Models;

use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\InvoiceStateIntegrityService;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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

    public const TYPE_ELECTRICITY = 'electricity';

    public const TYPE_GARBAGE = 'garbage';

    public const TYPE_SERVICE = 'service';

    /** @deprecated Not offered in the create dropdown — agents should add a named type instead. Kept for legacy rows. */
    public const TYPE_OTHER = 'other';

    /** @deprecated Prefer a specific charge type. Kept for legacy rows. */
    public const TYPE_MIXED = 'mixed';

    public const CUSTOM_TYPES_SETTING_KEY = 'invoice_custom_types_json';

    /**
     * Built-in types agents can pick when manually creating an invoice.
     * No generic "Other charge" — use + to create a named type.
     *
     * @return array<string, string> value => label
     */
    public static function builtinTypeOptions(): array
    {
        return [
            self::TYPE_RENT => 'Rent',
            self::TYPE_WATER => 'Water',
            self::TYPE_ELECTRICITY => 'Electricity',
            self::TYPE_GARBAGE => 'Garbage',
            self::TYPE_SERVICE => 'Service',
        ];
    }

    /**
     * Keys that must not be recreated as custom types.
     *
     * @return list<string>
     */
    public static function reservedTypeKeys(): array
    {
        return [
            self::TYPE_RENT,
            self::TYPE_WATER,
            self::TYPE_ELECTRICITY,
            self::TYPE_GARBAGE,
            self::TYPE_SERVICE,
            self::TYPE_OTHER,
            self::TYPE_MIXED,
        ];
    }

    /**
     * Types agents can pick when manually creating an invoice (built-in + custom).
     *
     * @return array<string, string> value => label
     */
    public static function createTypeOptions(): array
    {
        return array_merge(self::builtinTypeOptions(), self::customTypeOptions());
    }

    /**
     * Custom invoice types stored per agent workspace.
     *
     * @return array<string, string> value => label
     */
    public static function customTypeOptions(): array
    {
        $raw = self::readCustomTypesJson();
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $reserved = array_flip(self::reservedTypeKeys());
        $out = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $value = self::normalizeTypeKey((string) ($row['value'] ?? $row['key'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($value === '' || $label === '') {
                continue;
            }
            if (isset($reserved[$value])) {
                continue;
            }
            $out[$value] = $label;
        }

        return $out;
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function addCustomType(string $label): array
    {
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');
        if ($label === '') {
            throw new \InvalidArgumentException('Type name is required.');
        }
        if (mb_strlen($label) > 64) {
            throw new \InvalidArgumentException('Type name is too long (max 64 characters).');
        }

        $value = self::normalizeTypeKey($label);
        if ($value === '') {
            throw new \InvalidArgumentException('Type name must include letters or numbers.');
        }
        if (in_array($value, self::reservedTypeKeys(), true)) {
            throw new \InvalidArgumentException('That name is reserved. Pick a more specific charge name.');
        }

        $custom = self::customTypeOptions();
        if (isset($custom[$value])) {
            return ['value' => $value, 'label' => $custom[$value]];
        }

        $custom[$value] = $label;
        self::writeCustomTypesJson($custom);

        return ['value' => $value, 'label' => $label];
    }

    /**
     * Resolve a free-text charge name into a stored invoice_type (registers custom types).
     */
    public static function resolveOrCreateTypeFromLabel(string $label, ?string $fallbackType = null): string
    {
        $label = trim($label);
        $fallbackType = $fallbackType ?: self::TYPE_SERVICE;
        if ($label === '') {
            return $fallbackType;
        }

        $key = self::normalizeTypeKey($label);
        $options = self::createTypeOptions();
        if (isset($options[$key])) {
            return $key;
        }
        if (in_array($key, self::reservedTypeKeys(), true) && $key !== self::TYPE_OTHER && $key !== self::TYPE_MIXED) {
            return $key;
        }

        try {
            return self::addCustomType($label)['value'];
        } catch (\InvalidArgumentException) {
            return $key !== '' && $key !== self::TYPE_OTHER && $key !== self::TYPE_MIXED
                ? $key
                : $fallbackType;
        }
    }

    public static function normalizeTypeKey(string $raw): string
    {
        $normalized = strtolower(trim($raw));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private static function resolveCustomTypesAgentUserId(): ?int
    {
        return PropertyWorkspaceBranding::resolveViewerAgentUserId()
            ?? PropertyWorkspaceBranding::storeAgentUserId();
    }

    private static function readCustomTypesJson(): string
    {
        $agentUserId = self::resolveCustomTypesAgentUserId();
        $query = PropertyPortalSetting::query()->where('key', self::CUSTOM_TYPES_SETTING_KEY);
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            if ($agentUserId !== null) {
                $query->where('agent_user_id', $agentUserId);
            } else {
                $query->whereNull('agent_user_id');
            }
        }

        return trim((string) ($query->value('value') ?? ''));
    }

    /**
     * @param  array<string, string>  $types
     */
    private static function writeCustomTypesJson(array $types): void
    {
        $payload = [];
        foreach ($types as $value => $label) {
            $payload[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        $json = json_encode(array_values($payload), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Could not save invoice types.');
        }

        $agentUserId = self::resolveCustomTypesAgentUserId();

        if (Schema::hasColumn('property_portal_settings', 'agent_user_id') && $agentUserId !== null) {
            PropertyPortalSetting::query()->updateOrCreate(
                [
                    'agent_user_id' => $agentUserId,
                    'key' => self::CUSTOM_TYPES_SETTING_KEY,
                ],
                ['value' => $json]
            );

            return;
        }

        PropertyPortalSetting::setGlobalValue(self::CUSTOM_TYPES_SETTING_KEY, $json);
    }

    /**
     * All known invoice_type values (including legacy mixed + custom).
     *
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::createTypeOptions()),
            [self::TYPE_MIXED, self::TYPE_OTHER],
        )));
    }

    /**
     * Utility / non-rent charge types (used for AR/income posting and filters).
     *
     * @return list<string>
     */
    public static function utilityTypes(): array
    {
        $types = [
            self::TYPE_WATER,
            self::TYPE_ELECTRICITY,
            self::TYPE_GARBAGE,
            self::TYPE_SERVICE,
            self::TYPE_OTHER,
            self::TYPE_MIXED,
        ];

        return array_values(array_unique(array_merge($types, array_keys(self::customTypeOptions()))));
    }

    public function isUtilityChargeType(): bool
    {
        $type = (string) ($this->invoice_type ?? '');

        return $type !== '' && $type !== self::TYPE_RENT;
    }

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

    /**
     * How the tenant was notified (email/SMS). Null if never delivered electronically.
     */
    public function tenantDeliverySummary(): ?string
    {
        $events = $this->relationLoaded('events')
            ? $this->events
            : $this->events()->whereIn('event', [
                PmInvoiceEvent::EVENT_EMAILED,
                PmInvoiceEvent::EVENT_SMS_SENT,
            ])->get();

        $hasEmail = $events->contains('event', PmInvoiceEvent::EVENT_EMAILED);
        $hasSms = $events->contains('event', PmInvoiceEvent::EVENT_SMS_SENT);

        if ($hasEmail && $hasSms) {
            return 'Emailed & SMS';
        }
        if ($hasEmail) {
            return 'Emailed to tenant';
        }
        if ($hasSms) {
            return 'SMS sent';
        }

        return null;
    }

    public function tenantDeliveryPending(): bool
    {
        if (in_array((string) $this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true)) {
            return false;
        }

        return $this->tenantDeliverySummary() === null;
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

    public function ensureDefaultRentLineItem(float $amount, string $description = 'Monthly rent'): void
    {
        if ($this->items()->exists()) {
            return;
        }

        $lineTotal = round($amount, 2);
        $itemType = (string) ($this->invoice_type ?? self::TYPE_RENT);

        $this->items()->create([
            'line_no' => 1,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $lineTotal,
            'line_subtotal' => $lineTotal,
            'line_total' => $lineTotal,
            'source_type' => $itemType,
            'type' => $itemType,
        ]);
    }

    public function invoiceKindKey(): string
    {
        return (string) ($this->invoice_kind ?? self::KIND_INVOICE);
    }

    public function isRentSupplement(): bool
    {
        return $this->invoiceKindKey() === self::KIND_RENT_SUPPLEMENT;
    }

    public function isWaterSupplement(): bool
    {
        return $this->invoiceKindKey() === self::KIND_WATER_SUPPLEMENT;
    }

    public function isCarryForwardInvoice(): bool
    {
        if (! empty($this->carry_forward_origin)) {
            return true;
        }

        $description = (string) ($this->description ?? '');

        return str_starts_with($description, self::LEASE_OPENING_ARREARS_PREFIX)
            || str_starts_with($description, FinanceFirebreakService::CARRY_FORWARD_PREFIX);
    }

    /**
     * Human label for what is being billed (clearer than invoice_type alone).
     */
    public function chargeCategoryLabel(): string
    {
        if ($this->isCreditNote()) {
            return 'Credit note';
        }

        if ($this->isRentSupplement()) {
            return 'Rent supplement';
        }

        if ($this->isWaterSupplement()) {
            return 'Water supplement';
        }

        if ($this->isCarryForwardInvoice()) {
            return 'Opening balance';
        }

        $type = (string) ($this->invoice_type ?? self::TYPE_RENT);
        $custom = self::customTypeOptions();
        if (isset($custom[$type])) {
            return $custom[$type];
        }

        if ($type === self::TYPE_OTHER || $type === self::TYPE_MIXED) {
            return $this->legacyUnspecifiedChargeLabel();
        }

        return match ($type) {
            self::TYPE_RENT => 'Monthly rent',
            self::TYPE_WATER => 'Water',
            self::TYPE_ELECTRICITY => 'Electricity',
            self::TYPE_GARBAGE => 'Garbage',
            self::TYPE_SERVICE => 'Service',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Prefer description text over the old generic "Other charge" label.
     */
    private function legacyUnspecifiedChargeLabel(): string
    {
        $desc = trim((string) ($this->description ?? ''));
        if ($desc !== '') {
            $chunk = preg_split('/\s*[·|–—]\s*/u', $desc, 2)[0] ?? $desc;
            $chunk = trim(explode(' - ', $chunk, 2)[0] ?? $chunk);
            if ($chunk !== '') {
                return \Illuminate\Support\Str::limit($chunk, 48, '');
            }
        }

        return 'Charge';
    }

    /**
     * Expected rent for this invoice's unit (split when lease spans multiple units).
     */
    public function leaseRentExpectationForUnit(): ?float
    {
        $lease = $this->lease;
        if (! $lease) {
            return null;
        }

        $rent = round((float) $lease->monthly_rent, 2);
        if ($lease->relationLoaded('units')) {
            $unitCount = max(1, $lease->units->count());

            return round($rent / $unitCount, 2);
        }

        return $rent;
    }

    /**
     * Short subtitle for invoice lists — explains amount vs lease rent or carry-forward context.
     */
    public function chargeDetailHint(): ?string
    {
        if ($this->isRentSupplement()) {
            return $this->truncateChargeHint((string) ($this->description ?: 'Top-up after lease rent change'));
        }

        if ($this->isCarryForwardInvoice()) {
            return $this->truncateChargeHint($this->carryForwardDescriptionSummary());
        }

        $rentDeltaHint = $this->rentChargeOriginExplanation();
        if ($rentDeltaHint !== null) {
            return $rentDeltaHint;
        }

        $description = trim((string) ($this->description ?? ''));
        if ($description === '') {
            return null;
        }

        $normalizedDescription = $this->normalizeChargeDescription($description);
        $category = Str::lower($this->chargeCategoryLabel());
        if (Str::lower($normalizedDescription) === $category) {
            return null;
        }

        return $this->truncateChargeHint($normalizedDescription);
    }

    public function rentAmountDeltaHint(): ?string
    {
        return $this->rentChargeOriginExplanation();
    }

    /**
     * Explain why a rent invoice amount differs from the current lease rent, or
     * what each part of the charge is for.
     */
    public function rentChargeOriginExplanation(): ?string
    {
        if ((string) ($this->invoice_type ?? '') !== self::TYPE_RENT) {
            return null;
        }

        if ($this->isRentSupplement() || $this->isCarryForwardInvoice() || $this->isCreditNote()) {
            return null;
        }

        $expected = $this->leaseRentExpectationForUnit();
        if ($expected === null) {
            return null;
        }

        $amount = round((float) $this->amount, 2);
        $delta = round($amount - $expected, 2);
        if (abs($delta) <= self::AMOUNT_EPSILON) {
            return null;
        }

        $lineItemSummary = $this->lineItemsChargeSummary();
        if ($lineItemSummary !== null) {
            return $lineItemSummary;
        }

        $issued = $this->resolveIssuedEvent();
        $payload = is_array($issued?->payload) ? $issued->payload : [];
        $source = (string) ($payload['source'] ?? '');
        $snapPerUnit = isset($payload['per_unit_amount']) ? round((float) $payload['per_unit_amount'], 2) : null;

        if ($snapPerUnit !== null && abs($snapPerUnit - $amount) <= self::AMOUNT_EPSILON) {
            return sprintf(
                'Monthly rent KES %s when billed (%s) · lease rent now KES %s — not a separate fee; lease rent changed after billing',
                number_format($snapPerUnit, 2),
                $this->billingSourceShortLabel($source, $issued),
                number_format($expected, 2),
            );
        }

        if ($this->isAutoGeneratedRentInvoice($issued, $source)) {
            return sprintf(
                'Monthly rent KES %s when auto-billed %s · lease rent now KES %s — the KES %s difference is from a lease rent change after billing, not an extra charge',
                number_format($amount, 2),
                $this->issue_date?->format('Y-m-d') ?? 'on issue date',
                number_format($expected, 2),
                number_format(abs($delta), 2),
            );
        }

        $editHint = $this->amountEditOriginHint($expected, $amount);
        if ($editHint !== null) {
            return $editHint;
        }

        if ($this->isManualInvoice($issued, $source)) {
            $notes = trim((string) ($this->notes ?? ''));
            if ($notes !== '') {
                return sprintf(
                    'Manual invoice · KES %s above lease rent — %s',
                    number_format(abs($delta), 2),
                    $this->truncateChargeHint($notes, 72),
                );
            }

            $description = trim((string) ($this->description ?? ''));
            if ($description !== '' && ! $this->isGenericRentDescription($description)) {
                return sprintf(
                    'Manual invoice · KES %s above lease rent — %s',
                    number_format(abs($delta), 2),
                    $this->truncateChargeHint($this->normalizeChargeDescription($description), 72),
                );
            }

            return sprintf(
                'Manual invoice · KES %s above current lease rent with no fee breakdown recorded',
                number_format(abs($delta), 2),
            );
        }

        return sprintf(
            'Billed KES %s vs lease rent KES %s (+%s) — open invoice for line items and audit events',
            number_format($amount, 2),
            number_format($expected, 2),
            number_format(abs($delta), 2),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rentBillingEventPayload(PmLease $lease, float $perUnitAmount, string $source): array
    {
        $unitCount = $lease->relationLoaded('units')
            ? max(1, $lease->units->count())
            : max(1, $lease->units()->count());

        return [
            'source' => $source,
            'lease_monthly_rent' => round((float) $lease->monthly_rent, 2),
            'per_unit_amount' => round($perUnitAmount, 2),
            'unit_count' => $unitCount,
        ];
    }

    public function resolveIssuedEvent(): ?PmInvoiceEvent
    {
        if ($this->relationLoaded('events')) {
            return $this->events
                ->first(fn (PmInvoiceEvent $event): bool => (string) $event->event === PmInvoiceEvent::EVENT_ISSUED);
        }

        return $this->events()
            ->where('event', PmInvoiceEvent::EVENT_ISSUED)
            ->orderBy('id')
            ->first();
    }

    private function lineItemsChargeSummary(): ?string
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->orderBy('line_no')->get();

        if ($items->count() <= 1) {
            return null;
        }

        $parts = $items
            ->map(function (PmInvoiceItem $item): string {
                $label = trim((string) ($item->description ?: 'Charge'));
                $total = round((float) ($item->line_total ?? 0), 2);

                return $label.' KES '.number_format($total, 2);
            })
            ->filter()
            ->values()
            ->all();

        return $parts !== [] ? $this->truncateChargeHint(implode(' · ', $parts), 120) : null;
    }

    private function amountEditOriginHint(float $expected, float $amount): ?string
    {
        $events = $this->relationLoaded('events')
            ? $this->events
            : $this->events()->orderBy('id')->get();

        $edit = $events
            ->first(fn (PmInvoiceEvent $event): bool => (string) $event->event === PmInvoiceEvent::EVENT_EDITED
                && is_array($event->payload)
                && isset($event->payload['before']['amount'], $event->payload['after']['amount'])
                && abs((float) $event->payload['before']['amount'] - (float) $event->payload['after']['amount']) > self::AMOUNT_EPSILON);

        if (! $edit) {
            return null;
        }

        $before = round((float) $edit->payload['before']['amount'], 2);
        $after = round((float) $edit->payload['after']['amount'], 2);

        if (abs($after - $amount) > self::AMOUNT_EPSILON) {
            return null;
        }

        return sprintf(
            'Amount edited from KES %s to KES %s · current lease rent KES %s',
            number_format($before, 2),
            number_format($after, 2),
            number_format($expected, 2),
        );
    }

    private function isAutoGeneratedRentInvoice(?PmInvoiceEvent $issued, string $source): bool
    {
        if (in_array($source, ['rent:generate-invoices', 'revenue.uninvoiced_leases', 'revenue.uninvoiced_leases.supplement'], true)) {
            return true;
        }

        $summary = Str::lower(trim((string) ($issued?->summary ?? '')));

        return str_contains($summary, 'auto-generated') || str_contains($summary, 'rent invoice generated');
    }

    private function isManualInvoice(?PmInvoiceEvent $issued, string $source): bool
    {
        if ($source === 'manual') {
            return true;
        }

        $summary = Str::lower(trim((string) ($issued?->summary ?? '')));

        return str_contains($summary, 'manually issued') || ($issued !== null && $issued->user_id !== null && ! $this->isAutoGeneratedRentInvoice($issued, $source));
    }

    private function billingSourceShortLabel(string $source, ?PmInvoiceEvent $issued): string
    {
        return match ($source) {
            'rent:generate-invoices' => 'auto rent run',
            'revenue.uninvoiced_leases' => 'uninvoiced leases report',
            'revenue.uninvoiced_leases.supplement' => 'rent increase supplement',
            'manual' => 'manual entry',
            default => $issued?->user_id ? 'manual entry' : 'system',
        };
    }

    private function isGenericRentDescription(string $description): bool
    {
        $normalized = Str::lower(trim($description));

        return str_starts_with($normalized, 'rent ')
            || str_contains($normalized, '→')
            || $normalized === 'property charge';
    }

    private function carryForwardDescriptionSummary(): string
    {
        $description = trim((string) ($this->description ?? ''));
        foreach ([FinanceFirebreakService::CARRY_FORWARD_PREFIX, self::LEASE_OPENING_ARREARS_PREFIX] as $prefix) {
            if (str_starts_with($description, $prefix)) {
                $description = trim(substr($description, strlen($prefix)));
                break;
            }
        }

        $description = ltrim($description, '| ');
        if ($description !== '') {
            return $this->normalizeChargeDescription($description);
        }

        return 'Previous balance brought forward at lease start';
    }

    private function normalizeChargeDescription(string $description): string
    {
        $parts = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            preg_split('/\s*[|·]\s*/', $description) ?: [],
        )));

        return $parts !== [] ? implode(' · ', $parts) : trim($description);
    }

    private function truncateChargeHint(string $text, int $limit = 120): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return Str::length($text) > $limit ? Str::limit($text, $limit) : $text;
    }

    private const AMOUNT_EPSILON = 0.01;
}
