<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAuditConcern extends Model
{
    protected $fillable = [
        'loan_access_log_id',
        'opened_by_user_id',
        'owner_user_id',
        'status',
        'priority',
        'title',
        'reason',
    ];

    public function accessLog(): BelongsTo
    {
        return $this->belongsTo(LoanAccessLog::class, 'loan_access_log_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LoanAuditConcernMessage::class)->latest();
    }
}
