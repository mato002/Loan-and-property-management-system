<?php

namespace Tests\Unit\Property;

use App\Services\Property\FinanceIntegrityService;
use App\Services\Property\FinancialReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_severity_for_drift_thresholds(): void
    {
        $recon = app(FinancialReconciliationService::class);

        $this->assertSame(FinancialReconciliationService::SEVERITY_INFO, $recon->severityForDrift(50));
        $this->assertSame(FinancialReconciliationService::SEVERITY_WARNING, $recon->severityForDrift(150));
        $this->assertSame(FinancialReconciliationService::SEVERITY_CRITICAL, $recon->severityForDrift(1500));
    }

    public function test_all_scope_dashboard_returns_expected_categories(): void
    {
        $report = app(FinanceIntegrityService::class)->dashboard(null, 10);

        $this->assertTrue($report['ready'] ?? false);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('categories', $report);
        $this->assertArrayHasKey(FinanceIntegrityService::CATEGORY_ALLOCATION_DRIFT, $report['categories']);
        $this->assertArrayHasKey(FinanceIntegrityService::CATEGORY_SUSPENSE_MISMATCH, $report['categories']);
        $this->assertArrayHasKey(FinanceIntegrityService::CATEGORY_GL_AR_MISMATCH, $report['categories']);
        $this->assertArrayHasKey(FinanceIntegrityService::CATEGORY_ORPHAN_ALLOCATIONS, $report['categories']);

        foreach ($report['categories'] as $category) {
            $this->assertArrayHasKey('repair_recommendation', $category);
            $this->assertArrayHasKey('summary', $category);
            $this->assertArrayHasKey('rows', $category);
        }
    }

    public function test_hourly_scope_includes_allocation_and_suspense_only(): void
    {
        $report = app(FinanceIntegrityService::class)->scan(FinanceIntegrityService::SCOPE_HOURLY, null, 10);

        $keys = array_keys($report['categories'] ?? []);
        $this->assertContains(FinanceIntegrityService::CATEGORY_ALLOCATION_DRIFT, $keys);
        $this->assertContains(FinanceIntegrityService::CATEGORY_SUSPENSE_MISMATCH, $keys);
        $this->assertNotContains(FinanceIntegrityService::CATEGORY_ORPHAN_ALLOCATIONS, $keys);
    }
}
