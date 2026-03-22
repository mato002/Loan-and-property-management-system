<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentPackage extends Model
{
    protected $fillable = [
        'name',
        'rate_label',
        'minimum_label',
        'status',
    ];

    public function investors(): HasMany
    {
        return $this->hasMany(Investor::class);
    }
}
