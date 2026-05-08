<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTenantNoticeEvent extends Model
{
    protected $table = 'pm_tenant_notice_events';

    protected $fillable = [
        'notice_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(PmTenantNotice::class, 'notice_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
