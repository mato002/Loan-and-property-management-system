<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmListingApplication extends Model
{
    protected $table = 'pm_listing_applications';

    protected $fillable = [
        'property_unit_id',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'status',
        'notes',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
