<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Per-user SMS forwarder token. Each agent generates one of these and
 * configures it as the `X-Agent-Forwarder-Token` header in their SMS
 * forwarder app. The webhook resolves the token to a user id and stamps
 * it on every payment row that comes from that device.
 */
class PmForwarderToken extends Model
{
    protected $table = 'pm_forwarder_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'label',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Generates a new, securely-random token string. Format is human-friendly
     * but high-entropy: prefix + 48 char base62-ish string from random_bytes.
     */
    public static function generateTokenString(): string
    {
        return 'pm-agent-'.Str::lower(Str::random(48));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
