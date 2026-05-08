<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPayrollPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'period_start',
        'period_end',
        'period_month',
        'period_year',
        'label',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'notes',
        'agent_user_id',
        'created_by',
        'approved_by',
        'posted_by',
        'reversed_by',
        'approved_at',
        'posted_at',
        'reversed_at',
        'journal_batch_id',
        'reversal_journal_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingPayrollLine::class, 'accounting_payroll_period_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalBatch::class, 'journal_batch_id');
    }

    public function reversalJournalBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalBatch::class, 'reversal_journal_batch_id');
    }
}
