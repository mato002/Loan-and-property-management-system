<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTenantNotice extends Model
{
    protected $table = 'pm_tenant_notices';

    protected $fillable = [
        'pm_tenant_id',
        'property_unit_id',
        'notice_type',
        'status',
        'due_on',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
