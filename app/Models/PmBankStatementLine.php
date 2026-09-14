<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmBankStatementLine extends Model
{
    public const MATCH_MATCHED = 'matched';

    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_BANK_ONLY = 'bank_only';

    protected $table = 'pm_bank_statement_lines';

    protected $fillable = [
        'agent_user_id',
        'pm_bank_statement_id',
        'source_key',
        'line_type',
        'direction',
        'txn_date',
        'reference',
        'amount',
        'running_balance',
        'counterparty',
        'phone',
        'narration',
        'match_status',
        'matched_type',
        'pm_payment_id',
        'pm_ezen_receipt_register_id',
        'unassigned_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_bank_statement_lines.agent_user_id', (int) auth()->id());
        });
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(PmBankStatement::class, 'pm_bank_statement_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function ezenReceipt(): BelongsTo
    {
        return $this->belongsTo(PmEzenReceiptRegister::class, 'pm_ezen_receipt_register_id');
    }

    public function displayMatchStatus(): string
    {
        return match ($this->match_status) {
            self::MATCH_MATCHED => 'Matched',
            self::MATCH_BANK_ONLY => 'Bank only',
            default => 'Unmatched',
        };
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === 'debit' ? -abs($amount) : abs($amount);
    }
}
