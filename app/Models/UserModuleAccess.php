<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleAccess extends Model
{
    protected $fillable = [
        'user_id',
        'module',
        'status',
        'approved_by',
        'approved_at',
    ];

    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVOKED = 'revoked';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

