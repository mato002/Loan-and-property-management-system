<?php

namespace App\Models;

use App\Models\Concerns\FallbackPrimaryKeyWhenNoAutoIncrement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanBookPayment extends Model
{
    use FallbackPrimaryKeyWhenNoAutoIncrement;

    public const STATUS_UNPOSTED = 'unposted';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REJECTED = 'rejected';

    public const KIND_NORMAL = 'normal';

    public const KIND_PREPAYMENT = 'prepayment';

    public const KIND_OVERPAYMENT = 'overpayment';

    public const KIND_MERGED = 'merged';

    public const KIND_C2B_REVERSAL = 'c2b_reversal';

    protected $fillable = [
        'reference',
        'loan_book_loan_id',
        'amount',
        'currency',
        'channel',
        'status',
        'payment_kind',
        'merged_into_payment_id',
        'mpesa_receipt_number',
        'payer_msisdn',
        'transaction_at',
        'posted_at',
        'posted_by',
        'validated_at',
        'validated_by',
        'notes',
        'message',
        'created_by',
        'accounting_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_at' => 'datetime',
            'posted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_payment_id');
    }

    public function mergedChildren(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_payment_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }

    public function scopeNotMergedChild(Builder $q): Builder
    {
        return $q->whereNull('merged_into_payment_id');
    }

    public function scopeUnpostedQueue(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_UNPOSTED)->notMergedChild();
    }

    public function scopeProcessedQueue(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PROCESSED)->notMergedChild();
    }

    public function canEdit(): bool
    {
        return $this->status === self::STATUS_UNPOSTED
            && $this->merged_into_payment_id === null
            && $this->payment_kind !== self::KIND_MERGED;
    }
}
