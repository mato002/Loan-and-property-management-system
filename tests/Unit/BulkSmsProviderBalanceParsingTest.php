<?php

namespace Tests\Unit;

use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BulkSmsProviderBalanceParsingTest extends TestCase
{
    #[Test]
    public function it_parses_balance_units_and_price_from_provider_payload(): void
    {
        $parsed = $this->parse([
            'balance' => 564.19,
            'units' => 940.32,
            'price_per_unit' => 0.6,
        ]);

        $this->assertSame(564.19, $parsed['balance']);
        $this->assertSame(940.32, $parsed['units']);
        $this->assertSame(0.6, $parsed['price_per_unit']);
    }

    #[Test]
    public function it_derives_price_per_unit_from_balance_and_units_when_missing(): void
    {
        $parsed = $this->parse([
            'data' => [
                'balance' => 564.19,
                'units' => 940.32,
            ],
        ]);

        $this->assertSame(564.19, $parsed['balance']);
        $this->assertSame(940.32, $parsed['units']);
        $this->assertEqualsWithDelta(0.6, $parsed['price_per_unit'], 0.01);
    }

    #[Test]
    public function it_reads_sender_nested_tariff_fields(): void
    {
        $parsed = $this->parse([
            'balance' => 100,
            'sender' => [
                'units' => 166.67,
                'price_per_unit' => 0.6,
            ],
        ]);

        $this->assertSame(166.67, $parsed['units']);
        $this->assertSame(0.6, $parsed['price_per_unit']);
    }

    #[Test]
    public function it_keeps_debited_balance_when_provider_api_has_not_caught_up(): void
    {
        config(['bulksms.provider.client_id' => 'test-client']);

        $service = app(BulkSmsService::class);
        $service->rememberProviderBalance(525.79, 'api', 876.0, 0.6, 525.79, 0);
        $service->debitCachedProviderBalance(1.2);

        $display = $this->applyApiResponse([
            'balance' => 525.79,
            'units' => 876.0,
            'price_per_unit' => 0.6,
        ]);

        $this->assertEqualsWithDelta(524.59, $display['balance'], 0.01);
        $this->assertEqualsWithDelta(1.2, (float) Cache::get('bulksms:provider_balance:test-client')['pending_debit'], 0.01);
    }

    #[Test]
    public function it_clears_pending_debit_when_provider_balance_drops(): void
    {
        config(['bulksms.provider.client_id' => 'test-client']);

        $service = app(BulkSmsService::class);
        $service->rememberProviderBalance(525.79, 'api', 876.0, 0.6, 525.79, 0);
        $service->debitCachedProviderBalance(1.2);

        $display = $this->applyApiResponse([
            'balance' => 524.59,
            'units' => 874.0,
            'price_per_unit' => 0.6,
        ]);

        $this->assertEqualsWithDelta(524.59, $display['balance'], 0.01);
        $entry = Cache::get('bulksms:provider_balance:test-client');
        $this->assertSame(0.0, (float) ($entry['pending_debit'] ?? -1));
    }

    #[Test]
    public function it_prefers_provider_tariff_over_stale_env_fallback(): void
    {
        config(['bulksms.cost_per_sms' => 0.5]);
        config(['bulksms.provider.client_id' => 'test-client']);

        $service = app(BulkSmsService::class);
        $service->rememberProviderBalance(564.19, 'test', 940.32, null);

        $this->assertEqualsWithDelta(0.6, $service->costPerSms(), 0.01);
        $this->assertEqualsWithDelta(0.6, $service->resolveCostPerUnit(null, 564.19, 940.32), 0.01);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{balance: ?float, units: ?float, price_per_unit: ?float}
     */
    private function parse(array $json): array
    {
        $service = app(BulkSmsService::class);
        $method = new ReflectionMethod(BulkSmsService::class, 'parseProviderBalanceResponse');
        $method->setAccessible(true);

        return $method->invoke($service, $json);
    }

    /**
     * @param  array{balance: float, units?: float, price_per_unit?: float}  $parsed
     * @return array{balance: float, units: ?float, price_per_unit: ?float}
     */
    private function applyApiResponse(array $parsed): array
    {
        $service = app(BulkSmsService::class);
        $method = new ReflectionMethod(BulkSmsService::class, 'applyProviderBalanceApiResponse');
        $method->setAccessible(true);

        return $method->invoke($service, $parsed);
    }
}
