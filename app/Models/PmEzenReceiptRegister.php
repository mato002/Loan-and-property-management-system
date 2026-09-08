<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmEzenReceiptRegister extends Model
{
    public const LINK_IMPORTED = 'imported';

    public const LINK_TENANT = 'tenant_linked';

    public const LINK_PAYMENT = 'payment_linked';

    public const LINK_NO_TENANT = 'tenant_missing';

    protected $table = 'pm_ezen_receipt_register';

    protected $fillable = [
        'agent_user_id',
        'ezen_receipt_no',
        'ref_no',
        'property_code',
        'unit_label',
        'tnt_account',
        'register_tenant_name',
        'phone',
        'particulars',
        'amount',
        'txn_date',
        'banking_date',
        'receipted_to',
        'done_by',
        'pm_tenant_id',
        'pm_payment_id',
        'link_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'txn_date' => 'date',
            'banking_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_ezen_receipt_register.agent_user_id', (int) auth()->id());
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function displayTenantName(): string
    {
        return trim((string) ($this->tenant?->name ?? $this->register_tenant_name ?? ''));
    }

    public function displayPaymentMethod(): string
    {
        $ref = strtoupper(trim((string) ($this->ref_no ?? '')));
        if ($ref !== '' && $ref !== 'CASH' && preg_match('/^U[A-Z0-9]{9,}$/', $ref) === 1) {
            return 'M-Pesa';
        }

        $receiptedTo = strtoupper(trim((string) ($this->receipted_to ?? '')));
        if (str_contains($receiptedTo, 'M-PESA') || str_contains($receiptedTo, 'MPESA')) {
            return 'M-Pesa';
        }
        if (str_contains($receiptedTo, 'CASH')) {
            return 'Cash';
        }
        if ($receiptedTo !== '') {
            return $this->receipted_to;
        }

        return '—';
    }
}
