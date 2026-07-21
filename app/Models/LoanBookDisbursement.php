<?php

namespace App\Models;

use App\Models\Concerns\FallbackPrimaryKeyWhenNoAutoIncrement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBookDisbursement extends Model
{
    use FallbackPrimaryKeyWhenNoAutoIncrement;

    protected $fillable = [
        'loan_book_loan_id',
        'amount',
        'reference',
        'method',
        'disbursed_at',
        'notes',
        'accounting_journal_entry_id',
        'payout_status',
        'payout_provider',
        'payout_phone',
        'payout_conversation_id',
        'payout_originator_conversation_id',
        'payout_transaction_id',
        'payout_result_code',
        'payout_result_desc',
        'payout_requested_at',
        'payout_completed_at',
        'payout_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'date',
            'payout_requested_at' => 'datetime',
            'payout_completed_at' => 'datetime',
            'payout_meta' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanBookLoan::class, 'loan_book_loan_id');
    }

    public function accountingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'accounting_journal_entry_id');
    }

    public function isJournalReversed(): bool
    {
        $this->loadMissing('accountingJournalEntry');

        return ($this->accountingJournalEntry?->status ?? '') === AccountingJournalEntry::STATUS_REVERSED;
    }

    /**
     * Payout status for UI — journal reversal overrides stored payout_status.
     */
    public function effectivePayoutStatus(): string
    {
        if ($this->isJournalReversed()) {
            return 'reversed';
        }

        return strtolower((string) ($this->payout_status ?? 'completed'));
    }

    /**
     * @return array{label: string, class: string}
     */
    public function payoutStatusBadge(): array
    {
        return match ($this->effectivePayoutStatus()) {
            'reversed' => ['label' => 'Reversed', 'class' => 'bg-violet-100 text-violet-800'],
            'failed' => ['label' => 'Failed', 'class' => 'bg-red-100 text-red-700'],
            'pending', 'queued', 'processing' => ['label' => 'Pending', 'class' => 'bg-amber-100 text-amber-700'],
            'completed' => ['label' => 'Completed', 'class' => 'bg-emerald-100 text-emerald-700'],
            default => ['label' => ucfirst($this->effectivePayoutStatus()), 'class' => 'bg-slate-100 text-slate-700'],
        };
    }

    /**
     * True when no live disbursement blocks recording a new payout on this loan.
     */
    public function blocksNewDisbursement(): bool
    {
        if (! $this->accounting_journal_entry_id) {
            return true;
        }

        return ! $this->isJournalReversed();
    }

    public function canBeRemoved(): bool
    {
        if (! $this->accounting_journal_entry_id) {
            return true;
        }

        $this->loadMissing('accountingJournalEntry');
        $entry = $this->accountingJournalEntry;

        return $entry !== null && ($entry->status ?? '') === AccountingJournalEntry::STATUS_REVERSED;
    }

    public function removalBlockReason(): ?string
    {
        if ($this->canBeRemoved()) {
            return null;
        }

        if ($this->accounting_journal_entry_id) {
            return 'This disbursement is linked to a posted journal entry. Open the journal under Accounting and reverse it first, then remove this line and record the correct amount again.';
        }

        return 'This disbursement cannot be removed.';
    }
}
