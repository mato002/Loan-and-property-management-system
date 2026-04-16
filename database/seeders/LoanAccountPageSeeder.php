<?php

namespace Database\Seeders;

use App\Models\AccountingSalaryAdvance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the loan demo account user has an employee row and at least one salary advance row
 * so "My salary advance" and approval inbox submissions show real data after db:seed.
 */
class LoanAccountPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        $loanUser = User::query()->where('email', 'loan.user@system.com')->first();
        if ($loanUser === null) {
            return;
        }

        $employee = Employee::query()->firstOrCreate(
            ['email' => $loanUser->email],
            [
                'employee_number' => 'EMP-DEMO-ACCT',
                'first_name' => 'Loan',
                'last_name' => 'Demo User',
                'phone' => '+254712009900',
                'department' => 'Operations',
                'job_title' => 'Demo staff',
                'branch' => 'Nairobi HQ',
                'hire_date' => now()->subYears(2)->toDateString(),
            ]
        );

        if (AccountingSalaryAdvance::query()->where('employee_id', $employee->id)->exists()) {
            return;
        }

        AccountingSalaryAdvance::query()->create([
            'employee_id' => $employee->id,
            'amount' => 12000,
            'currency' => 'KES',
            'status' => AccountingSalaryAdvance::STATUS_PENDING,
            'requested_on' => now()->subDays(3)->toDateString(),
            'notes' => 'Seeded for My account → Salary advance',
        ]);
    }
}
