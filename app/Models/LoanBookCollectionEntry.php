<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBookCollectionEntry extends Model
{
    protected $fillable = [
        'loan_book_loan_id',
        'collected_on',
        'amount',
        'channel',
        'collected_by_employee_id',
        'notes',
        'accounting_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'collected_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'collected_by_employee_id');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }
}
