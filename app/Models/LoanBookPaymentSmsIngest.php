<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBookPaymentSmsIngest extends Model
{
    protected $fillable = [
        'provider',
        'source_device',
        'provider_txn_code',
        'payer_phone',
        'amount',
        'paid_at',
        'raw_message',
        'payload',
        'loan_book_loan_id',
        'loan_book_payment_id',
        'match_status',
        'match_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LoanBookPayment::class, 'loan_book_payment_id');
    }
}
