<?php

namespace Tests\Feature\Loan;

use App\Models\Employee;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanPaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillLoanPaymentAllocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_missing_allocations_for_processed_payments(): void
    {
        $loan = $this->makeLoan();

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-BF-001',
            'loan_book_loan_id' => $loan->id,
            'amount' => 100,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        $this->assertSame(0, LoanPaymentAllocation::query()->where('loan_book_payment_id', $payment->id)->count());

        $exit = Artisan::call('loan:backfill-payment-allocations', []);
        $this->assertSame(0, $exit);

        $rows = LoanPaymentAllocation::query()->where('loan_book_payment_id', $payment->id)->get();
        $this->assertGreaterThan(0, $rows->count());

        $exitSecond = Artisan::call('loan:backfill-payment-allocations', []);
        $this->assertSame(0, $exitSecond);
        $this->assertSame($rows->count(), LoanPaymentAllocation::query()->where('loan_book_payment_id', $payment->id)->count());
    }

    public function test_dry_run_does_not_write_rows(): void
    {
        $loan = $this->makeLoan();

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-BF-002',
            'loan_book_loan_id' => $loan->id,
            'amount' => 100,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        Artisan::call('loan:backfill-payment-allocations', ['--dry-run' => true]);

        $this->assertSame(0, LoanPaymentAllocation::query()->where('loan_book_payment_id', $payment->id)->count());
    }

    public function test_skips_payments_that_already_have_allocations(): void
    {
        $loan = $this->makeLoan();

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-BF-003',
            'loan_book_loan_id' => $loan->id,
            'amount' => 100,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        LoanPaymentAllocation::query()->create([
            'loan_book_payment_id' => $payment->id,
            'loan_book_loan_id' => $loan->id,
            'component' => 'principal',
            'amount' => 100,
            'allocation_order' => 1,
        ]);

        Artisan::call('loan:backfill-payment-allocations', []);

        $this->assertSame(1, LoanPaymentAllocation::query()->where('loan_book_payment_id', $payment->id)->count());
    }

    private function makeLoan(): LoanBookLoan
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-BF-'.uniqid(),
            'first_name' => 'Mgr',
            'last_name' => 'Test',
            'email' => 'emp-bf-'.uniqid('', true).'@example.test',
        ]);

        $client = LoanClient::query()->create([
            'client_number' => 'CL-BF-'.uniqid(),
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'phone' => '0711111111',
            'email' => 'cl-bf@example.test',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ]);

        return LoanBookLoan::query()->create([
            'loan_number' => 'LN-BF-'.uniqid(),
            'loan_client_id' => $client->id,
            'product_name' => 'Test Product',
            'principal' => 1000,
            'balance' => 500,
            'principal_outstanding' => 400,
            'interest_outstanding' => 50,
            'fees_outstanding' => 10,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);
    }
}
