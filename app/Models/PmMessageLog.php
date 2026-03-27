<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMessageLog extends Model
{
    protected $table = 'pm_message_logs';

    protected $fillable = [
        'user_id',
        'channel',
        'to_address',
        'subject',
        'body',
        'delivery_status',
        'delivery_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
