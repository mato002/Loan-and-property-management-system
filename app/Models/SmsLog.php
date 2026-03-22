<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'user_id',
        'sms_schedule_id',
        'phone',
        'message',
        'status',
        'error',
        'charged_amount',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'charged_amount' => 'decimal:4',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SmsSchedule::class, 'sms_schedule_id');
    }
}
