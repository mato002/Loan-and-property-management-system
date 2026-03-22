<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'sms_template_id',
        'body',
        'recipients',
        'scheduled_at',
        'status',
        'failure_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }
}
