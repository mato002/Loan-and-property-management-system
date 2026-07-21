<?php

namespace Tests\Feature\Property;

use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PropertyWorkspaceBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('property_portal_settings')) {
            $this->markTestSkipped('property_portal_settings table missing.');
        }
    }

    public function test_super_admin_sees_platform_name_not_agent_branding(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        config(['app.name' => 'Platform ERP']);

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => null,
            'key' => 'company_name',
            'value' => 'Legacy Global Name',
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => null,
        ]);

        $this->actingAs($superAdmin);

        $this->assertSame('Platform ERP', PropertyWorkspaceBranding::get('company_name'));
        $this->assertSame('Platform ERP', PropertyPortalSetting::getValue('company_name'));
    }

    public function test_agent_sees_own_workspace_branding(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        config(['app.name' => 'Platform ERP']);

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        $otherAgent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Acme Properties',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $otherAgent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        $this->actingAs($agent);

        $this->assertSame('Acme Properties', PropertyPortalSetting::getValue('company_name'));
    }

    public function test_guest_login_prefers_configured_agent_over_stale_global_name(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        config([
            'app.name' => 'GaithoPropertyAgency',
            'property.login_branding_agent_user_id' => $agent->id,
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => null,
            'key' => 'company_name',
            'value' => 'Matech SaaS Property',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        $this->assertSame(
            'Gaitho Property Agency',
            PropertyWorkspaceBranding::forGuestPage('company_name')
        );
    }

    public function test_guest_login_uses_sole_agent_branding_when_global_empty(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        config(['app.name' => 'Matech SaaS Property']);

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        $this->assertSame(
            'Gaitho Property Agency',
            PropertyWorkspaceBranding::forGuestPage('company_name')
        );
    }

    public function test_guest_login_falls_back_to_app_name_with_multiple_agents(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        config(['app.name' => 'Matech SaaS Property']);

        foreach (['Acme Properties', 'Gaitho Property Agency'] as $name) {
            $agent = User::factory()->create([
                'property_portal_role' => 'agent',
            ]);

            PropertyPortalSetting::query()->create([
                'agent_user_id' => $agent->id,
                'key' => 'company_name',
                'value' => $name,
            ]);
        }

        $this->assertSame(
            'Matech SaaS Property',
            PropertyWorkspaceBranding::forGuestPage('company_name')
        );
    }

    public function test_super_admin_agent_user_reads_saved_branding_on_settings_form(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
            'is_super_admin' => true,
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        $this->actingAs($agent);

        $this->assertSame('Gaitho Property Agency', PropertyWorkspaceBranding::getForSettings('company_name'));
    }

    public function test_super_admin_without_agent_role_reads_sole_agent_branding_for_settings(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => null,
        ]);

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        PropertyPortalSetting::query()->create([
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Gaitho Property Agency',
        ]);

        $this->actingAs($superAdmin);

        $this->assertSame($agent->id, PropertyWorkspaceBranding::settingsEditorAgentUserId());
        $this->assertSame('Gaitho Property Agency', PropertyWorkspaceBranding::getForSettings('company_name'));
    }

    public function test_agent_can_save_branding_to_own_scope(): void
    {
        if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $this->markTestSkipped('agent_user_id column missing — run property migrations.');
        }

        $agent = User::factory()->create([
            'property_portal_role' => 'agent',
        ]);

        $this->actingAs($agent);

        PropertyPortalSetting::setValue('company_name', 'Sunrise Estates');

        $this->assertDatabaseHas('property_portal_settings', [
            'agent_user_id' => $agent->id,
            'key' => 'company_name',
            'value' => 'Sunrise Estates',
        ]);
    }
}
