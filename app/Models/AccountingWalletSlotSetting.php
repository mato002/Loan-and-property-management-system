<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingWalletSlotSetting extends Model
{
    protected $fillable = [
        'slot_key',
        'accounting_chart_account_id',
    ];

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }
}
