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
        'paid_to_kind',
        'paid_to_landlord_id',
        'paid_to_name',
        'paid_to_note',
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

            AgentWorkspaceScope::whereWorkspaceOwner($query, 'pm_bank_statement_lines.agent_user_id');
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

    public function paidToLandlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_to_landlord_id');
    }

    /**
     * A line is matched only when the money is already on a known tenant.
     * A shared M-Pesa code in the Unmatched queue is not a tenant allocation.
     */
    public function scopeAllocatedToTenant(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('match_status'), self::MATCH_MATCHED)
            ->where(function (Builder $type) {
                $type->whereNull($this->qualifyColumn('matched_type'))
                    ->orWhere($this->qualifyColumn('matched_type'), '!=', 'unassigned');
            })
            ->where(function (Builder $known) {
                $known->whereHas('payment', fn (Builder $payment) => $payment->where('pm_tenant_id', '>', 0))
                    ->orWhereHas('ezenReceipt', function (Builder $receipt) {
                        $receipt->where('pm_tenant_id', '>', 0)
                            ->orWhere(function (Builder $name) {
                                $name->whereNotNull('register_tenant_name')
                                    ->where('register_tenant_name', '!=', '');
                            });
                    });
            });
    }

    /**
     * M-Pesa credits that still have no tenant, including ones already sitting in Unmatched.
     */
    public function scopeAwaitingTenant(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->where($table.'.match_status', '!=', self::MATCH_BANK_ONLY)
            ->where(function (Builder $waiting) use ($table) {
                $waiting->where($table.'.match_status', self::MATCH_UNMATCHED)
                    ->orWhere($table.'.matched_type', 'unassigned')
                    ->orWhere(function (Builder $orphan) use ($table) {
                        $orphan->where($table.'.match_status', self::MATCH_MATCHED)
                            ->where(function (Builder $type) use ($table) {
                                $type->whereNull($table.'.matched_type')
                                    ->orWhere($table.'.matched_type', '!=', 'unassigned');
                            })
                            ->whereDoesntHave('payment', fn (Builder $payment) => $payment->where('pm_tenant_id', '>', 0))
                            ->whereDoesntHave('ezenReceipt', function (Builder $receipt) {
                                $receipt->where('pm_tenant_id', '>', 0)
                                    ->orWhere(function (Builder $name) {
                                        $name->whereNotNull('register_tenant_name')
                                            ->where('register_tenant_name', '!=', '');
                                    });
                            });
                    });
            });
    }

    public function isAllocatedToTenant(): bool
    {
        if ((string) $this->match_status !== self::MATCH_MATCHED) {
            return false;
        }
        if ((string) $this->matched_type === 'unassigned') {
            return false;
        }

        return $this->matchedTenantId() !== null || $this->matchedTenantName() !== '';
    }

    public function displayMatchStatus(): string
    {
        if ((string) $this->match_status === self::MATCH_BANK_ONLY) {
            return match ((string) $this->paid_to_kind) {
                'landlord' => 'Landlord',
                'bank_charge' => 'Bank charge',
                'other' => 'Other payee',
                default => 'Bank only',
            };
        }

        return $this->isAllocatedToTenant() ? 'Matched' : 'Unmatched';
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
        if ((string) $this->match_status === self::MATCH_BANK_ONLY && trim((string) $this->paid_to_name) !== '') {
            $note = trim((string) $this->paid_to_note);
            $who = trim((string) $this->paid_to_name);

            return match ((string) $this->paid_to_kind) {
                'landlord' => 'Paid to landlord '.$who.($note !== '' ? ' · '.$note : ''),
                'bank_charge' => 'Bank charge'.($note !== '' ? ' · '.$note : ''),
                default => 'Paid to '.$who.($note !== '' ? ' · '.$note : ''),
            };
        }

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
                self::MATCH_BANK_ONLY => 'Not a tenant receipt — record who was paid',
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
