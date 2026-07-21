<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmMessageTemplate;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\RentReminderMessageLogResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentReminderMessageLogResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_resolver_uses_active_template_not_stored_body(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Test Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'B2',
            'rent_amount' => 8000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Mary Tenant',
            'phone' => '254712345654',
            'email' => 'mary@example.com',
        ]);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000015',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-05',
            'amount' => 8000,
            'amount_paid' => 0,
            'balance_due' => 8000,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        PmMessageTemplate::query()->create([
            'name' => 'Rent SMS',
            'category' => 'rent_reminder',
            'channel' => 'sms',
            'body' => "UPDATED Hello {tenant_name} for {invoice_no} balance {balance}.",
            'is_active' => true,
        ]);

        $log = PmMessageLog::query()->create([
            'user_id' => null,
            'channel' => 'sms',
            'to_address' => '254712345654',
            'subject' => '[STAFF|D+3|3 Days Overdue] INV-000015',
            'internal_stage' => 'D+3',
            'display_stage' => '3 Days Overdue',
            'template_category' => 'rent_reminder',
            'body' => 'OLD BAD TEMPLATE TEXT',
            'delivery_status' => 'failed',
        ]);

        $body = app(RentReminderMessageLogResolver::class)->resolveSmsBody($log);

        $this->assertStringContainsString('UPDATED Hello', (string) $body);
        $this->assertStringContainsString('INV-000015', (string) $body);
        $this->assertStringNotContainsString('OLD BAD', (string) $body);
        $this->assertStringNotContainsString('STOP *456', (string) $body);
    }

    public function test_legacy_stored_template_is_bypassed_for_resend(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $property = Property::query()->create(['name' => 'Legacy Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U1',
            'rent_amount' => 11000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Test Tenant',
            'phone' => '254790686687',
        ]);

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000281',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-30',
            'amount' => 11000,
            'amount_paid' => 0,
            'balance_due' => 11000,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        PmMessageTemplate::query()->create([
            'name' => 'Old rent SMS',
            'category' => 'rent_reminder',
            'channel' => 'sms',
            'body' => "[RENT] {invoice_no} MONTHLY {due_date}\nUnit: {unit_name}\nDue: {due_date}\nBal: {balance} STOP *456*9*5#",
            'is_active' => true,
        ]);

        $log = PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[RENT] INV-000281 MONTHLY 2026-06',
            'template_category' => 'rent_reminder',
            'body' => 'stale log body',
            'delivery_status' => 'failed',
        ]);

        $body = app(RentReminderMessageLogResolver::class)->resolveSmsBody($log);

        $this->assertStringNotContainsString('STOP *456', (string) $body);
        $this->assertStringNotContainsString('[RENT] INV-000281 MONTHLY', (string) $body);
    }

    public function test_resend_rebuilds_when_invoice_is_outside_scheduler_stage_window(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-06-02 08:00:00');

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $property = Property::query()->create(['name' => 'Ruaka Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'RUAKA PLOT 1/15',
            'rent_amount' => 11000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Test Tenant',
            'phone' => '254790686687',
        ]);

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000281',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-30',
            'amount' => 11000,
            'amount_paid' => 0,
            'balance_due' => 11000,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        PmMessageTemplate::query()->create([
            'name' => 'Rent SMS',
            'category' => 'rent_reminder',
            'channel' => 'sms',
            'body' => "[RENT] {invoice_no}\nUnit: {unit_name}\nDue: {due_date}\nBal: {balance}",
            'is_active' => true,
        ]);

        $log = PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[RENT] INV-000281 MONTHLY 2026-06',
            'template_category' => 'rent_reminder',
            'body' => "[RENT] INV-000281 MONTHLY 2026-06\nUnit: RUAKA PLOT 1/15\nDue: 2026-06-30\nBal: 11,000.00",
            'delivery_status' => 'failed',
        ]);

        $body = app(RentReminderMessageLogResolver::class)->resolveSmsBody($log);

        $this->assertNotNull($body);
        $this->assertStringNotContainsString('[RENT] INV-000281', (string) $body);
        $this->assertStringContainsString('INV-000281', (string) $body);
        $this->assertStringContainsString('11,000.00', (string) $body);

        \Illuminate\Support\Carbon::setTestNow();
    }
}
