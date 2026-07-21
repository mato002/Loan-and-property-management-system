<?php

namespace Tests\Unit\Property;

use App\Models\PmMessageLog;
use App\Services\Property\SmsHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolved_failed_excludes_superseded_and_matching_sent(): void
    {
        PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '+254712345678',
            'subject' => '[STAFF|D-3|INV-000101] Rent reminder',
            'body' => 'Pay INV-000101',
            'delivery_status' => 'failed',
            'template_category' => 'rent_reminder',
            'internal_stage' => 'D-3',
        ]);

        PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '+254712345678',
            'subject' => '[STAFF|D-3|INV-000101] Rent reminder',
            'body' => 'Pay INV-000101',
            'delivery_status' => 'sent',
            'template_category' => 'rent_reminder',
            'internal_stage' => 'D-3',
        ]);

        PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '+254799999999',
            'subject' => '[STAFF|D-3|INV-000202] Rent reminder',
            'body' => 'Pay INV-000202',
            'delivery_status' => 'failed',
            'template_category' => 'rent_reminder',
            'internal_stage' => 'D-3',
        ]);

        $health = app(SmsHealthService::class);

        $this->assertSame(1, $health->unresolvedFailedCount());
        $this->assertSame(1, $health->unresolvedRentReminderSmsCountForDate(now()));

        PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '+254799999999',
            'subject' => '[STAFF|D-3|INV-000202] Rent reminder',
            'body' => 'Pay INV-000202',
            'delivery_status' => 'sent',
            'template_category' => 'rent_reminder',
            'internal_stage' => 'D-3',
        ]);

        $this->assertSame(0, $health->unresolvedFailedCount());
        $this->assertSame(0, $health->unresolvedRentReminderSmsCountForDate(now()));
        $this->assertSame(0, $health->rentReminderFailuresNeedingActionToday());
    }

    public function test_superseded_count_tracks_superseded_rows(): void
    {
        PmMessageLog::query()->create([
            'channel' => 'sms',
            'to_address' => '+254711111111',
            'subject' => '[STAFF|D-3|INV-000303]',
            'body' => 'INV-000303',
            'delivery_status' => 'superseded',
            'superseded_at' => now(),
        ]);

        $this->assertSame(1, app(SmsHealthService::class)->supersededCount());
    }
}
