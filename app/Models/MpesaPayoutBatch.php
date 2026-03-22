<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaPayoutBatch extends Model
{
    protected $fillable = [
        'reference',
        'recipient_count',
        'total_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }
}
