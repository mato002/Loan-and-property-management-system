<?php

namespace Tests\Unit\Property;

use App\Services\Property\PropertyCommunicationTemplateService;
use App\Services\Property\TenantCommunicationStageService;
use Carbon\Carbon;
use Tests\TestCase;

class TenantCommunicationStageServiceTest extends TestCase
{
    public function test_resolves_pre_due_stages(): void
    {
        $service = app(TenantCommunicationStageService::class);
        $due = Carbon::parse('2026-06-05');

        $stage = $service->resolveFromDueDate($due, Carbon::parse('2026-06-02'));
        $this->assertNotNull($stage);
        $this->assertSame('D-3', $stage['internal_stage']);
        $this->assertSame('Due in 3 Days', $stage['display_label']);

        $tomorrow = $service->resolveFromDueDate($due, Carbon::parse('2026-06-04'));
        $this->assertSame('D-1', $tomorrow['internal_stage']);
        $this->assertSame('Due Tomorrow', $tomorrow['display_label']);
    }

    public function test_resolves_due_today_and_overdue_buckets(): void
    {
        $service = app(TenantCommunicationStageService::class);
        $asOf = Carbon::parse('2026-06-10');

        $today = $service->resolveFromDueDate(Carbon::parse('2026-06-10'), $asOf);
        $this->assertSame('D+0', $today['internal_stage']);
        $this->assertSame('Due Today', $today['display_label']);

        $seven = $service->resolveFromDueDate(Carbon::parse('2026-06-03'), $asOf);
        $this->assertSame('D+7', $seven['internal_stage']);
        $this->assertSame('7 Days Overdue', $seven['display_label']);
    }

    public function test_tenant_sms_body_uses_standard_structure_without_internal_codes(): void
    {
        $stageService = app(TenantCommunicationStageService::class);
        $templates = app(PropertyCommunicationTemplateService::class);
        $stage = $stageService->resolveFromDueDate(Carbon::parse('2026-06-03'), Carbon::parse('2026-06-10'));

        $sms = $templates->buildRentReminderSms([
            'tenant_name' => 'Jane Wanjiku',
            'invoice_no' => 'INV-000242',
            'unit_name' => 'SITA/15',
            'balance' => '2,500.00',
            'due_date' => '2026-06-03',
            'stage' => $stage,
            'agent' => [
                'name' => 'Peter Agent',
                'phone' => '0712345678',
                'email' => 'peter@gaitho.co.ke',
            ],
        ]);

        $this->assertStringContainsString('Dear Jane Wanjiku,', $sms);
        $this->assertStringNotContainsString('STOP', $sms);
        $this->assertStringContainsString('INV-000242', $sms);
        $this->assertStringContainsString('SITA/15', $sms);
        $this->assertStringContainsString('KES 2,500.00', $sms);
        $this->assertStringContainsString('For assistance, call 0712345678.', $sms);
        $this->assertStringNotContainsString('[RENT OVERDUE]', $sms);
        $this->assertStringNotContainsString('Need help?', $sms);
        $this->assertStringNotContainsString('http://', $sms);
        $this->assertStringContainsString('7 days overdue', strtolower($sms));
        $this->assertStringNotContainsString('D+7', $sms);
        $this->assertStringNotContainsString('D+0', $sms);
    }

    public function test_salutation_falls_back_to_dear_tenant(): void
    {
        $templates = app(PropertyCommunicationTemplateService::class);
        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            Carbon::parse('2026-06-03'),
            Carbon::parse('2026-06-10')
        );

        $sms = $templates->buildRentReminderSms([
            'tenant_name' => '',
            'invoice_no' => 'INV-1',
            'unit_name' => 'A-1',
            'balance' => '100.00',
            'stage' => $stage,
        ]);

        $this->assertStringContainsString('Dear Tenant,', $sms);
        $this->assertStringNotContainsString('Dear Valued Tenant', $sms);
    }

    public function test_preview_includes_sms_segment_estimate(): void
    {
        $preview = app(PropertyCommunicationTemplateService::class)->previewRentReminder('D+7', 'sms');

        $this->assertSame('Mary Ndugu', app(PropertyCommunicationTemplateService::class)->previewSampleContext('D+7')['tenant_name']);
        $this->assertStringContainsString('Dear Mary Ndugu,', $preview['body']);
        $this->assertGreaterThanOrEqual(1, $preview['sms_segments']);
        $this->assertGreaterThan(0, $preview['estimated_cost']);
        $this->assertStringNotContainsString('D+7', $preview['body']);
    }

    public function test_portal_message_omits_company_header_but_keeps_salutation(): void
    {
        $payload = app(PropertyCommunicationTemplateService::class)->buildRentReminder(
            app(PropertyCommunicationTemplateService::class)->previewSampleContext('D+0'),
            'portal'
        );

        $this->assertStringStartsWith('Dear Mary Ndugu,', $payload['body']);
        $this->assertSame('Due Today', $payload['subject']);
        $this->assertStringNotContainsString('D+0', $payload['body']);
    }

    public function test_staff_subject_preserves_internal_stage(): void
    {
        $service = app(TenantCommunicationStageService::class);
        $subject = $service->staffSubjectLine([
            'internal_stage' => 'D+7',
            'display_label' => '7 Days Overdue',
            'invoice_no' => 'INV-000242',
        ]);

        $this->assertSame('[STAFF|D+7|7 Days Overdue] INV-000242', $subject);

        $parsed = $service->parseStaffSubject($subject);
        $this->assertSame('D+7', $parsed['internal_stage']);
        $this->assertSame('7 Days Overdue', $parsed['display_label']);
    }
}
