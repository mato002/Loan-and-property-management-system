<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmMessageAttachment extends Model
{
    protected $table = 'lm_message_attachments';

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(LmMessage::class, 'message_id');
    }
}
