<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSalaryAdvance extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'employee_id',
        'amount',
        'currency',
        'status',
        'requested_on',
        'reason_for_request',
        'approved_by',
        'approved_amount',
        'approved_at',
        'settled_on',
        'form_meta',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'requested_on' => 'date',
            'approved_at' => 'datetime',
            'settled_on' => 'date',
            'form_meta' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
