<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmMessageBatch extends Model
{
    protected $table = 'lm_message_batches';

    protected $fillable = [
        'name',
        'channel',
        'status',
        'created_by_user_id',
        'recipient_count',
        'sent_count',
        'failed_count',
        'estimated_cost',
        'actual_cost',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:4',
            'actual_cost' => 'decimal:4',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
