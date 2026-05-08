<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmConversation;
use App\Models\PmConversationMessage;
use App\Models\PmTenant;
use App\Services\Property\PropertyCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
