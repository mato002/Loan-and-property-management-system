<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmPenaltyRule extends Model
{
    protected $table = 'pm_penalty_rules';

    protected $fillable = [
        'name',
        'scope',
        'trigger_event',
        'formula',
        'amount',
        'percent',
        'cap',
        'effective_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percent' => 'decimal:4',
            'cap' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
