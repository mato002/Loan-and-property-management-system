<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPortfolio extends Model
{
    protected $fillable = [
        'employee_id',
        'portfolio_code',
        'active_loans',
        'outstanding_amount',
        'par_rate',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_amount' => 'decimal:2',
            'par_rate' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
