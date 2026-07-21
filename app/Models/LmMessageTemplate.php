<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmMessageTemplate extends Model
{
    protected $table = 'lm_message_templates';

    protected $fillable = [
        'name',
        'channel',
        'category',
        'subject',
        'body',
        'template_version',
        'approved_by_user_id',
        'approved_at',
        'is_active',
        'supported_variables',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'supported_variables' => 'array',
        ];
    }
}
