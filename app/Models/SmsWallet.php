<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsWallet extends Model
{
    protected $fillable = [
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrFail();
    }
}
