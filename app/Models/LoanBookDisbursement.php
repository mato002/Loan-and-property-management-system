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
        'payout_status',
        'payout_provider',
        'payout_phone',
        'payout_conversation_id',
        'payout_originator_conversation_id',
        'payout_transaction_id',
        'payout_result_code',
        'payout_result_desc',
        'payout_requested_at',
        'payout_completed_at',
        'payout_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'date',
            'payout_requested_at' => 'datetime',
            'payout_completed_at' => 'datetime',
            'payout_meta' => 'array',
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
