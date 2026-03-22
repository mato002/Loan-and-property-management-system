<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAssetStockItem extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'loan_asset_category_id',
        'loan_asset_measurement_unit_id',
        'asset_code',
        'name',
        'quantity',
        'unit_cost',
        'location',
        'serial_number',
        'status',
        'acquisition_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'acquisition_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LoanAssetCategory::class, 'loan_asset_category_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(LoanAssetMeasurementUnit::class, 'loan_asset_measurement_unit_id');
    }
}
