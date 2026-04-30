<?php

namespace App\Models;

use App\Models\Concerns\FallbackPrimaryKeyWhenNoAutoIncrement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanBookApplication extends Model
{
    use FallbackPrimaryKeyWhenNoAutoIncrement;

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
        'term_value',
        'term_unit',
        'interest_rate',
        'interest_rate_period',
        'purpose',
        'stage',
        'borrower_category',
        'client_loan_sequence',
        'suggested_limit',
        'risk_flags_json',
        'classification_reason_json',
        'branch',
        'notes',
        'submission_source',
        'submitted_at',
        'form_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'suggested_limit' => 'decimal:2',
            'submitted_at' => 'datetime',
            'form_meta' => 'array',
            'risk_flags_json' => 'array',
            'classification_reason_json' => 'array',
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
