<?php

namespace Tests\Unit;

use App\Models\LoanBookLoan;
use App\Models\LoanClient;
use App\Models\LoanSystemSetting;
use App\Services\LoanBook\BorrowerClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_borrower_classification(): void
    {
        $client = $this->makeClient();
        $result = app(BorrowerClassificationService::class)->classify($client, 25000);

        $this->assertSame('new_borrower', $result['borrower_category']);
    }

    public function test_repeat_good_borrower_classification(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 0, 'principal' => 40000]);

        $result = app(BorrowerClassificationService::class)->classify($client, 50000);
        $this->assertSame('repeat_good', $result['borrower_category']);
    }

    public function test_repeat_normal_borrower_classification(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 6, 'principal' => 30000]);
        LoanSystemSetting::setValue('max_allowed_dpd_for_repeat', '7');
        LoanSystemSetting::setValue('min_repayment_success_rate', '0.4');

        $result = app(BorrowerClassificationService::class)->classify($client, 35000);
        $this->assertSame('repeat_normal', $result['borrower_category']);
    }

    public function test_repeat_risky_borrower_classification(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 20000, 'dpd' => 18]);
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 20]);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');

        $result = app(BorrowerClassificationService::class)->classify($client, 30000);
        $this->assertSame('repeat_risky', $result['borrower_category']);
    }

    public function test_blocked_borrower_classification(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 10000, 'dpd' => 0]);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '0');

        $result = app(BorrowerClassificationService::class)->classify($client, 15000);
        $this->assertSame('blocked', $result['borrower_category']);
    }

    public function test_active_loan_blocker(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_PENDING_DISBURSEMENT, 'balance' => 10000]);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '0');

        $result = app(BorrowerClassificationService::class)->classify($client, 10000);
        $this->assertContains('active_open_loan_exists', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_written_off_blocker(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_WRITTEN_OFF, 'balance' => 5000]);
        LoanSystemSetting::setValue('block_if_written_off_history', '1');
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');

        $result = app(BorrowerClassificationService::class)->classify($client, 10000);
        $this->assertContains('written_off_history_blocked', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_affordability_blocker(): void
    {
        $client = $this->makeClient(10000);
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 50000, 'term_value' => 1, 'term_unit' => 'monthly']);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');
        LoanSystemSetting::setValue('max_installment_to_income_ratio', '0.2');

        $result = app(BorrowerClassificationService::class)->classify($client, 30000);
        $this->assertContains('installment_to_income_ratio_exceeded', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_graduation_allowed(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 0, 'principal' => 40000]);
        LoanSystemSetting::setValue('graduation_increase_percentage', '30');

        $result = app(BorrowerClassificationService::class)->classify($client, 45000);
        $this->assertTrue((bool) $result['borrower_decision']['graduation_allowed']);
    }

    public function test_graduation_blocked_when_request_exceeds_limit(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 0, 'principal' => 20000]);
        LoanSystemSetting::setValue('second_loan_limit', '25000');

        $result = app(BorrowerClassificationService::class)->classify($client, 100000);
        $this->assertFalse((bool) $result['borrower_decision']['graduation_allowed']);
    }

    private function makeClient(float $monthlyIncome = 80000): LoanClient
    {
        return LoanClient::query()->create([
            'client_number' => 'CL-'.uniqid(),
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'client_status' => 'active',
            'biodata_meta' => ['monthly_income' => $monthlyIncome],
        ]);
    }

    /**
     * @param  array<string,mixed>  $override
     */
    private function makeLoan(LoanClient $client, array $override = []): LoanBookLoan
    {
        $payload = array_merge([
            'loan_number' => 'LN-'.uniqid(),
            'loan_client_id' => $client->id,
            'product_name' => 'Standard',
            'principal' => 30000,
            'principal_outstanding' => 30000,
            'balance' => 30000,
            'interest_rate' => 10,
            'term_value' => 3,
            'term_unit' => 'monthly',
            'interest_rate_period' => 'annual',
            'interest_outstanding' => 0,
            'fees_outstanding' => 0,
            'status' => LoanBookLoan::STATUS_ACTIVE,
            'dpd' => 0,
        ], $override);

        return LoanBookLoan::query()->create($payload);
    }
}
