<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmAccountingEntry extends Model
{
    protected $table = 'pm_accounting_entries';

    public const CATEGORY_INCOME = 'income';
    public const CATEGORY_EXPENSE = 'expense';
    public const CATEGORY_ASSET = 'asset';
    public const CATEGORY_LIABILITY = 'liability';
    public const CATEGORY_EQUITY = 'equity';

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';

    protected $fillable = [
        'property_id',
        'recorded_by_user_id',
        'entry_date',
        'account_name',
        'category',
        'entry_type',
        'amount',
        'reference',
        'description',
        'reversal_of_id',
        'source_key',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_INCOME => 'Income',
            self::CATEGORY_EXPENSE => 'Expense',
            self::CATEGORY_ASSET => 'Asset',
            self::CATEGORY_LIABILITY => 'Liability',
            self::CATEGORY_EQUITY => 'Equity',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_DEBIT => 'Debit',
            self::TYPE_CREDIT => 'Credit',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}

