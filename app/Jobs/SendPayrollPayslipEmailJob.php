<?php

namespace App\Jobs;

use App\Models\AccountingPayrollLine;
use App\Models\AccountingPayrollPeriod;
use App\Services\Property\PropertyMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPayrollPayslipEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $payrollPeriodId,
        public int $payrollLineId
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $period = AccountingPayrollPeriod::query()->find($this->payrollPeriodId);
        $line = AccountingPayrollLine::query()->with('employee')->find($this->payrollLineId);
        if (! $period || ! $line || (int) $line->accounting_payroll_period_id !== (int) $period->id) {
            return;
        }

        $employee = $line->employee;
        if (! $employee || ! $employee->email) {
            return;
        }

        Mail::raw(
            'Payslip '.($line->payslip_number ?: ('PSL-'.$period->id.'-'.$line->id)).' for '.$period->label.' | Net pay: '.PropertyMoney::kes((float) $line->net_pay),
            fn ($mail) => $mail->to($employee->email)->subject('Payslip '.$period->label)
        );

        $line->forceFill(['email_sent_at' => now()])->save();
    }
}
