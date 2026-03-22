<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmMessageTemplate extends Model
{
    protected $table = 'pm_message_templates';

    protected $fillable = [
        'name',
        'channel',
        'subject',
        'body',
    ];
}
