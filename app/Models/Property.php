<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Property extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address_line',
        'city',
        'agent_user_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function ($query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->where('properties.agent_user_id', $user->id);
        });
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function agentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    /**
     * All invoices linked to this property through its units.
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(
            PmInvoice::class,
            PropertyUnit::class,
            'property_id',
            'property_unit_id',
            'id',
            'id'
        );
    }

    public function landlords(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_landlord')
            ->withPivot('ownership_percent')
            ->withTimestamps();
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(PmAmenity::class, 'pm_amenity_property', 'property_id', 'pm_amenity_id')
            ->withTimestamps();
    }
}
