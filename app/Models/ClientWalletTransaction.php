<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientWalletTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const SOURCE_OVERPAYMENT = 'overpayment';

    public const SOURCE_PAYMENT_RECEIVED = 'payment_received';

    public const SOURCE_WALLET_TO_LOAN = 'wallet_to_loan';

    public const SOURCE_REFUND = 'refund';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const SOURCE_MIGRATION_BACKFILL = 'migration_backfill';

    protected $fillable = [
        'client_wallet_id',
        'loan_client_id',
        'transaction_type',
        'source_type',
        'amount',
        'running_balance',
        'reference',
        'description',
        'loan_book_payment_id',
        'loan_book_loan_id',
        'accounting_journal_entry_id',
        'created_by',
        'approved_by',
        'approved_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ClientWallet::class, 'client_wallet_id');
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function loanBookPayment(): BelongsTo
    {
        return $this->belongsTo(LoanBookPayment::class, 'loan_book_payment_id');
    }

    public function loanBookLoan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }
}
