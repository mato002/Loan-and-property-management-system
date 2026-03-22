<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTenantPortalRequest extends Model
{
    protected $table = 'pm_tenant_portal_requests';

    public const TYPE_VACATE = 'vacate_notice';

    public const TYPE_EXTENSION = 'lease_extension';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'message',
        'preferred_date',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
