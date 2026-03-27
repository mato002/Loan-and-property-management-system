<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address_line',
        'city',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
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
