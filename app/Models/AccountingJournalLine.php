<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalLine extends Model
{
    protected $fillable = [
        'accounting_journal_entry_id',
        'accounting_chart_account_id',
        'batch_id',
        'account_id',
        'debit',
        'credit',
        'memo',
        'reference',
        'property_id',
        'tenant_id',
        'landlord_id',
        'unit_id',
        'agent_user_id',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalBatch::class, 'batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }

    public function structuredAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'account_id');
    }
}
