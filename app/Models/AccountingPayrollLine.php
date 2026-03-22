<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPayrollLine extends Model
{
    protected $fillable = [
        'accounting_payroll_period_id', 'employee_id', 'gross_pay',
        'deductions', 'net_pay', 'payslip_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
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
