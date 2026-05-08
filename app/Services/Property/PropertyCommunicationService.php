<?php

namespace App\Services\Property;

use App\Jobs\SendBulkCommunicationJob;
use App\Jobs\SendEmailJob;
use App\Jobs\SendSmsJob;
use App\Models\PmMessage;
use App\Models\PmMessageBatch;
use App\Models\PmConversation;
use App\Models\PmConversationMessage;
use App\Models\PmMessageAttachment;
use App\Models\PmMessageDelivery;
use App\Models\PmMessageLog;
use App\Models\PmMessagePreference;
use App\Models\PmMessageRecipient;
use App\Models\PmTenantNotice;
use App\Models\PropertyPortalSetting;
use App\Services\BulkSmsService;
use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PropertyCommunicationService
{
    public function __construct(private readonly BulkSmsService $sms)
    {
    }

    /**
     * @param  list<string>  $recipients
     */
    public function sendNow(array $payload, array $recipients): PmMessage
    {
        return $this->createMessage($payload, $recipients, true);
    }

    /**
     * @param  list<string>  $recipients
     */
    public function schedule(array $payload, array $recipients): PmMessage
    {
        return $this->createMessage($payload, $recipients, false);
    }

    /**
     * @param  list<string>  $recipients
     */
    private function createMessage(array $payload, array $recipients, bool $sendNow): PmMessage
    {
        return DB::transaction(function () use ($payload, $recipients, $sendNow) {
            $channel = strtolower((string) ($payload['channel'] ?? 'system'));
            $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
            if ($idempotencyKey !== '') {
                $existing = PmMessage::query()
                    ->where('channel', $channel)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $batch = null;
            if (($payload['is_bulk'] ?? false) === true) {
                $batch = PmMessageBatch::query()->create([
                    'name' => (string) ($payload['batch_name'] ?? $payload['subject'] ?? 'Bulk communication'),
                    'channel' => $channel,
                    'status' => $sendNow ? 'queued' : 'scheduled',
                    'created_by_user_id' => $payload['created_by_user_id'] ?? null,
                    'recipient_count' => count($recipients),
                    'estimated_cost' => $channel === 'sms'
                        ? round(count($recipients) * $this->sms->costPerSms(), 4)
                        : null,
                ]);
            }

            $subject = $this->renderTemplate((string) ($payload['subject'] ?? ''), (array) ($payload['variables'] ?? []));
            $body = $this->renderTemplate((string) ($payload['body'] ?? ''), (array) ($payload['variables'] ?? []));

            $policy = $this->evaluateDispatchPolicy($channel, count($recipients));
            $resolvedSendNow = $sendNow && ($policy['defer_until'] ?? null) === null;

            $message = PmMessage::query()->create([
                'batch_id' => $batch?->id,
                'created_by_user_id' => $payload['created_by_user_id'] ?? null,
                'channel' => $channel,
                'category' => (string) ($payload['category'] ?? 'general_notice'),
                'purpose' => $payload['purpose'] ?? null,
                'priority' => (string) ($payload['priority'] ?? 'normal'),
                'severity' => (string) ($payload['severity'] ?? 'info'),
                'status' => $resolvedSendNow ? 'queued' : 'scheduled',
                'subject' => $subject !== '' ? $subject : null,
                'body' => $body,
                'template_id' => $payload['template_id'] ?? null,
                'template_version' => $payload['template_version'] ?? null,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'scheduled_at' => $payload['scheduled_at'] ?? ($policy['defer_until'] ?? null),
                'queued_at' => $resolvedSendNow ? now() : null,
            ]);
            $this->persistAttachments($message, (array) ($payload['attachments'] ?? []));

            $recipientRows = [];
            foreach ($recipients as $recipient) {
                $normalized = trim((string) $recipient);
                if ($normalized === '') {
                    continue;
                }

                $policy = $this->checkRecipientPolicy(
                    $channel,
                    (string) ($payload['category'] ?? 'general_notice'),
                    (string) ($payload['recipient_type'] ?? ''),
                    (int) ($payload['recipient_id'] ?? 0)
                );
                $isBlocked = $policy['allowed'] === false;

                $recipientRows[] = [
                    'message_id' => $message->id,
                    'channel' => $channel,
                    'recipient_type' => $payload['recipient_type'] ?? null,
                    'recipient_id' => $payload['recipient_id'] ?? null,
                    'to_address' => $normalized,
                    'status' => $isBlocked ? 'cancelled' : ($resolvedSendNow ? 'queued' : 'scheduled'),
                    'is_opted_out' => $isBlocked,
                    'opt_out_reason' => $policy['reason'] ?? null,
                    'queued_at' => $resolvedSendNow && ! $isBlocked ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($recipientRows !== []) {
                PmMessageRecipient::query()->insert($recipientRows);
            }

            if ($resolvedSendNow) {
                SendBulkCommunicationJob::dispatch($message->id);
            }

            return $message->fresh(['recipients']);
        });
    }

    public function queueMessageRecipients(PmMessage $message): void
    {
        $message->loadMissing('recipients');
        foreach ($message->recipients as $recipient) {
            if ($recipient->status === 'cancelled') {
                continue;
            }

            if ($recipient->channel === 'sms') {
                SendSmsJob::dispatch($recipient->id);
            } elseif ($recipient->channel === 'email') {
                SendEmailJob::dispatch($recipient->id);
            }
        }
    }

    public function dispatchSmsRecipient(PmMessageRecipient $recipient): void
    {
        $message = $recipient->message;
        if (! $message || $recipient->status === 'sent') {
            return;
        }

        $recipient->update(['status' => 'sending', 'sending_at' => now()]);
        $delivery = $this->startDelivery($recipient);
        $result = $this->sms->sendNow($message->body, [$recipient->to_address], $message->created_by_user_id, null, 'property');

        if (($result['ok'] ?? false) === true) {
            $this->markDeliverySuccess($recipient, $delivery, [
                'provider' => 'bulksms',
                'provider_response' => $result,
                'cost' => $this->sms->costPerSms(),
            ]);
            return;
        }

        $this->markDeliveryFailure($recipient, $delivery, (string) ($result['error'] ?? 'SMS dispatch failed.'));
    }

    public function dispatchEmailRecipient(PmMessageRecipient $recipient): void
    {
        $message = $recipient->message;
        if (! $message || $recipient->status === 'sent') {
            return;
        }

        $recipient->update(['status' => 'sending', 'sending_at' => now()]);
        $delivery = $this->startDelivery($recipient);

        try {
            $attachments = $message->attachments()->get();
            Mail::raw($message->body, function ($m) use ($recipient, $message, $attachments) {
                $m->to($recipient->to_address)->subject((string) ($message->subject ?: '(No subject)'));
                foreach ($attachments as $attachment) {
                    $path = storage_path('app/'.$attachment->disk.'/'.$attachment->path);
                    if (is_file($path)) {
                        $m->attach($path, [
                            'as' => $attachment->file_name,
                            'mime' => $attachment->mime_type ?: null,
                        ]);
                    }
                }
            });
            $this->markDeliverySuccess($recipient, $delivery, ['provider' => 'laravel_mail']);
        } catch (\Throwable $e) {
            $this->markDeliveryFailure($recipient, $delivery, 'Email failed: '.$e->getMessage());
        }
    }

    public function markProviderCallback(string $provider, string $providerMessageId, array $payload): void
    {
        $delivery = PmMessageDelivery::query()
            ->withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('provider_message_id', $providerMessageId)
            ->latest('id')
            ->first();

        if (! $delivery) {
            return;
        }

        $status = strtolower((string) Arr::get($payload, 'status', Arr::get($payload, 'delivery_status', '')));
        $recipient = $delivery->recipient;
        if (! $recipient) {
            return;
        }

        if (in_array($status, ['delivered', 'success'], true)) {
            $delivery->update([
                'provider_status' => $status,
                'provider_response' => $payload,
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
            $recipient->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
            $this->syncNoticeDeliveryProof($delivery, 'delivered');
        } elseif (in_array($status, ['failed', 'undelivered', 'rejected', 'expired'], true)) {
            $reason = (string) Arr::get($payload, 'failure_reason', Arr::get($payload, 'error', 'Provider marked failed'));
            $this->markDeliveryFailure($recipient, $delivery, $reason, $payload, $status);
            $this->syncNoticeDeliveryProof($delivery, 'failed');
        }
    }

    private function startDelivery(PmMessageRecipient $recipient): PmMessageDelivery
    {
        return PmMessageDelivery::query()->create([
            'message_id' => $recipient->message_id,
            'recipient_id' => $recipient->id,
            'channel' => $recipient->channel,
            'status' => 'sending',
            'attempt' => $recipient->retry_count + 1,
            'queued_at' => $recipient->queued_at ?: now(),
        ]);
    }

    private function markDeliverySuccess(PmMessageRecipient $recipient, PmMessageDelivery $delivery, array $meta = []): void
    {
        $delivery->update([
            'provider' => $meta['provider'] ?? $delivery->provider,
            'provider_message_id' => $meta['provider_message_id'] ?? $delivery->provider_message_id,
            'provider_response' => $meta['provider_response'] ?? $delivery->provider_response,
            'status' => 'sent',
            'sent_at' => now(),
            'cost' => $meta['cost'] ?? $delivery->cost,
            'failure_reason' => null,
        ]);

        $recipient->update([
            'status' => 'sent',
            'sent_at' => now(),
            'last_error' => null,
            'failed_at' => null,
        ]);

        $recipient->message?->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        PmMessageLog::query()->create([
            'user_id' => $recipient->message?->created_by_user_id,
            'channel' => $recipient->channel,
            'to_address' => $recipient->to_address,
            'subject' => $recipient->message?->subject,
            'body' => (string) ($recipient->message?->body ?? ''),
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'sent_at' => now(),
        ]);
        $this->syncConversationForRecipient($recipient, 'outbound');
    }

    private function markDeliveryFailure(
        PmMessageRecipient $recipient,
        PmMessageDelivery $delivery,
        string $reason,
        array $providerPayload = [],
        ?string $providerStatus = null
    ): void {
        $recipient->update([
            'status' => 'failed',
            'failed_at' => now(),
            'last_error' => $reason,
            'retry_count' => $recipient->retry_count + 1,
            'next_retry_at' => $recipient->retry_count + 1 < $recipient->max_retries ? now()->addMinutes(10) : null,
        ]);

        $delivery->update([
            'provider_status' => $providerStatus,
            'provider_response' => $providerPayload !== [] ? $providerPayload : $delivery->provider_response,
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        if ($recipient->retry_count < $recipient->max_retries) {
            if ($recipient->channel === 'sms') {
                SendSmsJob::dispatch($recipient->id)->delay(now()->addMinutes(10));
            } elseif ($recipient->channel === 'email') {
                SendEmailJob::dispatch($recipient->id)->delay(now()->addMinutes(10));
            }
        }

        PmMessageLog::query()->create([
            'user_id' => $recipient->message?->created_by_user_id,
            'channel' => $recipient->channel,
            'to_address' => $recipient->to_address,
            'subject' => $recipient->message?->subject,
            'body' => (string) ($recipient->message?->body ?? ''),
            'delivery_status' => 'failed',
            'delivery_error' => $reason,
            'sent_at' => null,
        ]);
        $this->syncConversationForRecipient($recipient, 'outbound', $reason);
    }

    private function checkRecipientPolicy(string $channel, string $category, string $recipientType, int $recipientId): array
    {
        if ($recipientType === '' || $recipientId <= 0) {
            return ['allowed' => true];
        }

        $pref = PmMessagePreference::query()
            ->where('subject_type', $recipientType)
            ->where('subject_id', $recipientId)
            ->where('category', $category)
            ->first();

        if (! $pref) {
            return ['allowed' => true];
        }

        if ($channel === 'sms' && ! $pref->allow_sms) {
            return ['allowed' => false, 'reason' => 'Recipient opted out of SMS.'];
        }
        if ($channel === 'email' && ! $pref->allow_email) {
            return ['allowed' => false, 'reason' => 'Recipient opted out of email.'];
        }

        return ['allowed' => true];
    }

    /**
     * @param  array<int, UploadedFile|string>  $attachments
     */
    private function persistAttachments(PmMessage $message, array $attachments): void
    {
        foreach ($attachments as $item) {
            if ($item instanceof UploadedFile) {
                $storedPath = $item->store('property/messages', 'public');
                PmMessageAttachment::query()->create([
                    'message_id' => $message->id,
                    'disk' => 'public',
                    'path' => $storedPath,
                    'file_name' => $item->getClientOriginalName() ?: basename($storedPath),
                    'mime_type' => $item->getMimeType(),
                    'size' => (int) ($item->getSize() ?? 0),
                ]);
            } elseif (is_string($item) && trim($item) !== '') {
                PmMessageAttachment::query()->create([
                    'message_id' => $message->id,
                    'disk' => 'public',
                    'path' => trim($item),
                    'file_name' => basename(trim($item)),
                ]);
            }
        }
    }

    private function syncNoticeDeliveryProof(PmMessageDelivery $delivery, string $state): void
    {
        $notice = PmTenantNotice::query()
            ->where('message_id', $delivery->message_id)
            ->latest('id')
            ->first();
        if (! $notice) {
            return;
        }

        $update = ['delivery_proof_id' => $delivery->id];
        if ($state === 'delivered' && in_array((string) $notice->status, ['sent', 'approved'], true)) {
            $update['status'] = 'delivered';
        }
        if ($state === 'failed' && in_array((string) $notice->status, ['sent', 'approved'], true)) {
            $update['status'] = 'escalated';
        }
        $notice->update($update);
    }

    /**
     * @return array{defer_until:\Illuminate\Support\CarbonInterface|null}
     */
    private function evaluateDispatchPolicy(string $channel, int $recipientCount): array
    {
        if ($channel !== 'sms') {
            return ['defer_until' => null];
        }

        $now = now();
        $startHour = (int) PropertyPortalSetting::getValue('communications_sms_send_window_start_hour', '8');
        $endHour = (int) PropertyPortalSetting::getValue('communications_sms_send_window_end_hour', '19');
        $currentHour = (int) $now->format('G');
        if ($currentHour < $startHour || $currentHour >= $endHour) {
            $next = $now->copy()->startOfDay()->addDay()->setTime($startHour, 0);
            return ['defer_until' => $next];
        }

        $dailyLimit = (int) PropertyPortalSetting::getValue('communications_daily_sms_limit', '0');
        if ($dailyLimit > 0) {
            $todaySent = PmMessageRecipient::query()
                ->withoutGlobalScopes()
                ->where('channel', 'sms')
                ->whereIn('status', ['sent', 'delivered'])
                ->whereDate('created_at', $now->toDateString())
                ->count();
            if ($todaySent + $recipientCount > $dailyLimit) {
                $next = $now->copy()->addDay()->startOfDay()->setTime($startHour, 0);
                return ['defer_until' => $next];
            }
        }

        return ['defer_until' => null];
    }

    private function syncConversationForRecipient(PmMessageRecipient $recipient, string $direction, ?string $error = null): void
    {
        $message = $recipient->message;
        if (! $message) {
            return;
        }

        $conversation = PmConversation::query()->firstOrCreate(
            [
                'pm_tenant_id' => $recipient->recipient_type === 'tenant' ? $recipient->recipient_id : null,
                'category' => $message->category,
            ],
            [
                'topic' => $message->subject ?: ucfirst(str_replace('_', ' ', $message->category)),
                'status' => 'open',
                'priority' => $message->priority,
                'last_message_at' => now(),
            ]
        );

        PmConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'direction' => $direction,
            'channel' => $recipient->channel,
            'sender_type' => 'user',
            'sender_id' => $message->created_by_user_id,
            'to_address' => $recipient->to_address,
            'body' => $error ? ($message->body."\n\n[Delivery error] ".$error) : $message->body,
            'sent_at' => now(),
            'meta' => [
                'recipient_status' => $recipient->status,
            ],
        ]);

        $conversation->update(['last_message_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function renderTemplate(string $text, array $variables): string
    {
        if ($text === '' || $variables === []) {
            return $text;
        }

        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }
}
