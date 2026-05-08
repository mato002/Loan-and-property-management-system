<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMessageAttachment extends Model
{
    protected $table = 'pm_message_attachments';

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByMessageParent($query, 'pm_message_attachments');
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(PmMessage::class, 'message_id');
    }
}
