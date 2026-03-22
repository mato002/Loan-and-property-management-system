<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBookDisbursement extends Model
{
    protected $fillable = [
        'loan_book_loan_id',
        'amount',
        'reference',
        'method',
        'disbursed_at',
        'notes',
        'accounting_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }
}
