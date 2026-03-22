<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLoan extends Model
{
    protected $fillable = [
        'employee_id',
        'account_ref',
        'principal',
        'balance',
        'next_due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'balance' => 'decimal:2',
            'next_due_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
