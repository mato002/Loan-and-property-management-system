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

    public function test_excluded_loan_skipped_for_active_open_loan_block(): void
    {
        $client = $this->makeClient();
        $pending = $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_PENDING_DISBURSEMENT, 'balance' => 10000]);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '0');

        $result = app(BorrowerClassificationService::class)->classify($client, 10000, (int) $pending->id);
        $this->assertNotContains('active_open_loan_exists', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_excluded_loan_second_open_still_blocks(): void
    {
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 5000]);
        $pending = $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_PENDING_DISBURSEMENT, 'balance' => 10000]);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '0');

        $result = app(BorrowerClassificationService::class)->classify($client, 10000, (int) $pending->id);
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

    public function test_total_indebtedness_to_income_blocker(): void
    {
        $client = $this->makeClient(10000);
        LoanSystemSetting::setValue('max_total_indebtedness_to_income_ratio', '2');
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 12000, 'dpd' => 0]);

        $result = app(BorrowerClassificationService::class)->classify($client, 10000);
        $this->assertContains('total_indebtedness_to_income_exceeded', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_guarantor_exposure_cap_tightens_indebtedness_rule(): void
    {
        LoanSystemSetting::setValue('max_total_indebtedness_to_income_ratio', '10');
        LoanSystemSetting::setValue('max_combined_guarantor_exposure_ratio', '1.5');
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');
        $client = $this->makeClient(10000, ['guarantor_1_full_name' => 'Jane Doe']);
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 5000, 'dpd' => 0]);

        $result = app(BorrowerClassificationService::class)->classify($client, 12000);
        $this->assertContains('total_indebtedness_to_income_exceeded', $result['borrower_decision']['blocking_reasons']);
    }

    public function test_affordability_engine_disabled_skips_installment_block_and_history_flags(): void
    {
        LoanSystemSetting::setValue('affordability_engine_enabled', '0');
        $client = $this->makeClient(10000);
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_ACTIVE, 'balance' => 50000, 'term_value' => 1, 'term_unit' => 'monthly']);
        LoanSystemSetting::setValue('allow_top_up_if_active_loan', '1');
        LoanSystemSetting::setValue('max_installment_to_income_ratio', '0.2');

        $result = app(BorrowerClassificationService::class)->classify($client, 30000);
        $this->assertNotContains('installment_to_income_ratio_exceeded', $result['borrower_decision']['blocking_reasons']);
        $this->assertFalse($result['borrower_decision']['affordability_engine_enabled']);
    }

    public function test_affordability_engine_disabled_neutralizes_repeat_risky_from_closed_loan_history(): void
    {
        LoanSystemSetting::setValue('affordability_engine_enabled', '0');
        $client = $this->makeClient();
        $this->makeLoan($client, ['status' => LoanBookLoan::STATUS_CLOSED, 'balance' => 0, 'dpd' => 20, 'principal' => 30000]);

        $result = app(BorrowerClassificationService::class)->classify($client, 35000);
        $this->assertSame('repeat_normal', $result['borrower_category']);
        $this->assertNotContains('historical_dpd_above_threshold', $result['borrower_decision']['risk_flags']);
        $this->assertNotContains('repayment_success_rate_low', $result['borrower_decision']['risk_flags']);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeClient(float $monthlyIncome = 80000, array $overrides = []): LoanClient
    {
        return LoanClient::query()->create(array_merge([
            'client_number' => 'CL-'.uniqid(),
            'kind' => LoanClient::KIND_CLIENT,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'client_status' => 'active',
            'biodata_meta' => ['monthly_income' => $monthlyIncome],
        ], $overrides));
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
