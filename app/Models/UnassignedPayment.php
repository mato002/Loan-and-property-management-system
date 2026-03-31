<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnassignedPayment extends Model
{
    protected $table = 'unassigned_payments';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'amount',
        'account_number',
        'phone',
        'payment_method',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}

