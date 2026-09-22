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

    public function displayPhone(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    public function matchedTenantId(): ?int
    {
        $fromReceipt = $this->ezenReceipt?->pm_tenant_id;
        if ($fromReceipt) {
            return (int) $fromReceipt;
        }
        $fromPayment = $this->payment?->pm_tenant_id;
        if ($fromPayment) {
            return (int) $fromPayment;
        }

        return null;
    }

    public function matchedTenantAccount(): string
    {
        $receipt = $this->ezenReceipt;
        if ($receipt && trim((string) $receipt->tnt_account) !== '') {
            return trim((string) $receipt->tnt_account);
        }

        $account = $this->payment?->tenant?->account_number;

        return trim((string) $account);
    }

    public function matchedUnitLabel(): string
    {
        $receipt = $this->ezenReceipt;
        if (! $receipt) {
            return '';
        }
        $property = trim((string) ($receipt->property_code ?? ''));
        $unit = trim((string) ($receipt->unit_label ?? ''));

        return trim($property.($property !== '' && $unit !== '' ? ' · ' : '').$unit);
    }

    public function matchedTenantName(): string
    {
        $fromReceipt = $this->ezenReceipt?->displayTenantName();
        if (is_string($fromReceipt) && trim($fromReceipt) !== '') {
            return trim($fromReceipt);
        }

        return trim((string) ($this->payment?->tenant?->name ?? ''));
    }

    public function matchReason(): string
    {
        $ref = strtoupper(trim((string) $this->reference));

        return match ($this->matched_type) {
            'ezen_receipt' => $ref !== ''
                ? 'M-Pesa code '.$ref.' = EZEN receipt '.trim((string) ($this->ezenReceipt?->ezen_receipt_no ?? ''))
                : 'EZEN receipt by M-Pesa code',
            'payment' => $ref !== ''
                ? 'M-Pesa code '.$ref.' = payment'
                : 'Payment by M-Pesa code',
            'unassigned' => 'Same M-Pesa code in Unmatched (not a tenant yet)',
            default => match ($this->match_status) {
                self::MATCH_BANK_ONLY => 'Cheque or bank charge — not a tenant receipt',
                default => $ref !== ''
                    ? 'No EZEN receipt or payment with M-Pesa code '.$ref
                    : 'No unique M-Pesa code',
            },
        };
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === 'debit' ? -abs($amount) : abs($amount);
    }
}
