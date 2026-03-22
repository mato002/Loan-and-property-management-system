<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investor extends Model
{
    protected $fillable = [
        'investment_package_id',
        'name',
        'email',
        'phone',
        'committed_amount',
        'accrued_interest',
        'maturity_date',
    ];

    protected function casts(): array
    {
        return [
            'committed_amount' => 'decimal:2',
            'accrued_interest' => 'decimal:2',
            'maturity_date' => 'date',
        ];
    }

    public function investmentPackage(): BelongsTo
    {
        return $this->belongsTo(InvestmentPackage::class);
    }
}
