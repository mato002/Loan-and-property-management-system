<?php

namespace Tests\Feature\Loan;

use App\Models\AccountingChartAccount;
use App\Models\AccountingWalletSlotSetting;
use App\Models\ClientWallet;
use App\Models\ClientWalletTransaction;
use App\Models\Employee;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanPaymentAllocation;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\ClientWalletService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creation_creates_wallet_via_service(): void
    {
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-001',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'W',
            'last_name' => 'Test',
            'phone' => '0711000001',
            'client_status' => 'active',
        ]);

        $wallet = app(ClientWalletService::class)->ensureWallet($client, null);

        $this->assertDatabaseHas('client_wallets', [
            'loan_client_id' => $client->id,
            'status' => ClientWallet::STATUS_ACTIVE,
        ]);
        $this->assertSame(0.0, (float) $wallet->balance);
    }

    public function test_backfill_command_creates_missing_wallets(): void
    {
        LoanClient::query()->create([
            'client_number' => 'CL-W-002',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'A',
            'last_name' => 'B',
            'client_status' => 'active',
        ]);

        $this->artisan('loan:backfill-client-wallets')->assertSuccessful();

        $this->assertSame(1, (int) ClientWallet::query()->count());
    }

    public function test_overpayment_creates_wallet_credit_and_is_idempotent(): void
    {
        $this->seedPostingAccountsAndMappings();
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-003',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'O',
            'last_name' => 'Pay',
            'client_status' => 'active',
        ]);
        app(ClientWalletService::class)->ensureWallet($client, null);

        $loan = LoanBookLoan::query()->create([
            'loan_number' => 'LN-W-003',
            'loan_client_id' => $client->id,
            'product_name' => 'P',
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
            'reference' => 'PAY-W-003',
            'loan_book_loan_id' => $loan->id,
            'amount' => 1000,
            'currency' => 'KES',
            'channel' => 'cash',
            'status' => LoanBookPayment::STATUS_PROCESSED,
            'payment_kind' => LoanBookPayment::KIND_NORMAL,
            'transaction_at' => now(),
        ]);

        $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment);
        $payment->update(['accounting_journal_entry_id' => $entry->id]);
        app(LoanBookLoanUpdateService::class)->onPaymentProcessed($payment->fresh());
        app(ClientWalletService::class)->syncPostedPaymentWalletEffects($payment->fresh(['allocations', 'loan']));

        $over = (float) LoanPaymentAllocation::query()
            ->where('loan_book_payment_id', $payment->id)
            ->where('component', 'overpayment')
            ->sum('amount');
        $this->assertGreaterThan(0.0, $over);

        $wallet = ClientWallet::query()->where('loan_client_id', $client->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(round($over, 2), round((float) $wallet->balance, 2));

        app(ClientWalletService::class)->syncPostedPaymentWalletEffects($payment->fresh(['allocations', 'loan']));
        $wallet->refresh();
        $this->assertSame(1, (int) ClientWalletTransaction::query()
            ->where('loan_book_payment_id', $payment->id)
            ->where('source_type', ClientWalletTransaction::SOURCE_OVERPAYMENT)
            ->count());
    }

    public function test_wallet_manual_adjustment_posts_gl_for_credit_and_debit(): void
    {
        $this->seedPostingAccountsAndMappings();
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-ADJ',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Adj',
            'last_name' => 'Test',
            'client_status' => 'active',
        ]);
        app(ClientWalletService::class)->ensureWallet($client, null);

        $svc = app(ClientWalletService::class);
        $svc->creditWallet($client, 50.0, ClientWalletTransaction::SOURCE_ADJUSTMENT, [
            'reference' => 'ADJ-T-CR',
            'description' => 'Test credit adjustment',
        ]);
        $creditTx = ClientWalletTransaction::query()->where('reference', 'ADJ-T-CR')->first();
        $this->assertNotNull($creditTx);
        $this->assertNotNull($creditTx->accounting_journal_entry_id);
        $this->assertDatabaseHas('accounting_journal_entries', [
            'id' => $creditTx->accounting_journal_entry_id,
        ]);

        $svc->debitWallet($client, 20.0, ClientWalletTransaction::SOURCE_ADJUSTMENT, [
            'reference' => 'ADJ-T-DR',
            'description' => 'Test debit adjustment',
        ]);
        $debitTx = ClientWalletTransaction::query()->where('reference', 'ADJ-T-DR')->first();
        $this->assertNotNull($debitTx);
        $this->assertNotNull($debitTx->accounting_journal_entry_id);
        $this->assertNotSame($creditTx->accounting_journal_entry_id, $debitTx->accounting_journal_entry_id);

        $wallet = ClientWallet::query()->where('loan_client_id', $client->id)->first();
        $this->assertSame(30.0, (float) $wallet->balance);
    }

    public function test_wallet_to_loan_debit_cannot_exceed_balance(): void
    {
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-004',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'X',
            'last_name' => 'Y',
            'client_status' => 'active',
        ]);
        app(ClientWalletService::class)->ensureWallet($client, null);

        $this->expectException(\RuntimeException::class);
        app(ClientWalletService::class)->debitWallet($client, 100.0, ClientWalletTransaction::SOURCE_WALLET_TO_LOAN, [
            'reference' => 'T',
            'description' => 'x',
        ]);
    }

    public function test_client_profile_shows_wallet_balance_not_channel_filter(): void
    {
        [$user, $employee] = $this->createLoanUserWithEmployee('mgr-wallet@example.test', 'manager');
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-005',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Show',
            'last_name' => 'Me',
            'assigned_employee_id' => $employee->id,
            'client_status' => 'active',
        ]);
        $wallet = app(ClientWalletService::class)->ensureWallet($client, $user->id);
        $wallet->update(['balance' => 250.5]);

        $response = $this->actingAs($user)->get(route('loan.clients.show', $client));
        $response->assertOk();
        $response->assertSee('250.50');
        $response->assertDontSee('View Wallet'); // old link text removed
    }

    public function test_account_balances_page_lists_wallets_for_manager(): void
    {
        [$user] = $this->createLoanUserWithEmployee('mgr-wallets@example.test', 'manager');
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-006',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'List',
            'last_name' => 'Test',
            'client_status' => 'active',
        ]);
        $w = app(ClientWalletService::class)->ensureWallet($client, $user->id);
        $w->update(['balance' => 99]);

        $response = $this->actingAs($user)->get(route('loan.financial.account_balances'));
        $response->assertOk();
        $response->assertSee('CL-W-006');
        $response->assertSee('99.00');
    }

    public function test_wallet_statement_running_balances_order(): void
    {
        $this->seedPostingAccountsAndMappings();
        $client = LoanClient::query()->create([
            'client_number' => 'CL-W-007',
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'R',
            'last_name' => 'B',
            'client_status' => 'active',
        ]);
        app(ClientWalletService::class)->creditWallet($client, 100, ClientWalletTransaction::SOURCE_ADJUSTMENT, [
            'reference' => 'A1',
            'description' => 'Adj',
            'created_by' => null,
        ]);
        app(ClientWalletService::class)->debitWallet($client, 30, ClientWalletTransaction::SOURCE_ADJUSTMENT, [
            'reference' => 'A2',
            'description' => 'Adj2',
            'created_by' => null,
        ]);

        $rows = ClientWalletTransaction::query()
            ->where('loan_client_id', $client->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertSame(100.0, (float) $rows[0]->running_balance);
        $this->assertSame(70.0, (float) $rows[1]->running_balance);
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
            'employee_number' => 'EMP-W-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'T',
            'last_name' => (string) $user->id,
            'email' => $email,
        ]);

        return [$user, $employee];
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
            'wallet_adj' => $this->makeAccount('5999', 'Wallet Adjustment Offset', 'expense'),
        ];

        $mappings = [
            'collection_cash_account' => $accounts['cash']->id,
            'client_wallet_liability_account' => $accounts['wallet']->id,
            'loan_portfolio_performing_account' => $accounts['principal']->id,
            'interest_income_account' => $accounts['interest']->id,
            'fee_income_account' => $accounts['fees']->id,
            'penalty_income_account' => $accounts['penalty']->id,
            'adjustment_account' => $accounts['wallet_adj']->id,
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
            'overdraft_limit' => 1_000_000_000,
            'min_balance_floor' => 0,
        ]);
    }
}
