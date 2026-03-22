<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanBookCollectionRate extends Model
{
    protected $fillable = [
        'branch',
        'year',
        'month',
        'target_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
        ];
    }
}
