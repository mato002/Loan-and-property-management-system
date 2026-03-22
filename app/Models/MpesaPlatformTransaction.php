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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
