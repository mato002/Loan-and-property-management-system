<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalLine extends Model
{
    protected $fillable = [
        'accounting_journal_entry_id',
        'accounting_chart_account_id',
        'debit',
        'credit',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }
}
