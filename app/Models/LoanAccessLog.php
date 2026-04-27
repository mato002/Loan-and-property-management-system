<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'device_fingerprint',
        'route_name',
        'method',
        'event_category',
        'action_type',
        'path',
        'activity',
        'result',
        'risk_score',
        'risk_level',
        'risk_reason',
        'requires_reason',
        'reason_text',
        'old_value',
        'new_value',
        'audit_token',
        'checksum',
        'previous_hash',
        'mfa_verified',
        'ip_address',
        'country_code',
        'geo_label',
        'is_foreign_ip',
        'is_privileged',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'requires_reason' => 'boolean',
            'mfa_verified' => 'boolean',
            'is_foreign_ip' => 'boolean',
            'is_privileged' => 'boolean',
            'risk_score' => 'integer',
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(LoanAuditAlert::class);
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(LoanAuditConcern::class);
    }
}
