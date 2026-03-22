<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPostingRule extends Model
{
    protected $fillable = [
        'rule_key',
        'label',
        'debit_account_id',
        'credit_account_id',
        'is_editable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_editable' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'rule_key';
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'credit_account_id');
    }
}
