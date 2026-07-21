<?php

namespace App\Services\Property;

/**
 * Turns raw SMS provider / API errors into messages property staff can act on.
 */
final class SmsDeliveryErrorPresenter
{
    public function forAgent(?string $raw, ?string $invoiceHint = null): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 'This SMS could not be sent. Try again in a few minutes.';
        }

        $lower = strtolower($raw);
        $payload = $this->extractJsonPayload($raw);

        if ($this->isRateLimit($lower, $payload)) {
            $retry = (int) ($payload['retry_after'] ?? $payload['retryAfter'] ?? 60);

            return 'The SMS service is busy (too many requests at once). '
                .'Wait about '.max(30, $retry).' seconds, then try again. '
                .'For large batches, resend about 40–50 messages per minute.';
        }

        if ($this->matchesAny($lower, ['insufficient provider balance', 'insufficient local sms wallet'])) {
            return $this->simplifyBalanceMessage($raw);
        }

        if ($this->matchesAny($lower, ['provider balance endpoint is not configured', 'bulk sms provider is not configured', 'bulksms_'])) {
            return 'SMS sending is not fully configured. Please contact your system administrator.';
        }

        if ($this->matchesAny($lower, ['curl error', 'connection failed', 'could not resolve host', 'timed out'])) {
            return 'Could not connect to the SMS service. Check your internet connection and try again.';
        }

        if ($this->matchesAny($lower, ['invalid phone', 'invalid recipient', 'add at least one valid phone'])) {
            return 'The phone number on this message is invalid or missing. Check the tenant\'s contact number.';
        }

        if ($this->matchesAny($lower, ['already logged', 'already sent', 'duplicate charges'])) {
            return 'This invoice was already sent successfully to this tenant. No resend is needed.';
        }

        if ($this->matchesAny($lower, ['could not rebuild', 'invoice missing'])) {
            return 'This rent reminder could not be rebuilt. Open Templates and confirm the rent reminder SMS template is set up.';
        }

        $providerMessage = trim((string) ($payload['message'] ?? $payload['error'] ?? ''));
        if ($providerMessage !== '' && ! $this->looksLikeJson($providerMessage)) {
            return $this->truncate($providerMessage, 220);
        }

        $prefix = $invoiceHint !== '' ? 'Message for '.$invoiceHint.': ' : '';

        return $prefix.'This SMS could not be sent. Try again in a few minutes, or contact support if it keeps failing.';
    }

    /**
     * @param  list<array{id?:int,error?:string}|string>  $errors
     */
    public function summarizeBulkFailures(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $grouped = [];
        foreach ($errors as $item) {
            if (is_array($item)) {
                $id = (int) ($item['id'] ?? 0);
                $message = trim((string) ($item['error'] ?? ''));
            } else {
                $line = trim((string) $item);
                $id = 0;
                $message = $line;
                if (preg_match('/^#(\d+):\s*(.+)$/s', $line, $matches)) {
                    $id = (int) $matches[1];
                    $message = trim($matches[2]);
                }
            }

            if ($message === '') {
                continue;
            }

            $friendly = $this->forAgent($message);
            $grouped[$friendly][] = $id > 0 ? $id : null;
        }

        if ($grouped === []) {
            return '';
        }

        $parts = [];
        foreach ($grouped as $friendly => $ids) {
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
            $count = count($ids);
            if ($count > 0 && $count <= 5) {
                $refs = implode(', ', array_map(static fn (int $id): string => '#'.$id, $ids));
                $parts[] = $count.' message(s) ('.$refs.'): '.$friendly;
            } elseif ($count > 5) {
                $parts[] = $count.' message(s): '.$friendly;
            } else {
                $parts[] = $friendly;
            }
        }

        return 'Some messages were not sent. '.implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJsonPayload(string $raw): array
    {
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isRateLimit(string $lower, array $payload): bool
    {
        if ($this->matchesAny($lower, ['429', 'rate_limit', 'too many requests'])) {
            return true;
        }

        $code = (int) ($payload['statusCode'] ?? $payload['status'] ?? 0);
        $name = strtolower((string) ($payload['name'] ?? $payload['errorCodeName'] ?? ''));

        return $code === 429 || str_contains($name, 'rate_limit');
    }

    private function simplifyBalanceMessage(string $raw): string
    {
        if (preg_match('/Need\s+([\d,.]+)\s+(\w+)\s+for\s+(\d+)\s+message/i', $raw, $matches)) {
            return sprintf(
                'Not enough SMS credit. You need %s %s to send %s message(s). Top up your SMS wallet and try again.',
                $matches[1],
                $matches[2],
                $matches[3]
            );
        }

        if (str_contains(strtolower($raw), 'could not verify provider balance')) {
            return 'Could not check SMS wallet balance right now. Wait a minute and try again.';
        }

        return 'Not enough SMS credit to send these messages. Top up your SMS wallet and try again.';
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeJson(string $value): bool
    {
        $value = trim($value);

        return str_starts_with($value, '{') || str_starts_with($value, '[');
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return rtrim(substr($value, 0, $max - 1)).'…';
    }
}
