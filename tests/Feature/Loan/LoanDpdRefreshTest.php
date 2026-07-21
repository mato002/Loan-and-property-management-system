<?php

namespace Tests\Feature\Loan;

use App\Models\Employee;
use App\Models\LoanBookLoan;
use App\Models\LoanClient;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\LoanBook\LoanDpdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanDpdRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_loan_gets_dpd_and_appears_in_arrears_register(): void
    {
        Carbon::setTestNow('2026-07-04 10:00:00');

        $user = User::factory()->create([
            'email' => 'loan-admin@example.test',
            'loan_role' => 'admin',
        ]);
        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-0001',
            'first_name' => 'Loan',
            'last_name' => 'Admin',
            'email' => 'loan-admin@example.test',
        ]);
        $client = LoanClient::query()->create([
            'client_number' => 'CL-DPD-001',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Grace',
            'last_name' => 'Client',
            'phone' => '254711000001',
            'email' => 'grace@example.test',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ]);

        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-DPD-001',
            'loan_client_id' => $client->id,
            'product_name' => 'loan 1-12k',
            'principal' => 10000,
            'principal_outstanding' => 10000,
            'interest_outstanding' => 2000,
            'fees_outstanding' => 0,
            'balance' => 12000,
            'interest_rate' => 20,
            'term_value' => 1,
            'term_unit' => 'monthly',
            'interest_rate_period' => 'monthly',
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
            'disbursed_at' => '2026-04-25',
            'maturity_date' => '2026-05-25',
        ]);

        $this->assertSame(0, (int) $loan->dpd);

        $updated = app(LoanDpdService::class)->refreshLoan($loan->fresh());
        $loan->refresh();

        $this->assertTrue($updated);
        $this->assertGreaterThanOrEqual(39, (int) $loan->dpd);

        $response = $this->actingAs($user)->get(route('loan.book.loan_arrears'));

        $response->assertOk();
        $response->assertSee('Grace Client');
        $response->assertSee('1 loan(s)');
        $response->assertDontSee('No arrears in the register');

        Carbon::setTestNow();
    }
}
