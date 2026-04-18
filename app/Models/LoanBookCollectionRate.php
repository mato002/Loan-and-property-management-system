<?php

namespace App\Models;

use App\Models\Concerns\FallbackPrimaryKeyWhenNoAutoIncrement;
use Illuminate\Database\Eloquent\Model;

class LoanBookCollectionRate extends Model
{
    use FallbackPrimaryKeyWhenNoAutoIncrement;

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
