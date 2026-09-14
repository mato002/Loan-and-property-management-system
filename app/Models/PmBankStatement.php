<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmBankStatement extends Model
{
    protected $table = 'pm_bank_statements';

    protected $fillable = [
        'agent_user_id',
        'source_key',
        'bank_name',
        'account_no',
        'account_name',
        'currency',
        'period_from',
        'period_to',
        'opening_balance',
        'closing_balance',
        'total_debit',
        'total_credit',
        'source_filename',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_bank_statements.agent_user_id', (int) auth()->id());
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PmBankStatementLine::class, 'pm_bank_statement_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function periodLabel(): string
    {
        $from = $this->period_from?->format('d/m/Y') ?? '—';
        $to = $this->period_to?->format('d/m/Y') ?? '—';

        return $from.' to '.$to;
    }
}
