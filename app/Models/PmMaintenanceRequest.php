<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmMaintenanceRequest extends Model
{
    protected $table = 'pm_maintenance_requests';

    protected $fillable = [
        'property_unit_id',
        'reported_by_user_id',
        'category',
        'description',
        'urgency',
        'status',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_maintenance_request_id');
    }
}
