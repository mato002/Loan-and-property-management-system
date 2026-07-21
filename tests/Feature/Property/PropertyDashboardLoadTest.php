<?php

namespace Tests\Feature\Property;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PropertyDashboardLoadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function smsProviderConfig(): array
    {
        return [
            'bulksms.provider.api_url' => 'https://sms-provider.test',
            'bulksms.provider.client_id' => 'client',
            'bulksms.provider.api_key' => 'key',
            'bulksms.provider.balance_path' => 'balance',
            'bulksms.dashboard_balance_source' => 'auto',
        ];
    }

    public function test_property_dashboard_loads_without_blocking_sms_provider_http(): void
    {
        Http::fake();
        config($this->smsProviderConfig());

        $user = User::factory()->create([
            'property_portal_role' => 'agent',
            'loan_role' => 'admin',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->get(route('property.dashboard'));

        $response->assertOk();
        $response->assertSee('Quick start checklist', false);
        $response->assertDontSee('Loading dashboard metrics', false);
        Http::assertNothingSent();
    }

    public function test_module_switch_to_property_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'property_portal_role' => 'agent',
            'loan_role' => 'admin',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'loan'])
            ->get(route('module.switch', ['module' => 'property']));

        $response->assertRedirect(route('property.dashboard'));
    }

    public function test_module_switch_defers_heavy_dashboard_metrics_on_first_paint(): void
    {
        Http::fake();
        config($this->smsProviderConfig());

        $user = User::factory()->create([
            'property_portal_role' => 'agent',
            'loan_role' => 'admin',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'loan'])
            ->followingRedirects()
            ->get(route('module.switch', ['module' => 'property']));

        $response->assertOk();
        $response->assertSee('property-dashboard-metrics', false);
        $response->assertSee('Loading dashboard metrics', false);
        $response->assertDontSee('Quick start checklist', false);
    }

    public function test_dashboard_metrics_frame_loads_full_content(): void
    {
        Http::fake();
        config($this->smsProviderConfig());

        $user = User::factory()->create([
            'property_portal_role' => 'agent',
            'loan_role' => 'admin',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->get(route('property.dashboard.metrics'));

        $response->assertOk();
        $response->assertSee('Quick start checklist', false);
        $response->assertSee('property-dashboard-metrics', false);
        Http::assertNothingSent();
    }
}
