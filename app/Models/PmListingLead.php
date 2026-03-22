<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmListingLead extends Model
{
    protected $table = 'pm_listing_leads';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'source',
        'stage',
        'notes',
        'property_unit_id',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
