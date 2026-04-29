<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingWalletSlotSetting extends Model
{
    protected $fillable = [
        'slot_key',
        'accounting_chart_account_id',
        'approval_status',
        'last_updated_by',
        'approved_by',
        'approved_at',
        'history_json',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'history_json' => 'array',
        ];
    }

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingChartAccount::class, 'accounting_chart_account_id');
    }
}
