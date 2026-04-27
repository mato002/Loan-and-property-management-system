<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAuditAlert extends Model
{
    protected $fillable = [
        'loan_access_log_id',
        'alert_rule',
        'severity',
        'status',
        'assigned_to_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function accessLog(): BelongsTo
    {
        return $this->belongsTo(LoanAccessLog::class, 'loan_access_log_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
