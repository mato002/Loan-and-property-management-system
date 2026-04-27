<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBranchRegionChange extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'loan_branch_id',
        'from_loan_region_id',
        'to_loan_region_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'status',
        'effective_at',
        'approved_at',
        'rejected_at',
        'reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(LoanBranch::class, 'loan_branch_id');
    }

    public function fromRegion(): BelongsTo
    {
        return $this->belongsTo(LoanRegion::class, 'from_loan_region_id');
    }

    public function toRegion(): BelongsTo
    {
        return $this->belongsTo(LoanRegion::class, 'to_loan_region_id');
    }
}

