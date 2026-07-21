<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmMessageRead extends Model
{
    protected $table = 'lm_message_reads';

    protected $fillable = [
        'user_id',
        'lm_message_log_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(LmMessageLog::class, 'lm_message_log_id');
    }
}
