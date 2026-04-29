<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPaymentAllocation extends Model
{
    protected $fillable = [
        'loan_book_payment_id',
        'loan_book_loan_id',
        'component',
        'amount',
        'allocation_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocation_order' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LoanBookPayment::class, 'loan_book_payment_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }
}
