<?php

namespace Tests\Unit\Property;

use App\Services\Property\SmsDeliveryErrorPresenter;
use Tests\TestCase;

class SmsDeliveryErrorPresenterTest extends TestCase
{
    public function test_rate_limit_json_becomes_plain_language(): void
    {
        $raw = 'Provider balance error: 429 {"statusCode":429,"name":"RATE_LIMIT_EXCEEDED","message":"Too many requests. Please try again in 60 seconds.","retry_after":60}';

        $message = app(SmsDeliveryErrorPresenter::class)->forAgent($raw);

        $this->assertStringContainsString('too many requests', strtolower($message));
        $this->assertStringNotContainsString('statusCode', $message);
        $this->assertStringNotContainsString('RATE_LIMIT_EXCEEDED', $message);
    }

    public function test_bulk_summary_groups_failures_without_json(): void
    {
        $summary = app(SmsDeliveryErrorPresenter::class)->summarizeBulkFailures([
            ['id' => 1016, 'error' => 'Provider balance error: 429 {"statusCode":429,"name":"RATE_LIMIT_EXCEEDED"}'],
            ['id' => 1017, 'error' => 'Provider balance error: 429 {"statusCode":429,"name":"RATE_LIMIT_EXCEEDED"}'],
        ]);

        $this->assertStringContainsString('Some messages were not sent', $summary);
        $this->assertStringContainsString('#1016', $summary);
        $this->assertStringNotContainsString('{', $summary);
    }
}
