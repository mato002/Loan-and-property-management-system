<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanBookApplication extends Model
{
    public const STAGE_SUBMITTED = 'submitted';

    public const STAGE_CREDIT_REVIEW = 'credit_review';

    public const STAGE_APPROVED = 'approved';

    public const STAGE_DECLINED = 'declined';

    public const STAGE_DISBURSED = 'disbursed';

    protected $fillable = [
        'reference',
        'loan_client_id',
        'product_name',
        'amount_requested',
        'term_months',
        'purpose',
        'stage',
        'branch',
        'notes',
        'submission_source',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(LoanBookLoan::class);
    }
}
