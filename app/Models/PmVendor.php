<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmVendor extends Model
{
    protected $table = 'pm_vendors';

    protected $fillable = [
        'name',
        'category',
        'phone',
        'email',
        'status',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    public function maintenanceJobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_vendor_id');
    }
}
