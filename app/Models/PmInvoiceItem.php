<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmInvoiceItem extends Model
{
    protected $table = 'pm_invoice_items';

    protected $fillable = [
        'invoice_id',
        'type',
        'amount',
        'account_id',
        'agent_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'invoice_id');
    }
}

