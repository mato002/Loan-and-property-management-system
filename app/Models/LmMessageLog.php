<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmMessageLog extends Model
{
    protected $table = 'lm_message_logs';

    protected $fillable = [
        'user_id',
        'channel',
        'to_address',
        'subject',
        'internal_stage',
        'display_stage',
        'template_category',
        'body',
        'delivery_status',
        'delivery_error',
        'sent_at',
        'superseded_at',
        'superseded_by_log_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
