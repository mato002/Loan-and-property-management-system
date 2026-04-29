<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmPaymentAllocation extends Model
{
    protected $table = 'pm_payment_allocations';

    protected $fillable = [
        'pm_payment_id',
        'pm_invoice_id',
        'lease_deposit_line_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function leaseDepositLine(): BelongsTo
    {
        return $this->belongsTo(LeaseDepositLine::class, 'lease_deposit_line_id');
    }
}
