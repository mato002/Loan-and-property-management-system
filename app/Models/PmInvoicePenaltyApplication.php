<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmInvoicePenaltyApplication extends Model
{
    protected $table = 'pm_invoice_penalty_applications';

    protected $fillable = [
        'pm_invoice_id',
        'pm_penalty_rule_id',
        'threshold_date',
        'amount',
        'applied_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'threshold_date' => 'date',
            'applied_at' => 'datetime',
            'reversed_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PmPenaltyRule::class, 'pm_penalty_rule_id');
    }
}
