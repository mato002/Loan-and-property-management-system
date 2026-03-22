<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAssetMeasurementUnit extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
        'description',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(LoanAssetStockItem::class, 'loan_asset_measurement_unit_id');
    }
}
