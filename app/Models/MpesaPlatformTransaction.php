<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaPlatformTransaction extends Model
{
    protected $fillable = [
        'reference',
        'amount',
        'channel',
        'status',
        'notes',
        'conversation_id',
        'originator_conversation_id',
        'transaction_id',
        'result_code',
        'result_desc',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
