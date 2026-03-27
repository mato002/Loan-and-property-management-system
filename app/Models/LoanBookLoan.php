<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanBookLoan extends Model
{
    public const STATUS_PENDING_DISBURSEMENT = 'pending_disbursement';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_WRITTEN_OFF = 'written_off';

    public const STATUS_RESTRUCTURED = 'restructured';

    protected $fillable = [
        'loan_number',
        'loan_book_application_id',
        'loan_client_id',
        'product_name',
        'principal',
        'principal_outstanding',
        'balance',
        'interest_rate',
        'interest_outstanding',
        'fees_outstanding',
        'status',
        'dpd',
        'disbursed_at',
        'maturity_date',
        'is_checkoff',
        'checkoff_employer',
        'branch',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'principal_outstanding' => 'decimal:2',
            'balance' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'interest_outstanding' => 'decimal:2',
            'fees_outstanding' => 'decimal:2',
            'is_checkoff' => 'boolean',
            'disbursed_at' => 'datetime',
            'maturity_date' => 'date',
        ];
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanBookApplication::class, 'loan_book_application_id');
    }

    public function loanBranch(): BelongsTo
    {
        return $this->belongsTo(LoanBranch::class, 'loan_branch_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(LoanBookDisbursement::class);
    }

    public function collectionEntries(): HasMany
    {
        return $this->hasMany(LoanBookCollectionEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanBookPayment::class, 'loan_book_loan_id');
    }
}
