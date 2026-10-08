<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Services\Property\BankCollections\CoopIpnNotification;
use App\Services\Property\PropertyBankTransactionIngestService;
use App\Support\Property\BankIntegrationConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Co-operative Bank IPN (push notification) receiver.
 *
 * The bank posts each credit applied to the collection account. This URL is what
 * we send them to register. It acknowledges in their message envelope, then
 * settles or parks the credit through the existing collection matcher.
 */
class CoopIpnWebhookController extends Controller
{
    public function handle(Request $request, PropertyBankTransactionIngestService $ingest): JsonResponse
    {
        return $request->isMethod('post')
            ? $this->store($request, $ingest)
            : $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return $this->reply('401', 'Unauthorized notification source', 401);
        }

        return response()->json([
            'ok' => true,
            'service' => 'Co-operative Bank IPN',
            'method' => 'POST',
            'content_type' => 'application/json',
            'message' => 'Notification endpoint is ready. POST each IPN payload to this URL.',
        ]);
    }

    public function store(Request $request, PropertyBankTransactionIngestService $ingest): JsonResponse
    {
        $payload = $this->payload($request);
        if (! $this->authorized($request)) {
            Log::warning('Co-op IPN rejected', [
                'ip' => $request->ip(),
            ]);

            return $this->reply('401', 'Unauthorized notification source', 401);
        }

        $notice = CoopIpnNotification::normalize($payload);
        Log::info('Co-op IPN received', [
            'ip' => $request->ip(),
            'transaction_id' => $notice['transaction_id'],
            'event_type' => $notice['event_type'],
            'amount' => $notice['amount'],
            'bank_account' => $notice['bank_account_number'],
            'payer_reference' => $notice['payer_reference'],
        ]);

        if (! $notice['is_credit']) {
            return $this->reply('200', 'Successfully received data');
        }

        if ($notice['transaction_id'] === '' || $notice['amount'] <= 0) {
            return $this->reply('400', 'TransactionId and Amount are required', 400);
        }

        try {
            $result = $ingest->ingest('coop', $notice['ingest']);
        } catch (\Throwable $e) {
            Log::error('Co-op IPN ingest failed', [
                'transaction_id' => $notice['transaction_id'],
                'error' => $e->getMessage(),
            ]);

            return $this->reply('500', 'Notification could not be stored', 500);
        }

        if (! ($result['ok'] ?? false)) {
            Log::warning('Co-op IPN not stored', [
                'transaction_id' => $notice['transaction_id'],
                'message' => $result['message'] ?? '',
            ]);

            return $this->reply('500', 'Notification could not be stored', 500);
        }

        return $this->reply('200', 'Successfully received data');
    }

    private function reply(string $code, string $message, int $status = 200): JsonResponse
    {
        return response()->json(CoopIpnNotification::acknowledgement($code, $message), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->all();
        if ($payload !== []) {
            return $payload;
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function authorized(Request $request): bool
    {
        $config = BankIntegrationConfig::resolve('coop');
        $secret = trim((string) ($config['webhook_secret'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = trim((string) ($config['password'] ?? ''));
        $expectsAuth = $secret !== '' || ($username !== '' && $password !== '');
        if ($expectsAuth) {
            $token = trim((string) ($request->bearerToken() ?: $request->header('X-Coop-Ipn-Secret') ?: ''));
            if ($secret !== '' && $token !== '' && hash_equals($secret, $token)) {
                return true;
            }

            $basicUser = (string) ($request->getUser() ?? '');
            $basicPass = (string) ($request->getPassword() ?? '');
            if ($username !== '' && $password !== '' && hash_equals($username, $basicUser) && hash_equals($password, $basicPass)) {
                return true;
            }
            if ($secret !== '' && $basicPass !== '' && hash_equals($secret, $basicPass)) {
                return true;
            }

            return false;
        }

        $allowed = config('property_banks.providers.coop.ipn_allowed_ips', []);
        if (is_string($allowed)) {
            $allowed = array_filter(array_map('trim', explode(',', $allowed)));
        }
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        return in_array((string) $request->ip(), $allowed, true);
    }
}
