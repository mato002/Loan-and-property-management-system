<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmPenaltyRule extends Model
{
    protected $table = 'pm_penalty_rules';

    public const COMPOUNDING_SIMPLE = 'simple';

    public const COMPOUNDING_DAILY = 'daily_compound';

    public const COMPOUNDING_ONE_SHOT = 'one_shot';

    protected $fillable = [
        'name',
        'scope',
        'trigger_event',
        'grace_days',
        'formula',
        'compounding_mode',
        'amount',
        'percent',
        'cap',
        'cumulative_cap',
        'effective_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percent' => 'decimal:4',
            'cap' => 'decimal:2',
            'cumulative_cap' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
            'grace_days' => 'integer',
        ];
    }
}
