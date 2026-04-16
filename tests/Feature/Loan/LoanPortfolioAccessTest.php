<?php

namespace Tests\Feature\Loan;

use App\Models\Employee;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPortfolioAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_sees_only_assigned_clients_on_clients_index(): void
    {
        [$officerUser, $officerEmployee] = $this->createLoanUserWithEmployee('officer1@example.test', 'officer');
        [, $otherEmployee] = $this->createLoanUserWithEmployee('officer2@example.test', 'officer');

        $ownClient = LoanClient::query()->create($this->clientPayload($officerEmployee, 'CL-OWN-001', 'Own'));
        $otherClient = LoanClient::query()->create($this->clientPayload($otherEmployee, 'CL-OTH-001', 'Other'));

        $response = $this->actingAs($officerUser)->get(route('loan.clients.index'));

        $response->assertOk();
        $response->assertSee($ownClient->full_name);
        $response->assertDontSee($otherClient->full_name);
    }

    public function test_officer_cannot_open_unassigned_client_details(): void
    {
        [$officerUser] = $this->createLoanUserWithEmployee('officer1@example.test', 'officer');
        [, $otherEmployee] = $this->createLoanUserWithEmployee('officer2@example.test', 'officer');

        $otherClient = LoanClient::query()->create($this->clientPayload($otherEmployee, 'CL-OTH-002', 'Other'));

        $response = $this->actingAs($officerUser)->get(route('loan.clients.show', $otherClient));

        $response->assertForbidden();
    }

    public function test_officer_sees_only_assigned_payments_in_unposted_queue(): void
    {
        [$officerUser, $officerEmployee] = $this->createLoanUserWithEmployee('officer1@example.test', 'officer');
        [, $otherEmployee] = $this->createLoanUserWithEmployee('officer2@example.test', 'officer');

        $ownLoan = $this->createLoanForEmployee($officerEmployee, 'LN-OWN-001');
        $otherLoan = $this->createLoanForEmployee($otherEmployee, 'LN-OTH-001');

        $ownPayment = LoanBookPayment::query()->create([
            'reference' => 'PAY-OWN-001',
            'loan_book_loan_id' => $ownLoan->id,
            'amount' => 1000,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
            'created_by' => $officerUser->id,
        ]);
        $otherPayment = LoanBookPayment::query()->create([
            'reference' => 'PAY-OTH-001',
            'loan_book_loan_id' => $otherLoan->id,
            'amount' => 2000,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
            'created_by' => $officerUser->id,
        ]);

        $response = $this->actingAs($officerUser)->get(route('loan.payments.unposted'));

        $response->assertOk();
        $response->assertSee($ownPayment->reference);
        $response->assertDontSee($otherPayment->reference);
    }

    public function test_manager_can_see_all_clients(): void
    {
        [$managerUser, $managerEmployee] = $this->createLoanUserWithEmployee('manager@example.test', 'manager');
        [, $otherEmployee] = $this->createLoanUserWithEmployee('officer3@example.test', 'officer');

        $ownClient = LoanClient::query()->create($this->clientPayload($managerEmployee, 'CL-MGR-001', 'Manager'));
        $otherClient = LoanClient::query()->create($this->clientPayload($otherEmployee, 'CL-OTH-003', 'Other'));

        $response = $this->actingAs($managerUser)->get(route('loan.clients.index'));

        $response->assertOk();
        $response->assertSee($ownClient->full_name);
        $response->assertSee($otherClient->full_name);
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function createLoanUserWithEmployee(string $email, string $loanRole): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'loan_role' => $loanRole,
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => ucfirst($loanRole),
            'last_name' => (string) $user->id,
            'email' => $email,
        ]);

        return [$user, $employee];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Employee $employee, string $clientNumber, string $firstName): array
    {
        return [
            'client_number' => $clientNumber,
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => $firstName,
            'last_name' => 'Client',
            'phone' => '0700000000',
            'email' => strtolower($clientNumber).'@example.test',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ];
    }

    private function createLoanForEmployee(Employee $employee, string $loanNumber): LoanBookLoan
    {
        $client = LoanClient::query()->create($this->clientPayload(
            $employee,
            'CL-'.$loanNumber,
            'Client'.$loanNumber
        ));

        return LoanBookLoan::query()->create([
            'loan_number' => $loanNumber,
            'loan_client_id' => $client->id,
            'product_name' => 'Test Product',
            'principal' => 10000,
            'balance' => 10000,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);
    }
}
