<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PmAmenity extends Model
{
    protected $table = 'pm_amenities';

    protected $fillable = [
        'name',
        'category',
    ];

    /**
     * @return BelongsToMany<PropertyUnit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(PropertyUnit::class, 'pm_amenity_unit', 'pm_amenity_id', 'property_unit_id')
            ->withTimestamps();
    }
}
