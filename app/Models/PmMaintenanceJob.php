<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMaintenanceJob extends Model
{
    protected $table = 'pm_maintenance_jobs';

    protected $fillable = [
        'pm_maintenance_request_id',
        'pm_vendor_id',
        'quote_amount',
        'status',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PmMaintenanceRequest::class, 'pm_maintenance_request_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(PmVendor::class, 'pm_vendor_id');
    }
}
