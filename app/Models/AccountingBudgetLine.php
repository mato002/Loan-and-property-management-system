<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBudgetLine extends Model
{
    protected $fillable = [
        'fiscal_year', 'month', 'accounting_chart_account_id', 'branch',
        'amount', 'label', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }
}
