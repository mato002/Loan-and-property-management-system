<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingChartAccount extends Model
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'is_cash_account',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cash_account' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class, 'accounting_chart_account_id');
    }
}
