<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'min_units',
        'max_units',
        'monthly_price_ksh',
        'annual_price_ksh',
        'is_active',
        'sort_order',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price_ksh' => 'decimal:2',
            'annual_price_ksh' => 'decimal:2',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function agentSubscriptions(): HasMany
    {
        return $this->hasMany(AgentSubscription::class);
    }

    public function getFormattedMonthlyPriceAttribute(): string
    {
        return 'KSH ' . number_format($this->monthly_price_ksh, 2);
    }

    public function getFormattedAnnualPriceAttribute(): string
    {
        return $this->annual_price_ksh ? 'KSH ' . number_format($this->annual_price_ksh, 2) : null;
    }

    public function getUnitRangeAttribute(): string
    {
        if ($this->max_units === null) {
            return "{$this->min_units}+ units";
        }
        
        return "{$this->min_units} - {$this->max_units} units";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_units');
    }

    public static function getPackageForUnits(int $unitCount): ?self
    {
        return static::active()
            ->where('min_units', '<=', $unitCount)
            ->where(function ($query) use ($unitCount) {
                $query->whereNull('max_units')
                    ->orWhere('max_units', '>=', $unitCount);
            })
            ->first();
    }
}
