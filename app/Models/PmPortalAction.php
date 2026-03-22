<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmPortalAction extends Model
{
    protected $table = 'pm_portal_actions';

    protected $fillable = [
        'user_id',
        'portal_role',
        'action_key',
        'notes',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
