<?php

namespace Tests\Feature\Loan;

use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for Loan Book HTTP routes (applications, loans, operations) with an approved loan user.
 */
class LoanBookSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_applications_index_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.applications.index'))
            ->assertOk();
    }

    public function test_applications_create_form_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.applications.create'))
            ->assertOk();
    }

    public function test_loans_index_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.loans.index'))
            ->assertOk();
    }

    public function test_loan_arrears_page_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.loan_arrears'))
            ->assertOk();
    }

    public function test_checkoff_loans_page_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.checkoff_loans'))
            ->assertOk();
    }

    public function test_app_loans_report_returns_ok(): void
    {
        $user = $this->makeApprovedLoanUser();

        $this->actingAs($user)
            ->get(route('loan.book.app_loans_report'))
            ->assertOk();
    }

    private function makeApprovedLoanUser(): User
    {
        $user = User::factory()->create([
            'loan_role' => 'manager',
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        return $user;
    }
}
