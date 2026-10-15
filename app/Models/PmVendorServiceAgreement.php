<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmVendorServiceAgreement extends Model
{
    protected $table = 'pm_vendor_service_agreements';

    protected $fillable = [
        'agent_user_id', 'pm_vendor_id', 'property_id', 'service_name',
        'monthly_amount', 'starts_on', 'ends_on', 'billing_day', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'billing_day' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(PmVendor::class, 'pm_vendor_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(PmEzenBill::class, 'pm_vendor_service_agreement_id');
    }
}
