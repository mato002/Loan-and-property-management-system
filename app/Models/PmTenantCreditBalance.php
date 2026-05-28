<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmTenantCreditBalance extends Model
{
    protected $fillable = [
        'pm_tenant_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PmTenantCreditTransaction::class, 'pm_tenant_id', 'pm_tenant_id')
            ->orderByDesc('id');
    }
}
