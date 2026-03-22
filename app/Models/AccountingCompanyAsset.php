<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingCompanyAsset extends Model
{
    protected $fillable = [
        'asset_code', 'name', 'category', 'location', 'branch',
        'acquired_on', 'cost', 'net_book_value', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'cost' => 'decimal:2',
            'net_book_value' => 'decimal:2',
        ];
    }
}
