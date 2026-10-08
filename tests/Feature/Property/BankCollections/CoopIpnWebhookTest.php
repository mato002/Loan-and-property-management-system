<?php

namespace Tests\Feature\Property\BankCollections;

use App\Services\Property\PropertyBankTransactionIngestService;
use App\Support\Property\BankIntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoopIpnWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BankIntegrationConfig::forgetCachedToken('coop');
        config(['property_banks.providers.coop.ipn_allowed_ips' => []]);
    }

    public function test_get_reports_the_endpoint_ready_for_the_bank(): void
    {
        $response = $this->getJson(route('webhooks.property.payments.coop_ipn'));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('service', 'Co-operative Bank IPN');
    }

    public function test_credit_notification_is_acknowledged_and_ingested(): void
    {
        $this->mock(PropertyBankTransactionIngestService::class, function ($mock): void {
            $mock->shouldReceive('ingest')
                ->once()
                ->withArgs(function (string $provider, array $payload): bool {
                    return $provider === 'coop'
                        && ($payload['external_transaction_reference'] ?? '') === 'CB0545802_13072026_2'
                        && ($payload['tenant_account_number'] ?? '') === '7719'
                        && ($payload['payer_phone'] ?? '') === '254722417771'
                        && (float) ($payload['amount'] ?? 0) === 900.0;
                })
                ->andReturn([
                    'ok' => true,
                    'duplicate' => false,
                    'matched' => true,
                    'message' => 'Matched and settled.',
                ]);
        });

        $response = $this->postJson(route('webhooks.property.payments.coop_ipn'), [
            'AcctNo' => 'ACCOUNT_NUMBER',
            'Amount' => '900.0',
            'Currency' => 'KES',
            'EventType' => 'CREDIT',
            'Narration' => 'UGDFHAVTTZ~7719#Isaac~254722417771~MPESAC2B_400222~IBRAHIM NDWIGA',
            'PaymentRef' => '13072026_158467093',
            'TransactionDate' => '2026-07-13T14:22:04',
            'TransactionId' => 'CB0545802_13072026_2',
        ]);

        $response->assertOk();
        $response->assertJsonPath('MessageCode', '200');
        $response->assertJsonPath('Message', 'Successfully received data');
    }

    public function test_debit_notification_is_acknowledged_without_ingest(): void
    {
        $this->mock(PropertyBankTransactionIngestService::class, function ($mock): void {
            $mock->shouldReceive('ingest')->never();
        });

        $response = $this->postJson(route('webhooks.property.payments.coop_ipn'), [
            'MessageReference' => 'debit-1',
            'TransactionId' => 'DEB-1',
            'Amount' => '500.00',
            'EventType' => 'DEBIT',
            'AcctNo' => '01123456789000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('MessageCode', '200');
        $response->assertJsonPath('Message', 'Successfully received data');
    }
}
