<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPayrollPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSED = 'processed';

    protected $fillable = [
        'period_start', 'period_end', 'label', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingPayrollLine::class, 'accounting_payroll_period_id');
    }
}
