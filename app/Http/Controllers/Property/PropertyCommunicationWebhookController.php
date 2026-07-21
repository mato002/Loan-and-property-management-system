<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmConversation;
use App\Models\PmConversationMessage;
use App\Models\PmTenant;
use App\Services\BulkSmsService;
use App\Services\Property\PradytecBulkSmsWebhookService;
use App\Services\Property\PropertyCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PropertyCommunicationWebhookController extends Controller
{
    public function smsDelivery(Request $request, PropertyCommunicationService $service): Response
    {
        $payload = $request->all();
        $providerId = (string) ($payload['provider_message_id'] ?? $payload['message_id'] ?? '');
        if ($providerId === '') {
            return response(['ok' => false, 'error' => 'provider_message_id is required'], 422);
        }

        $service->markProviderCallback('bulksms', $providerId, $payload);

        return response(['ok' => true]);
    }

    public function pradytec(
        Request $request,
        BulkSmsService $bulkSms,
        PradytecBulkSmsWebhookService $handler,
    ): Response {
        $raw = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');

        if (! $bulkSms->verifyWebhookSignature($raw, is_string($signature) ? $signature : null)) {
            return response(['ok' => false, 'error' => 'Invalid webhook signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response(['ok' => false, 'error' => 'Invalid JSON payload'], 422);
        }

        $event = trim((string) ($request->header('X-Webhook-Event') ?? $payload['event'] ?? ''));
        if ($event === '') {
            return response(['ok' => false, 'error' => 'Missing event'], 422);
        }

        $expectedClient = trim((string) config('bulksms.provider.client_id', ''));
        $payloadClient = trim((string) ($payload['client_id'] ?? ''));
        if ($expectedClient !== '' && $payloadClient !== '' && $expectedClient !== $payloadClient) {
            return response(['ok' => false, 'error' => 'Client mismatch'], 403);
        }

        try {
            $handler->handle($event, $payload);
        } catch (\Throwable $e) {
            Log::error('Pradytec webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return response(['ok' => false, 'error' => 'Processing failed'], 500);
        }

        return response(['ok' => true]);
    }

    public function smsInbound(Request $request): Response
    {
        $from = trim((string) ($request->input('from') ?? $request->input('phone') ?? ''));
        $body = trim((string) ($request->input('body') ?? $request->input('message') ?? ''));
        if ($from === '' || $body === '') {
            return response(['ok' => false, 'error' => 'from and body are required'], 422);
        }

        $tenant = PmTenant::query()->where('phone', 'like', '%'.preg_replace('/\D+/', '', $from).'%')->first();
        $conversation = PmConversation::query()->firstOrCreate(
            ['pm_tenant_id' => $tenant?->id, 'category' => 'inbound_sms'],
            ['topic' => 'Inbound SMS', 'status' => 'open', 'priority' => 'normal']
        );

        PmConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'channel' => 'sms',
            'sender_type' => 'tenant',
            'sender_id' => $tenant?->id,
            'to_address' => $from,
            'body' => $body,
            'sent_at' => now(),
            'meta' => $request->all(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response(['ok' => true]);
    }
}
