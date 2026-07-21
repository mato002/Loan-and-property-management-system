<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmMessagePreference extends Model
{
    protected $table = 'lm_message_preferences';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'category',
        'allow_sms',
        'allow_email',
        'allow_promotional_messages',
        'allow_payment_reminders',
        'preferred_channel',
    ];

    protected function casts(): array
    {
        return [
            'allow_sms' => 'boolean',
            'allow_email' => 'boolean',
            'allow_promotional_messages' => 'boolean',
            'allow_payment_reminders' => 'boolean',
        ];
    }
}
