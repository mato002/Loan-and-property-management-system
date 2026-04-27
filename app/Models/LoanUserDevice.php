<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanUserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_hash',
        'fingerprint_label',
        'is_trusted',
        'bound_at',
        'last_seen_at',
        'last_seen_ip',
        'last_seen_user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'bound_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

