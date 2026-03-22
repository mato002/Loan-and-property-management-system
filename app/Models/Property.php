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
}
