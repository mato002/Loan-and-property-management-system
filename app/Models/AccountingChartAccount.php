<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class AccountingChartAccount extends Model
{
    public const CLASS_HEADER = 'Header';
    public const CLASS_PARENT = 'Parent';

    public const CLASS_DETAIL = 'Detail';

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'parent_id',
        'account_class',
        'current_balance',
        'min_balance_floor',
        'is_cash_account',
        'allow_overdraft',
        'overdraft_limit',
        'is_overdrawn',
        'is_active',
        'created_by',
        'approval_status',
        'approval_current_step',
        'approval_submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'approval_history',
        'is_controlled_account',
        'control_requires_approval',
        'control_approval_type',
        'control_approval_role',
        'control_always_require_approval',
        'control_threshold_enabled',
        'control_threshold_amount',
        'control_applies_to',
        'control_reason_note',
        'floor_enabled',
        'floor_action',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'rejected_by' => 'integer',
            'approval_current_step' => 'integer',
            'is_cash_account' => 'boolean',
            'allow_overdraft' => 'boolean',
            'is_overdrawn' => 'boolean',
            'is_active' => 'boolean',
            'is_controlled_account' => 'boolean',
            'control_requires_approval' => 'boolean',
            'control_always_require_approval' => 'boolean',
            'control_threshold_enabled' => 'boolean',
            'floor_enabled' => 'boolean',
            'approval_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'current_balance' => 'decimal:2',
            'overdraft_limit' => 'decimal:2',
            'min_balance_floor' => 'decimal:2',
            'control_threshold_amount' => 'decimal:2',
            'approval_history' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class, 'accounting_chart_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isHeader(): bool
    {
        return (string) $this->account_class === self::CLASS_HEADER;
    }

    public function isDetail(): bool
    {
        return (string) $this->account_class === self::CLASS_DETAIL;
    }

    public function isParent(): bool
    {
        return (string) $this->account_class === self::CLASS_PARENT;
    }

    public function controlledApprovers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'accounting_controlled_account_approvers', 'accounting_chart_account_id', 'user_id')
            ->withTimestamps();
    }

    public function isDescendantOf(int $accountId): bool
    {
        $seen = [];
        $cursor = $this->parent;
        while ($cursor) {
            if (in_array($cursor->id, $seen, true)) {
                return false;
            }
            if ((int) $cursor->id === $accountId) {
                return true;
            }
            $seen[] = (int) $cursor->id;
            $cursor = $cursor->parent;
        }

        return false;
    }
}
