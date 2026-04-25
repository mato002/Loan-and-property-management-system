<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingChartAccount extends Model
{
    public const CLASS_HEADER = 'Header';

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
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'is_cash_account' => 'boolean',
            'allow_overdraft' => 'boolean',
            'is_overdrawn' => 'boolean',
            'is_active' => 'boolean',
            'current_balance' => 'decimal:2',
            'overdraft_limit' => 'decimal:2',
            'min_balance_floor' => 'decimal:2',
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

    public function isHeader(): bool
    {
        return (string) $this->account_class === self::CLASS_HEADER;
    }

    public function isDetail(): bool
    {
        return (string) $this->account_class !== self::CLASS_HEADER;
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
