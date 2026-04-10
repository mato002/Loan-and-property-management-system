<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'status',
        'starts_at',
        'ends_at',
        'price_paid',
        'payment_method',
        'payment_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'price_paid' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE 
            && $this->starts_at?->lte(now()) 
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }

    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->lt(now());
    }

    public function getFormattedPricePaidAttribute(): string
    {
        return $this->price_paid ? 'KSH ' . number_format($this->price_paid, 2) : 'N/A';
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function getActiveSubscriptionForUser(int $userId): ?self
    {
        return static::active()->forUser($userId)->first();
    }
}
