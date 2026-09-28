<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPayrollLine extends Model
{
    protected $fillable = [
        'accounting_payroll_period_id',
        'employee_id',
        'basic_pay',
        'allowances',
        'gross_pay',
        'deductions',
        'net_pay',
        'payslip_number',
        'notes',
        'email_sent_at',
        'payment_status',
        'payment_date',
        'payment_reference',
        'payout_provider',
        'payout_phone',
        'payout_status',
        'payout_conversation_id',
        'payout_originator_conversation_id',
        'payout_meta',
    ];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'basic_pay' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'email_sent_at' => 'datetime',
            'payment_date' => 'date',
            'payout_meta' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPayrollPeriod::class, 'accounting_payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
