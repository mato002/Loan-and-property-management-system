<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLoanApplication extends Model
{
    protected $fillable = [
        'employee_id',
        'reference',
        'product',
        'amount',
        'stage',
        'status',
        'form_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'form_meta' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
