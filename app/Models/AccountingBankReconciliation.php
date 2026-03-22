<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBankReconciliation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'accounting_chart_account_id', 'statement_date', 'statement_balance',
        'adjustment_amount', 'outstanding_items', 'status', 'prepared_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
