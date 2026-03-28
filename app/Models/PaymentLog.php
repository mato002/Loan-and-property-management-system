<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $table = 'payment_logs';

    public $timestamps = false;

    protected $fillable = [
        'source',
        'response',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

