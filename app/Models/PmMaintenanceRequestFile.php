<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMaintenanceRequestFile extends Model
{
    protected $table = 'pm_maintenance_request_files';

    protected $fillable = [
        'pm_maintenance_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PmMaintenanceRequest::class, 'pm_maintenance_request_id');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }
}
