<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkplanItem extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'title',
        'is_done',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
