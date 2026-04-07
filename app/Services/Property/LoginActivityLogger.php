<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use Illuminate\Support\Facades\Schema;

class LoginActivityLogger
{
    /**
     * @param array<string,mixed> $context
     */
    public function log(?int $userId, string $status, string $subject, ?string $email = null, array $context = []): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        try {
            $pairs = [];
            foreach ($context as $k => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $pairs[] = $k.': '.(is_scalar($v) ? (string) $v : json_encode($v));
            }
            if ($email) {
                array_unshift($pairs, 'email: '.$email);
            }

            PmMessageLog::query()->create([
                'user_id' => $userId,
                'channel' => 'system',
                'to_address' => $email ?: null,
                'subject' => '[LOGIN] '.$subject,
                'body' => $pairs === [] ? $subject : implode(' | ', $pairs),
                'delivery_status' => $status,
                'delivery_error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable) {
            // Do not block auth flow if logging fails.
        }
    }
}

