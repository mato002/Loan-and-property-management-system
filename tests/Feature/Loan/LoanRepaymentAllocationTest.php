<?php

namespace Tests\Feature\Loan;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalLine;
use App\Models\AccountingWalletSlotSetting;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanPaymentAllocation;
use App\Models\LoanSystemSetting;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBook\LoanRepaymentAllocationService;
use App\Services\LoanBookGlPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRepaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_service_uses_default_order_and_overpayment_residual(): void
    {
        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-ALLOC-001',
            'principal' => 1000,
            'balance' => 1000,
            'principal_outstanding' => 500,
            'interest_outstanding' => 200,
            'fees_outstanding' => 100,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-ALLOC-001',
            'loan_book_loan_id' => $loan->id,
            'amount' => 1200,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        $result = app(LoanRepaymentAllocationService::class)->allocate($payment, $loan, 1000.0);

        $this->assertSame(
            ['principal', 'interest', 'fees', 'penalty', 'overpayment'],
            $result['order']
        );
        $this->assertSame(500.0, $result['allocations']['principal']);
        $this->assertSame(200.0, $result['allocations']['interest']);
        $this->assertSame(100.0, $result['allocations']['fees']);
        $this->assertSame(0.0, $result['allocations']['penalty']);
        $this->assertSame(200.0, $result['allocations']['overpayment']);
    }

    public function test_changed_setting_order_is_applied_and_penalty_remains_included(): void
    {
        LoanSystemSetting::setValue(
            'loan_repayment_allocation_order',
            'interest,principal,fees,overpayment',
            'Loan repayment allocation order',
            'preferences'
        );

        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-ALLOC-002',
            'principal' => 1000,
            'balance' => 1000,
            'principal_outstanding' => 500,
            'interest_outstanding' => 200,
            'fees_outstanding' => 100,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-ALLOC-002',
            'loan_book_loan_id' => $loan->id,
            'amount' => 150,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        $result = app(LoanRepaymentAllocationService::class)->allocate($payment, $loan, 150.0);

        $this->assertSame(
            ['interest', 'principal', 'fees', 'penalty', 'overpayment'],
            $result['order']
        );
        $this->assertSame(150.0, $result['allocations']['interest']);
        $this->assertSame(0.0, $result['allocations']['principal']);
    }

    public function test_gl_lines_allocation_records_and_loan_balances_stay_consistent(): void
    {
        $accounts = $this->seedPostingAccountsAndMappings();
        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-ALLOC-003',
            'principal' => 1200,
            'balance' => 1200,
            'principal_outstanding' => 800,
            'interest_outstanding' => 200,
            'fees_outstanding' => 100,
            'interest_rate' => 10,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ]);

        $payment = LoanBookPayment::query()->create([
            'reference' => 'PAY-ALLOC-003',
            'loan_book_loan_id' => $loan->id,
            'amount' => 1000,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment);
        app(LoanBookLoanUpdateService::class)->onPaymentProcessed($payment->fresh());

        $allocationRows = LoanPaymentAllocation::query()
            ->where('loan_book_payment_id', $payment->id)
            ->orderBy('allocation_order')
            ->get();
        $this->assertCount(4, $allocationRows);
        $this->assertSame(['principal', 'interest', 'fees', 'overpayment'], $allocationRows->pluck('component')->values()->all());

        $componentCreditByAccount = AccountingJournalLine::query()
            ->where('accounting_journal_entry_id', $entry->id)
            ->where('credit', '>', 0)
            ->get()
            ->groupBy('accounting_chart_account_id')
            ->map(fn ($rows) => round((float) $rows->sum('credit'), 2));

        $this->assertSame(800.0, (float) $componentCreditByAccount->get($accounts['principal']->id));
        $this->assertSame(200.0, (float) $componentCreditByAccount->get($accounts['interest']->id));
        $this->assertSame(100.0, (float) $componentCreditByAccount->get($accounts['fees']->id, 0.0));
        $this->assertSame(1200.0, (float) $componentCreditByAccount->get($accounts['wallet']->id));

        $loan->refresh();
        $this->assertSame(0.0, (float) $loan->principal_outstanding);
        $this->assertSame(0.0, (float) $loan->interest_outstanding);
        $this->assertSame(0.0, (float) $loan->fees_outstanding);
        $this->assertSame(0.0, (float) $loan->balance);
    }

    /**
     * @return array<string, AccountingChartAccount>
     */
    private function seedPostingAccountsAndMappings(): array
    {
        $accounts = [
            'cash' => $this->makeAccount('1001', 'Collection Cash', 'asset'),
            'wallet' => $this->makeAccount('2001', 'Client Wallet Liability', 'liability'),
            'principal' => $this->makeAccount('1200', 'Loan Portfolio Performing', 'asset'),
            'interest' => $this->makeAccount('4002', 'Interest Income', 'income'),
            'fees' => $this->makeAccount('4007', 'Fee Income', 'income'),
            'penalty' => $this->makeAccount('4003', 'Penalty Income', 'income'),
        ];

        $mappings = [
            'collection_cash_account' => $accounts['cash']->id,
            'client_wallet_liability_account' => $accounts['wallet']->id,
            'loan_portfolio_performing_account' => $accounts['principal']->id,
            'interest_income_account' => $accounts['interest']->id,
            'fee_income_account' => $accounts['fees']->id,
            'penalty_income_account' => $accounts['penalty']->id,
        ];

        foreach ($mappings as $slotKey => $accountId) {
            AccountingWalletSlotSetting::query()->updateOrCreate(
                ['slot_key' => $slotKey],
                [
                    'accounting_chart_account_id' => $accountId,
                    'approval_status' => 'active',
                    'history_json' => [],
                ]
            );
        }

        return $accounts;
    }

    private function makeAccount(string $code, string $name, string $type): AccountingChartAccount
    {
        return AccountingChartAccount::query()->create([
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'account_class' => AccountingChartAccount::CLASS_DETAIL,
            'is_active' => true,
            'is_cash_account' => $type === 'asset' && str_contains(strtolower($name), 'cash'),
            'current_balance' => 0,
            'allow_overdraft' => true,
            'overdraft_limit' => 0,
            'min_balance_floor' => 0,
        ]);
    }
}
