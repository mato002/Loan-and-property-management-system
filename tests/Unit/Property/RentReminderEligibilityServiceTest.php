<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmMessage;
use App\Models\PmMessagePreference;
use App\Models\PmMessageRecipient;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\RentReminderEligibilityService;
use App\Services\Property\TenantCommunicationStageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentReminderEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_is_not_eligible(): void
    {
        $invoice = $this->seedOpenInvoice();
        $this->allocateFullPayment($invoice);

        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            Carbon::parse('2026-06-05'),
            Carbon::parse('2026-06-12')
        );

        $decision = app(RentReminderEligibilityService::class)->evaluate(
            $invoice->fresh(),
            $stage,
            Carbon::parse('2026-06-12')
        );

        $this->assertFalse($decision['eligible']);
        $this->assertSame(RentReminderEligibilityService::REASON_PAID, $decision['reason']);
    }

    public function test_zero_balance_excludes_invoice_from_pipeline(): void
    {
        $invoice = $this->seedOpenInvoice();
        $this->allocateFullPayment($invoice);

        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            Carbon::parse('2026-06-05'),
            Carbon::parse('2026-06-12')
        );

        $decision = app(RentReminderEligibilityService::class)->evaluate(
            $invoice->fresh(),
            $stage,
            Carbon::parse('2026-06-12')
        );

        $this->assertFalse($decision['eligible']);
        $this->assertSame(RentReminderEligibilityService::REASON_PAID, $decision['reason']);
        $this->assertSame(
            0,
            app(RentReminderEligibilityService::class)
                ->reminderInvoiceQuery(Carbon::parse('2026-06-12'))
                ->whereKey($invoice->id)
                ->count()
        );
    }

    public function test_cancelled_invoice_is_inactive(): void
    {
        $invoice = $this->seedOpenInvoice();
        $invoice->update(['status' => PmInvoice::STATUS_CANCELLED, 'balance_due' => 1000]);

        $decision = app(RentReminderEligibilityService::class)->evaluate(
            $invoice->fresh(),
            ['internal_stage' => 'D+7'],
            Carbon::parse('2026-06-12')
        );

        $this->assertFalse($decision['eligible']);
        $this->assertSame(RentReminderEligibilityService::REASON_INACTIVE, $decision['reason']);
    }

    public function test_tenant_opted_out_of_arrears_reminders(): void
    {
        $invoice = $this->seedOpenInvoice();
        PmMessagePreference::query()->create([
            'subject_type' => 'tenant',
            'subject_id' => $invoice->pm_tenant_id,
            'category' => 'rent_reminder',
            'allow_sms' => true,
            'allow_email' => true,
            'allow_arrears_reminders' => false,
        ]);

        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            Carbon::parse('2026-06-05'),
            Carbon::parse('2026-06-12')
        );

        $decision = app(RentReminderEligibilityService::class)->evaluate(
            $invoice->fresh(),
            $stage,
            Carbon::parse('2026-06-12')
        );

        $this->assertFalse($decision['eligible']);
        $this->assertSame(RentReminderEligibilityService::REASON_TENANT_OPTED_OUT, $decision['reason']);
        $this->assertFalse(app(RentReminderEligibilityService::class)->tenantAllowsChannel((int) $invoice->pm_tenant_id, 'sms'));
    }

    public function test_channel_stage_blocks_when_recipient_already_sent(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $asOf = Carbon::parse('2026-06-12');

        $message = PmMessage::query()->create([
            'channel' => 'sms',
            'category' => 'rent_reminder',
            'status' => 'sent',
            'body' => 'test',
            'idempotency_key' => $service->idempotencyKeyForStage(42, 'sms', ['stage_key' => 'D+7', 'internal_stage' => 'D+12'], $asOf),
        ]);

        PmMessageRecipient::query()->create([
            'message_id' => $message->id,
            'channel' => 'sms',
            'to_address' => '254700000001',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $stage = ['stage_key' => 'D+7', 'internal_stage' => 'D+12'];
        $this->assertTrue($service->channelStageAlreadySent(42, 'sms', $stage, $asOf));
        $this->assertFalse($service->channelStageAlreadySent(42, 'email', $stage, $asOf));
    }

    public function test_channel_stage_allows_scheduler_retry_after_failed_delivery(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $asOf = Carbon::parse('2026-06-12');

        $message = PmMessage::query()->create([
            'channel' => 'sms',
            'category' => 'rent_reminder',
            'status' => 'failed',
            'body' => 'test',
            'idempotency_key' => $service->idempotencyKeyForStage(99, 'sms', ['stage_key' => 'D-1', 'internal_stage' => 'D-1'], $asOf),
        ]);

        PmMessageRecipient::query()->create([
            'message_id' => $message->id,
            'channel' => 'sms',
            'to_address' => '254700000099',
            'status' => 'failed',
            'retry_count' => 3,
            'max_retries' => 3,
            'failed_at' => now(),
            'last_error' => 'Provider balance error: 429',
        ]);

        $this->assertFalse($service->channelStageAlreadySent(99, 'sms', ['stage_key' => 'D-1', 'internal_stage' => 'D-1'], $asOf));
        $this->assertFalse($service->messageBlocksRentReminderRetry($message->fresh(['recipients'])));
    }

    public function test_channel_stage_blocks_while_queue_retries_are_pending(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $asOf = Carbon::parse('2026-06-12');

        $message = PmMessage::query()->create([
            'channel' => 'sms',
            'category' => 'rent_reminder',
            'status' => 'failed',
            'body' => 'test',
            'idempotency_key' => $service->idempotencyKeyForStage(77, 'sms', ['stage_key' => 'D+0', 'internal_stage' => 'D+0'], $asOf),
        ]);

        PmMessageRecipient::query()->create([
            'message_id' => $message->id,
            'channel' => 'sms',
            'to_address' => '254700000077',
            'status' => 'failed',
            'retry_count' => 1,
            'max_retries' => 3,
            'next_retry_at' => now()->addMinutes(8),
            'failed_at' => now(),
        ]);

        $this->assertTrue($service->channelStageAlreadySent(77, 'sms', ['stage_key' => 'D+0', 'internal_stage' => 'D+0'], $asOf));
    }

    public function test_overdue_bucket_idempotency_does_not_change_daily(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $stageDay8 = ['stage_key' => 'D+7', 'internal_stage' => 'D+8'];
        $stageDay9 = ['stage_key' => 'D+7', 'internal_stage' => 'D+9'];

        $keyDay8 = $service->idempotencyKeyForStage(10, 'sms', $stageDay8, Carbon::parse('2026-06-08'));
        $keyDay9 = $service->idempotencyKeyForStage(10, 'sms', $stageDay9, Carbon::parse('2026-06-09'));

        $this->assertSame($keyDay8, $keyDay9);
        $this->assertSame('rent:sms:10:D+7', $keyDay8);
    }

    public function test_log_guard_detects_same_invoice_sent_today(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $asOf = Carbon::parse('2026-06-02');

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[RENT] INV-000281 MONTHLY 2026-06',
            'body' => 'test',
            'delivery_status' => 'sent',
            'sent_at' => $asOf,
        ]);

        $this->assertTrue($service->logShowsRentReminderSentToday('254790686687', 'INV-000281', $asOf));
    }

    public function test_sms_resend_action_hides_resend_for_sent_rows(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $log = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[RENT] INV-000128 ESCALATION D+5',
            'body' => 'test',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);

        $action = $service->smsResendActionForLog($log);

        $this->assertFalse($action['can_resend']);
        $this->assertSame('Delivered', $action['label']);
    }

    public function test_sms_resend_action_hides_retry_when_invoice_already_sent(): void
    {
        $service = app(RentReminderEligibilityService::class);

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[RENT] INV-000128 ESCALATION D+5',
            'body' => 'ok',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);

        $failed = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[RENT] INV-000128 ESCALATION D+5',
            'body' => 'fail',
            'delivery_status' => 'failed',
            'delivery_error' => 'Provider balance error: 429',
        ]);

        $keys = $service->deliveredInvoiceKeysForInvoiceNumbers(['INV-000128']);
        $action = $service->smsResendActionForLog($failed, $keys);

        $this->assertFalse($action['can_resend']);
        $this->assertSame('Already sent', $action['label']);
    }

    public function test_supersede_failed_logs_when_invoice_already_delivered(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $body = 'Same reminder body text';

        $sent = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[STAFF|D+7|Overdue] INV-000281',
            'internal_stage' => 'D+7',
            'body' => $body,
            'delivery_status' => 'sent',
        ]);

        $failed = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[STAFF|D+7|Overdue] INV-000281',
            'internal_stage' => 'D+7',
            'body' => $body,
            'delivery_status' => 'failed',
            'delivery_error' => '429',
        ]);

        $updated = $service->supersedeStaleFailedSmsLogs(dryRun: false);

        $this->assertSame(1, $updated['superseded']);
        $failed->refresh();
        $this->assertSame('superseded', (string) $failed->delivery_status);
        $this->assertNotNull($failed->superseded_at);
        $this->assertSame((int) $sent->id, (int) $failed->superseded_by_log_id);
    }

    public function test_successful_sms_intent_blocks_resend_when_body_hash_matches(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $body = 'Pay rent for INV-000333';

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[STAFF|D+3|Reminder] INV-000333',
            'internal_stage' => 'D+3',
            'body' => $body,
            'delivery_status' => 'sent',
        ]);

        $failed = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[STAFF|D+3|Reminder] INV-000333',
            'internal_stage' => 'D+3',
            'body' => $body,
            'delivery_status' => 'failed',
        ]);

        $this->assertTrue($service->logShowsSuccessfulSmsForIntent(
            '254712345678',
            'INV-000333',
            'D+3',
            $service->messageBodyHash($body),
            (int) $failed->id
        ));

        $action = $service->smsResendActionForLog($failed);
        $this->assertFalse($action['can_resend']);
        $this->assertSame('Already sent', $action['label']);
    }

    public function test_backfill_supersede_when_sent_body_differs_from_failed(): void
    {
        $service = app(RentReminderEligibilityService::class);

        $sent = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[STAFF|D+7|Overdue] INV-000400',
            'body' => 'New template body after top-up',
            'delivery_status' => 'sent',
        ]);

        $failed = \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[RENT] INV-000400 MONTHLY 2026-06',
            'body' => 'Old failed body from cron',
            'delivery_status' => 'failed',
        ]);

        $result = $service->supersedeStaleFailedSmsLogs(dryRun: false);

        $this->assertGreaterThanOrEqual(1, $result['superseded']);
        $failed->refresh();
        $this->assertSame('superseded', (string) $failed->delivery_status);
        $this->assertSame((int) $sent->id, (int) $failed->superseded_by_log_id);
    }

    public function test_unresolved_failed_scope_hides_failed_row_when_sent_exists(): void
    {
        $service = app(RentReminderEligibilityService::class);
        $table = (new \App\Models\PmMessageLog)->getTable();

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[RENT] INV-000555 MONTHLY 2026-06',
            'body' => 'ok',
            'delivery_status' => 'sent',
        ]);

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254712345678',
            'subject' => '[RENT] INV-000555 MONTHLY 2026-06',
            'body' => 'fail',
            'delivery_status' => 'failed',
        ]);

        $count = \App\Models\PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($service, $table) {
                $service->applyUnresolvedFailedSmsScope($q, $table);
            })
            ->count();

        $this->assertSame(0, $count);
    }

    public function test_delivered_invoice_blocks_resend_even_on_another_day(): void
    {
        $service = app(RentReminderEligibilityService::class);

        \App\Models\PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '254790686687',
            'subject' => '[RENT] INV-000281 MONTHLY 2026-06',
            'body' => 'test',
            'delivery_status' => 'sent',
            'sent_at' => Carbon::parse('2026-06-01'),
            'created_at' => Carbon::parse('2026-06-01'),
        ]);

        $this->assertTrue($service->logShowsRentReminderDeliveredForInvoice('254790686687', 'INV-000281'));
    }

    public function test_open_balance_invoice_with_stage_is_eligible(): void
    {
        $invoice = $this->seedOpenInvoice();
        $stage = app(TenantCommunicationStageService::class)->resolveFromDueDate(
            Carbon::parse('2026-06-05'),
            Carbon::parse('2026-06-12')
        );

        $decision = app(RentReminderEligibilityService::class)->evaluate(
            $invoice,
            $stage,
            Carbon::parse('2026-06-12')
        );

        $this->assertTrue($decision['eligible']);
        $this->assertNull($decision['reason']);
        $this->assertGreaterThan(0, $decision['balance']);
    }

    private function allocateFullPayment(PmInvoice $invoice): void
    {
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $invoice->pm_tenant_id,
            'amount' => (float) $invoice->amount,
            'status' => PmPayment::STATUS_COMPLETED,
            'channel' => 'bank',
            'paid_at' => now(),
        ]);

        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => (float) $invoice->amount,
        ]);

        $invoice->syncAmountPaidFromAllocations();
    }

    private function seedOpenInvoice(): PmInvoice
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Reminder Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'R1',
            'rent_amount' => 5000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Reminder Tenant', 'email' => 't@example.com', 'phone' => '254700000001']);

        return PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-REM-'.uniqid(),
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-05',
            'amount' => 5000,
            'amount_paid' => 0,
            'balance_due' => 5000,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);
    }
}
