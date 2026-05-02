<?php

namespace Tests\Feature\Loan;

use App\Models\AccountingJournalEntry;
use App\Models\Employee;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanPaymentAllocation;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessedPaymentsAllocationDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_page_shows_allocation_components_from_table(): void
    {
        $user = $this->createLoanAdminUser();
        [$loan, $payment] = $this->createProcessedPaymentWithAllocations([
            ['component' => 'principal', 'amount' => 3000.0, 'allocation_order' => 1],
            ['component' => 'interest', 'amount' => 500.0, 'allocation_order' => 2],
            ['component' => 'fees', 'amount' => 100.0, 'allocation_order' => 3],
            ['component' => 'penalty', 'amount' => 50.0, 'allocation_order' => 4],
            ['component' => 'overpayment', 'amount' => 200.0, 'allocation_order' => 5],
        ]);

        $response = $this->actingAs($user)->get(route('loan.payments.processed'));

        $response->assertOk();
        $response->assertSee('Principal - 3,000.00', false);
        $response->assertSee('Interest - 500.00', false);
        $response->assertSee('Fees - 100.00', false);
        $response->assertSee('Penalty - 50.00', false);
        $response->assertSee('Overpayment - 200.00', false);
        $response->assertDontSee('Charges -', false);
    }

    public function test_processed_page_does_not_default_full_amount_as_principal_when_allocations_missing(): void
    {
        $user = $this->createLoanAdminUser();
        [$loan, $payment] = $this->createProcessedPaymentWithoutAllocations(3627.00);

        $response = $this->actingAs($user)->get(route('loan.payments.processed'));

        $response->assertOk();
        $response->assertSee('Allocation pending', false);
        $response->assertDontSee('Principal - 3,627.00', false);
    }

    public function test_processed_page_shows_verify_journal_when_journal_exists_but_no_allocations(): void
    {
        $user = $this->createLoanAdminUser();
        [$loan, $payment] = $this->createProcessedPaymentWithoutAllocations(500.00, withJournal: true);

        $response = $this->actingAs($user)->get(route('loan.payments.processed'));

        $response->assertOk();
        $response->assertSee('Allocation unavailable — verify journal', false);
    }

    public function test_print_view_matches_processed_breakdown(): void
    {
        $user = $this->createLoanAdminUser();
        [$loan, $payment] = $this->createProcessedPaymentWithAllocations([
            ['component' => 'principal', 'amount' => 100.0, 'allocation_order' => 1],
            ['component' => 'interest', 'amount' => 25.5, 'allocation_order' => 2],
        ]);

        $response = $this->actingAs($user)->get(route('loan.payments.processed.print'));

        $response->assertOk();
        $response->assertSee('Principal - 100.00', false);
        $response->assertSee('Interest - 25.50', false);
        $response->assertDontSee('Ledger line', false);
    }

    public function test_allocation_mismatch_badge_when_sum_differs_from_payment_amount(): void
    {
        $user = $this->createLoanAdminUser();
        [$loan, $payment] = $this->createProcessedPaymentWithAllocations([
            ['component' => 'principal', 'amount' => 100.0, 'allocation_order' => 1],
        ], paymentAmount: 500.0);

        $response = $this->actingAs($user)->get(route('loan.payments.processed'));

        $response->assertOk();
        $response->assertSee('Allocation mismatch', false);
    }

    private function createLoanAdminUser(): User
    {
        // Loan "admin" role has cross-portfolio visibility (see ScopesLoanPortfolioAccess).
        $user = User::factory()->create([
            'email' => 'loan-admin-processed-'.uniqid('', true).'@example.test',
            'loan_role' => 'admin',
        ]);

        UserModuleAccess::query()->create([
            'user_id' => $user->id,
            'module' => 'loan',
            'status' => UserModuleAccess::STATUS_APPROVED,
        ]);

        return $user;
    }

    /**
     * @param  list<array{component:string,amount:float,allocation_order:int}>  $allocationRows
     * @return array{0: LoanBookLoan, 1: LoanBookPayment}
     */
    private function createProcessedPaymentWithAllocations(array $allocationRows, float $paymentAmount = 3850.0): array
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PRC-'.uniqid(),
            'first_name' => 'Mgr',
            'last_name' => 'Test',
            'email' => 'emp-processed-'.uniqid('', true).'@example.test',
        ]);

        $client = LoanClient::query()->create([
            'client_number' => 'CL-PRC-'.uniqid(),
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'phone' => '0711111111',
            'email' => 'cl-p@example.test',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ]);

        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-PRC-'.uniqid(),
            'loan_client_id' => $client->id,
            'product_name' => 'Test Product',
            'principal' => 10000,
            'balance' => 10000,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-PRC-'.uniqid(),
            'loan_book_loan_id' => $loan->id,
            'amount' => $paymentAmount,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        foreach ($allocationRows as $row) {
            LoanPaymentAllocation::query()->create([
                'loan_book_payment_id' => $payment->id,
                'loan_book_loan_id' => $loan->id,
                'component' => $row['component'],
                'amount' => $row['amount'],
                'allocation_order' => $row['allocation_order'],
            ]);
        }

        return [$loan, $payment];
    }

    /**
     * @return array{0: LoanBookLoan, 1: LoanBookPayment}
     */
    private function createProcessedPaymentWithoutAllocations(float $amount, bool $withJournal = false): array
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PRC2-'.uniqid(),
            'first_name' => 'Mgr',
            'last_name' => 'Test',
            'email' => 'emp-processed2-'.uniqid('', true).'@example.test',
        ]);

        $client = LoanClient::query()->create([
            'client_number' => 'CL-PRC2-'.uniqid(),
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'phone' => '0722222222',
            'email' => 'cl-p2@example.test',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ]);

        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-PRC2-'.uniqid(),
            'loan_client_id' => $client->id,
            'product_name' => 'Test Product',
            'principal' => 10000,
            'balance' => 10000,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);

        $journalId = null;
        if ($withJournal) {
            $journalId = AccountingJournalEntry::query()->create([
                'entry_date' => now()->toDateString(),
                'reference' => 'JE-PRC-'.uniqid(),
                'description' => 'Test journal link',
                'created_by' => null,
            ])->id;
        }

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-PRC2-'.uniqid(),
            'loan_book_loan_id' => $loan->id,
            'amount' => $amount,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
            'accounting_journal_entry_id' => $journalId,
        ]);

        return [$loan, $payment];
    }
}
