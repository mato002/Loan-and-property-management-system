<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmMessagePreference extends Model
{
    protected $table = 'pm_message_preferences';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'property_id',
        'category',
        'allow_sms',
        'allow_email',
        'allow_whatsapp',
        'allow_promotional_messages',
        'allow_arrears_reminders',
        'preferred_channel',
        'digest_frequency',
    ];

    protected function casts(): array
    {
        return [
            'allow_sms' => 'boolean',
            'allow_email' => 'boolean',
            'allow_whatsapp' => 'boolean',
            'allow_promotional_messages' => 'boolean',
            'allow_arrears_reminders' => 'boolean',
        ];
    }
}
