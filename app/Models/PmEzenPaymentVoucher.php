<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmEzenPaymentVoucher extends Model
{
    public const LINK_IMPORTED = 'imported';

    public const LINK_REMITTANCE = 'remittance_posted';

    public const LINK_EXPENSE = 'expense_posted';

    public const LINK_UNMATCHED = 'unmatched';

    public const LINK_SKIPPED = 'skipped';

    public const CATEGORY_REMITTANCE = 'remittance';

    public const CATEGORY_COMMISSION = 'commission';

    public const CATEGORY_TAX = 'tax';

    public const CATEGORY_EXPENSE = 'expense';

    protected $table = 'pm_ezen_payment_vouchers';

    protected $fillable = [
        'agent_user_id',
        'ezen_voucher_no',
        'method',
        'ref_no',
        'txn_date',
        'particulars',
        'paid_from',
        'paid_to',
        'payee_name',
        'property_code',
        'category',
        'period_month',
        'amount',
        'recorded_by',
        'property_id',
        'landlord_id',
        'pm_landlord_payout_id',
        'pm_accounting_entry_id',
        'link_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'txn_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_ezen_payment_vouchers.agent_user_id', (int) auth()->id());
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(PmLandlordPayout::class, 'pm_landlord_payout_id');
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(PmAccountingEntry::class, 'pm_accounting_entry_id');
    }

    public function displayPayee(): string
    {
        $paidTo = trim((string) ($this->paid_to ?? ''));
        if ($paidTo !== '') {
            return $paidTo;
        }

        return trim((string) ($this->payee_name ?? '')) ?: '—';
    }

    public function displayCategory(): string
    {
        return match ($this->category) {
            self::CATEGORY_REMITTANCE => 'Rent remittance',
            self::CATEGORY_COMMISSION => 'Commission',
            self::CATEGORY_TAX => 'Tax / statutory',
            default => 'Expense',
        };
    }

    public function displayLinkStatus(): string
    {
        return match ($this->link_status) {
            self::LINK_REMITTANCE => 'Posted as payout',
            self::LINK_EXPENSE => 'Posted as expense',
            self::LINK_UNMATCHED => 'Unmatched payee',
            self::LINK_SKIPPED => 'Skipped',
            default => 'Imported',
        };
    }
}
