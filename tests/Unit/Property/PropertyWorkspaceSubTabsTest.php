<?php

namespace Tests\Unit\Property;

use App\Support\Property\PropertyWorkspaceTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PropertyWorkspaceSubTabsTest extends TestCase
{
    public function test_utilities_routes_resolve_utilities_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.revenue.utilities.reconciliation');

        $this->assertNotNull($group);
        $this->assertSame('Utilities', $group['label']);
        $this->assertSame('Billing', $group['tabs'][0]['label']);
        $this->assertSame('Reconciliation', $group['tabs'][1]['label']);
    }

    public function test_accounting_gl_routes_resolve_gl_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.accounting.gl.chart_accounts');

        $this->assertNotNull($group);
        $this->assertSame('GL', $group['label']);
        $this->assertContains('Chart of accounts', array_column($group['tabs'], 'label'));
    }

    public function test_reports_tenant_routes_resolve_tenant_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.reports.tenant.deposits');

        $this->assertNotNull($group);
        $this->assertSame('Tenant', $group['label']);
        $this->assertContains('Deposits', array_column($group['tabs'], 'label'));
    }

    public function test_rent_roll_resolves_rent_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.revenue.rent_roll');

        $this->assertNotNull($group);
        $this->assertSame('Rent', $group['label']);
        $this->assertContains('Uninvoiced leases', array_column($group['tabs'], 'label'));
    }

    public function test_uninvoiced_leases_resolves_rent_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.revenue.uninvoiced_leases');

        $this->assertNotNull($group);
        $this->assertSame('Rent', $group['label']);
    }

    public function test_penalties_resolves_billing_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.revenue.penalties');

        $this->assertNotNull($group);
        $this->assertSame('Billing', $group['label']);
    }

    public function test_equity_unmatched_resolves_bank_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.equity.unmatched');

        $this->assertNotNull($group);
        $this->assertSame('Bank', $group['label']);
    }

    public function test_tenant_credits_resolves_cash_sub_tab_group(): void
    {
        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.revenue.tenant_credits');

        $this->assertNotNull($group);
        $this->assertSame('Cash', $group['label']);
    }

    public function test_collections_overview_tab_active_on_overview_and_index_routes(): void
    {
        Route::get('/test-collections-overview', fn () => 'ok')->name('property.revenue.overview');
        Route::get('/test-collections-index', fn () => 'ok')->name('property.revenue.index');

        $overviewTab = collect(PropertyWorkspaceTabs::tabsFor('collections'))
            ->firstWhere('key', 'overview');

        $this->assertNotNull($overviewTab);
        $this->assertSame('property.revenue.overview', $overviewTab['route']);

        $this->app->instance('request', Request::create('/test-collections-overview', 'GET'));
        $this->assertTrue(PropertyWorkspaceTabs::tabIsActive($overviewTab, 'property.revenue.overview'));

        $this->app->instance('request', Request::create('/test-collections-index', 'GET'));
        $this->assertTrue(PropertyWorkspaceTabs::tabIsActive($overviewTab, 'property.revenue.index'));
    }

    public function test_revenue_index_is_not_a_hub_shell_redirect_route(): void
    {
        $this->assertFalse(PropertyWorkspaceTabs::isHubShellRoute('property.revenue.index'));
        $this->assertFalse(PropertyWorkspaceTabs::isHubShellRoute('property.revenue.overview'));
    }

    public function test_leases_tab_is_active_on_tenant_leases_route(): void
    {
        Route::get('/test-leases', fn () => 'ok')->name('property.tenants.leases');

        $this->app->instance('request', Request::create('/test-leases', 'GET'));

        $leasesTab = collect(PropertyWorkspaceTabs::tabsFor('tenants'))
            ->firstWhere('key', 'leases');

        $this->assertTrue(PropertyWorkspaceTabs::tabIsActive($leasesTab, 'property.tenants.leases'));
    }

    public function test_tab_is_active_excludes_expiry_query_from_leases_tab(): void
    {
        Route::get('/test-leases-expiry', fn () => 'ok')->name('property.tenants.leases');

        $this->app->instance('request', Request::create('/test-leases-expiry?tab=expiry', 'GET'));

        $leasesTab = collect(PropertyWorkspaceTabs::tabsFor('tenants'))
            ->firstWhere('key', 'leases');

        $this->assertFalse(PropertyWorkspaceTabs::tabIsActive($leasesTab, 'property.tenants.leases'));
    }

    public function test_tenants_workspace_has_no_primary_expiry_tab(): void
    {
        $keys = array_column(PropertyWorkspaceTabs::tabsFor('tenants'), 'key');

        $this->assertContains('leases', $keys);
        $this->assertNotContains('expiry', $keys);
    }

    public function test_only_leases_primary_tab_active_on_default_leases_route(): void
    {
        Route::get('/test-leases-only', fn () => 'ok')->name('property.tenants.leases');

        $this->app->instance('request', Request::create('/test-leases-only', 'GET'));

        $tabs = collect(PropertyWorkspaceTabs::tabsFor('tenants'));
        $activePrimary = $tabs->filter(
            fn (array $tab): bool => PropertyWorkspaceTabs::tabIsActive($tab, 'property.tenants.leases')
        )->pluck('key')->all();

        $this->assertSame(['leases'], $activePrimary);
    }

    public function test_expiring_soon_subtab_active_with_tab_query(): void
    {
        Route::get('/test-leases-exp-sub', fn () => 'ok')->name('property.tenants.leases');

        $this->app->instance('request', Request::create('/test-leases-exp-sub?tab=expiry', 'GET'));

        $group = PropertyWorkspaceTabs::resolveActiveSubTabGroup('property.tenants.leases');
        $this->assertNotNull($group);
        $this->assertSame('Leases', $group['label']);

        $expirySub = collect($group['tabs'])->firstWhere('key', 'expiry');
        $leasesSub = collect($group['tabs'])->firstWhere('key', 'leases');

        $this->assertTrue(PropertyWorkspaceTabs::tabIsActive($expirySub, 'property.tenants.leases'));
        $this->assertFalse(PropertyWorkspaceTabs::tabIsActive($leasesSub, 'property.tenants.leases'));
    }
}
