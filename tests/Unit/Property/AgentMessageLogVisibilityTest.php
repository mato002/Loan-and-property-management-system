<?php

namespace Tests\Unit\Property;

use App\Models\PmMessageLog;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentMessageLogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_sees_system_sms_to_their_tenant_phone(): void
    {
        $agent = User::factory()->create([
            'is_super_admin' => false,
            'property_portal_role' => 'agent',
        ]);
        $otherAgent = User::factory()->create([
            'is_super_admin' => false,
            'property_portal_role' => 'agent',
        ]);

        $property = Property::query()->create([
            'name' => 'Agent Property',
            'agent_user_id' => $agent->id,
        ]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 10000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Jane Tenant',
            'phone' => '254712345678',
            'email' => 'jane@example.com',
            'agent_user_id' => $agent->id,
        ]);

        PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'sms',
            'to_address' => '254712345678',
            'body' => 'Auto rent reminder',
            'delivery_status' => 'sent',
            'template_category' => 'rent_reminder',
        ]);

        PmMessageLog::query()->create([
            'user_id' => $agent->id,
            'channel' => 'sms',
            'to_address' => '254700000001',
            'body' => 'Manual send',
            'delivery_status' => 'sent',
        ]);

        $otherTenant = PmTenant::query()->create([
            'name' => 'Other Tenant',
            'phone' => '254799999999',
            'agent_user_id' => $otherAgent->id,
        ]);

        PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'sms',
            'to_address' => '254799999999',
            'body' => 'Other agent reminder',
            'delivery_status' => 'sent',
        ]);

        $this->actingAs($agent);
        $visible = PmMessageLog::query()->pluck('body')->all();

        $this->assertContains('Auto rent reminder', $visible);
        $this->assertContains('Manual send', $visible);
        $this->assertNotContains('Other agent reminder', $visible);
    }

    public function test_super_admin_sees_all_message_logs(): void
    {
        $admin = User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]);

        PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'sms',
            'to_address' => '254711111111',
            'body' => 'System wide',
            'delivery_status' => 'sent',
        ]);

        $this->actingAs($admin);
        $this->assertSame(1, PmMessageLog::query()->count());
    }
}
