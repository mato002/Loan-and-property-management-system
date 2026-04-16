<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleAccess extends Model
{
    protected static function booted(): void
    {
        static::saving(function (UserModuleAccess $access): void {
            if (filled($access->status)) {
                $access->status = self::normalizeStatusString($access->status);
            }
        });
    }

    /**
     * Read from raw attributes so display/actions stay correct even if legacy rows used odd casing or whitespace.
     */
    public function getNormalizedStatusAttribute(): string
    {
        $raw = (string) ($this->getAttributes()['status'] ?? '');

        return self::normalizeStatusString($raw);
    }

    public static function normalizeStatusString(mixed $value): string
    {
        $raw = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $value);

        return strtolower(trim($raw));
    }

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

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

