<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAssetCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(LoanAssetStockItem::class, 'loan_asset_category_id');
    }
}
