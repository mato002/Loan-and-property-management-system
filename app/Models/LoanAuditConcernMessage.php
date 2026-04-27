<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAuditConcernMessage extends Model
{
    protected $fillable = [
        'loan_audit_concern_id',
        'user_id',
        'message',
    ];

    public function concern(): BelongsTo
    {
        return $this->belongsTo(LoanAuditConcern::class, 'loan_audit_concern_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
