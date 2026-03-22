<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmUnitMovement extends Model
{
    protected $table = 'pm_unit_movements';

    protected $fillable = [
        'property_unit_id',
        'movement_type',
        'status',
        'scheduled_on',
        'completed_on',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
