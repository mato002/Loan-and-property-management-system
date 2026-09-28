<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PmEzenBill extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNPAID = 'unpaid';

    protected $table = 'pm_ezen_bills';

    protected $fillable = [
        'agent_user_id',
        'source_key',
        'ezen_bill_no',
        'vendor_invoice_no',
        'bill_date',
        'due_date',
        'vendor_name',
        'memo',
        'total_amount',
        'total_paid',
        'amount_due',
        'listing_status',
        'payment_status',
        'pm_supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'bill_date' => 'date',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_ezen_bills.agent_user_id', (int) auth()->id());
        });
    }

    public function displayListingStatus(): string
    {
        return ucfirst((string) ($this->listing_status ?? 'closed'));
    }

    public function displayPaymentStatus(): string
    {
        return match ($this->payment_status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_PARTIAL => 'Partial',
            default => 'Unpaid',
        };
    }
}
