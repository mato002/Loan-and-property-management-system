<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBookPenaltyAccrual extends Model
{
    protected $fillable = [
        'loan_book_loan_id',
        'loan_product_id',
        'scope',
        'installment_no',
        'accrued_on',
        'arrears_base',
        'penalty_amount_type',
        'penalty_rate',
        'penalty_amount',
        'reference',
        'accounting_journal_entry_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accrued_on' => 'date',
            'arrears_base' => 'decimal:2',
            'penalty_rate' => 'decimal:4',
            'penalty_amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }
}

