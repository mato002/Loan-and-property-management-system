<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\StaffGroup;
use App\Models\StaffLeave;
use App\Models\StaffLoan;
use App\Models\StaffLoanApplication;
use App\Models\StaffPortfolio;
use App\Models\User;
use App\Models\WorkplanItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EmployeeModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::query()->where('employee_number', 'EMP-1001')->exists()) {
            if ($this->command) {
                $this->command->info('Employee demo data already present (EMP-1001). Skipping EmployeeModuleSeeder.');
            }

            return;
        }

        $employees = collect([
            [
                'employee_number' => 'EMP-1001',
                'first_name' => 'Amina',
                'last_name' => 'Otieno',
                'email' => 'amina.otieno@loan.local',
                'phone' => '+254712000001',
                'department' => 'Credit',
                'job_title' => 'Senior loan officer',
                'branch' => 'Nairobi HQ',
                'hire_date' => '2019-04-01',
            ],
            [
                'employee_number' => 'EMP-1002',
                'first_name' => 'James',
                'last_name' => 'Kariuki',
                'email' => 'james.kariuki@loan.local',
                'phone' => '+254712000002',
                'department' => 'Collections',
                'job_title' => 'Field collector',
                'branch' => 'Westlands',
                'hire_date' => '2020-01-15',
            ],
            [
                'employee_number' => 'EMP-1003',
                'first_name' => 'Mary',
                'last_name' => 'Wanjala',
                'email' => 'mary.wanjala@loan.local',
                'phone' => '+254712000003',
                'department' => 'Operations',
                'job_title' => 'Branch admin',
                'branch' => 'Nairobi HQ',
                'hire_date' => '2018-08-20',
            ],
            [
                'employee_number' => 'EMP-1004',
                'first_name' => 'Peter',
                'last_name' => 'Mwangi',
                'email' => 'peter.mwangi@loan.local',
                'phone' => '+254712000004',
                'department' => 'Risk',
                'job_title' => 'Risk analyst',
                'branch' => 'Mombasa',
                'hire_date' => '2021-06-01',
            ],
            [
                'employee_number' => 'EMP-1005',
                'first_name' => 'Linda',
                'last_name' => 'Akinyi',
                'email' => 'linda.akinyi@loan.local',
                'phone' => '+254712000005',
                'department' => 'Credit',
                'job_title' => 'Credit committee secretary',
                'branch' => 'Kisumu',
                'hire_date' => '2017-11-10',
            ],
            [
                'employee_number' => 'EMP-1006',
                'first_name' => 'Tom',
                'last_name' => 'Wekesa',
                'email' => 'tom.wekesa@loan.local',
                'phone' => '+254712000006',
                'department' => 'Collections',
                'job_title' => 'Team lead',
                'branch' => 'Eldoret',
                'hire_date' => '2019-02-28',
            ],
        ])->map(fn (array $row) => Employee::create($row));

        $e = fn (int $i) => $employees[$i - 1];

        $north = StaffGroup::create([
            'name' => 'North region collectors',
            'description' => 'Field collections and follow-ups — Nairobi north corridor.',
        ]);
        $north->employees()->attach([$e(2)->id, $e(6)->id, $e(1)->id]);

        $credit = StaffGroup::create([
            'name' => 'Credit committee — Tier A',
            'description' => 'Approvals up to Ksh 500,000.',
        ]);
        $credit->employees()->attach([$e(1)->id, $e(5)->id, $e(4)->id]);

        $disburse = StaffGroup::create([
            'name' => 'Disbursement desk',
            'description' => 'Same-day payouts and reconciliations.',
        ]);
        $disburse->employees()->attach([$e(3)->id, $e(1)->id]);

        $start = Carbon::parse('2025-03-10');
        StaffLeave::create([
            'employee_id' => $e(2)->id,
            'leave_type' => 'Sick',
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'days' => 2,
            'status' => 'approved',
            'notes' => null,
        ]);
        $annualStart = Carbon::parse('2025-04-01');
        StaffLeave::create([
            'employee_id' => $e(3)->id,
            'leave_type' => 'Annual',
            'start_date' => $annualStart,
            'end_date' => $annualStart->copy()->addDays(7),
            'days' => 8,
            'status' => 'pending',
            'notes' => 'Family travel',
        ]);
        StaffLeave::create([
            'employee_id' => $e(1)->id,
            'leave_type' => 'Annual',
            'start_date' => Carbon::parse('2025-03-18'),
            'end_date' => Carbon::parse('2025-03-22'),
            'days' => 5,
            'status' => 'approved',
            'notes' => null,
        ]);

        StaffPortfolio::create([
            'employee_id' => $e(1)->id,
            'portfolio_code' => 'PF-NRB-01',
            'active_loans' => 186,
            'outstanding_amount' => 42100000,
            'par_rate' => 2.10,
        ]);
        StaffPortfolio::create([
            'employee_id' => $e(2)->id,
            'portfolio_code' => 'PF-WST-02',
            'active_loans' => 94,
            'outstanding_amount' => 18750000,
            'par_rate' => 3.40,
        ]);
        StaffPortfolio::create([
            'employee_id' => $e(6)->id,
            'portfolio_code' => 'PF-ELD-04',
            'active_loans' => 71,
            'outstanding_amount' => 11200000,
            'par_rate' => 4.00,
        ]);

        $app1 = StaffLoanApplication::create([
            'employee_id' => $e(1)->id,
            'reference' => null,
            'product' => 'Salary advance',
            'amount' => 45000,
            'stage' => 'HR clearance',
            'status' => 'pending',
        ]);
        $app1->update(['reference' => 'SLA-'.str_pad((string) $app1->id, 5, '0', STR_PAD_LEFT)]);

        $app2 = StaffLoanApplication::create([
            'employee_id' => $e(4)->id,
            'reference' => null,
            'product' => 'Emergency',
            'amount' => 120000,
            'stage' => 'Credit review',
            'status' => 'pending',
        ]);
        $app2->update(['reference' => 'SLA-'.str_pad((string) $app2->id, 5, '0', STR_PAD_LEFT)]);

        $app3 = StaffLoanApplication::create([
            'employee_id' => $e(3)->id,
            'reference' => null,
            'product' => 'Asset (laptop)',
            'amount' => 85000,
            'stage' => 'Disbursement',
            'status' => 'approved',
        ]);
        $app3->update(['reference' => 'SLA-'.str_pad((string) $app3->id, 5, '0', STR_PAD_LEFT)]);

        $loan1 = StaffLoan::create([
            'employee_id' => $e(2)->id,
            'account_ref' => null,
            'principal' => 200000,
            'balance' => 118400,
            'next_due_date' => Carbon::parse('2025-04-01'),
            'status' => 'current',
        ]);
        $loan1->update(['account_ref' => 'STF-'.str_pad((string) $loan1->id, 5, '0', STR_PAD_LEFT)]);

        $loan2 = StaffLoan::create([
            'employee_id' => $e(5)->id,
            'account_ref' => null,
            'principal' => 350000,
            'balance' => 290100,
            'next_due_date' => Carbon::parse('2025-03-28'),
            'status' => 'current',
        ]);
        $loan2->update(['account_ref' => 'STF-'.str_pad((string) $loan2->id, 5, '0', STR_PAD_LEFT)]);

        $loan3 = StaffLoan::create([
            'employee_id' => $e(6)->id,
            'account_ref' => null,
            'principal' => 75000,
            'balance' => 12300,
            'next_due_date' => Carbon::parse('2025-03-20'),
            'status' => 'arrears',
        ]);
        $loan3->update(['account_ref' => 'STF-'.str_pad((string) $loan3->id, 5, '0', STR_PAD_LEFT)]);

        $admin = User::query()->where('email', 'admin@loan.com')->first()
            ?? User::query()->orderBy('id')->first();

        if ($admin) {
            $today = now()->toDateString();
            $tomorrow = now()->addDay()->toDateString();

            WorkplanItem::create([
                'user_id' => $admin->id,
                'work_date' => $today,
                'title' => 'Call 6 accounts in PAR 15+ bucket (Westlands)',
                'is_done' => false,
                'sort_order' => 1,
            ]);
            WorkplanItem::create([
                'user_id' => $admin->id,
                'work_date' => $today,
                'title' => 'Submit end-of-day collection sheet to branch admin',
                'is_done' => true,
                'sort_order' => 2,
            ]);
            WorkplanItem::create([
                'user_id' => $admin->id,
                'work_date' => $tomorrow,
                'title' => 'Branch morning huddle — 8:30',
                'is_done' => false,
                'sort_order' => 1,
            ]);
        }
    }
}
