<?php

namespace Tests\Unit;

use App\Models\AccountingChartAccount;
use App\Services\AccountingChartCodeGeneratorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class AccountingChartCodeGeneratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('accounting_chart_accounts');
        Schema::create('accounting_chart_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('account_type', 24);
            $table->string('account_class', 24)->nullable();
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    #[DataProvider('typeRangeProvider')]
    public function test_it_starts_codes_from_expected_range_per_account_type(string $type, int $startCode): void
    {
        $service = app(AccountingChartCodeGeneratorService::class);

        $nextCode = $service->preview($type, AccountingChartAccount::CLASS_DETAIL, null);

        $this->assertSame((string) $startCode, $nextCode);
    }

    #[DataProvider('typeRangeProvider')]
    public function test_it_skips_existing_codes_within_type_range(string $type, int $startCode): void
    {
        DB::table('accounting_chart_accounts')->insert([
            'code' => (string) $startCode,
            'name' => strtoupper($type).' test account',
            'account_type' => $type,
            'account_class' => AccountingChartAccount::CLASS_DETAIL,
            'is_cash_account' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(AccountingChartCodeGeneratorService::class);
        $nextCode = $service->preview($type, AccountingChartAccount::CLASS_DETAIL, null);

        $this->assertSame((string) ($startCode + 1), $nextCode);
    }

    /**
     * @return array<string, array{0:string,1:int}>
     */
    public static function typeRangeProvider(): array
    {
        return [
            'asset' => [AccountingChartAccount::TYPE_ASSET, 1000],
            'liability' => [AccountingChartAccount::TYPE_LIABILITY, 2000],
            'equity' => [AccountingChartAccount::TYPE_EQUITY, 3000],
            'income' => [AccountingChartAccount::TYPE_INCOME, 4000],
            'expense' => [AccountingChartAccount::TYPE_EXPENSE, 5000],
        ];
    }
}
