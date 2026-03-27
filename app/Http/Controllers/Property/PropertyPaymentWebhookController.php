<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmSmsIngest;
use App\Models\PmTenant;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyPaymentWebhookController extends Controller
{
    public function smsIngest(Request $request, MpesaDarajaService $daraja): JsonResponse
    {
        $secret = (string) config('services.property_sms_ingest.secret', '');
        $providedSecret = (string) $request->header('X-Property-Sms-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:32'],
            'source_device' => ['nullable', 'string', 'max:128'],
            'provider_txn_code' => ['required', 'string', 'max:64'],
            'payer_phone' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'raw_message' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $normalizedPhone = isset($data['payer_phone']) && trim((string) $data['payer_phone']) !== ''
            ? $daraja->normalizeMsisdn((string) $data['payer_phone'])
            : null;

        $ingest = PmSmsIngest::query()->firstOrCreate(
            ['provider_txn_code' => (string) $data['provider_txn_code']],
            [
                'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
                'source_device' => $data['source_device'] ?? null,
                'payer_phone' => $normalizedPhone,
                'amount' => (float) $data['amount'],
                'paid_at' => $data['paid_at'] ?? now(),
                'raw_message' => $data['raw_message'] ?? null,
                'payload' => $data['payload'] ?? null,
                'match_status' => 'unmatched',
            ]
        );

        // Idempotency: if this transaction was already linked to a payment, do nothing.
        if ($ingest->pm_payment_id) {
            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => $ingest->match_status,
                'payment_id' => $ingest->pm_payment_id,
                'message' => 'Transaction already processed.',
            ]);
        }

        $tenant = $normalizedPhone ? $this->findTenantByPhone($normalizedPhone) : null;

        if (! $tenant) {
            $ingest->update([
                'match_status' => 'unmatched',
                'match_note' => 'No tenant found by payer phone.',
            ]);

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'unmatched',
            ]);
        }

        $existingPayment = PmPayment::query()
            ->where('external_ref', (string) $data['provider_txn_code'])
            ->first();

        if ($existingPayment) {
            $ingest->update([
                'matched_tenant_id' => $tenant->id,
                'pm_payment_id' => $existingPayment->id,
                'match_status' => 'duplicate',
                'match_note' => 'Transaction code already exists in pm_payments.',
            ]);

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'duplicate',
                'payment_id' => $existingPayment->id,
            ]);
        }

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa_sms_ingest',
            'amount' => (float) $data['amount'],
            'external_ref' => (string) $data['provider_txn_code'],
            'paid_at' => $data['paid_at'] ?? now(),
            'status' => PmPayment::STATUS_COMPLETED,
            'meta' => [
                'source' => 'sms_ingest',
                'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
                'source_device' => $data['source_device'] ?? null,
                'payer_phone' => $normalizedPhone,
                'raw_message' => $data['raw_message'] ?? null,
                'payload' => $data['payload'] ?? null,
            ],
        ]);

        app(PropertyPaymentSettlementService::class)->complete(
            $payment,
            (string) $data['provider_txn_code'],
            $data['paid_at'] ?? now(),
            'Payment ingested via SMS bridge.',
            'sms_ingest',
            (float) $data['amount'],
        );

        $ingest->update([
            'matched_tenant_id' => $tenant->id,
            'pm_payment_id' => $payment->id,
            'match_status' => 'matched',
            'match_note' => 'Matched by payer phone and posted automatically.',
        ]);

        return response()->json([
            'ok' => true,
            'ingest_id' => $ingest->id,
            'status' => 'matched',
            'payment_id' => $payment->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function findTenantByPhone(string $normalizedPhone): ?PmTenant
    {
        $local = str_starts_with($normalizedPhone, '254') ? '0'.substr($normalizedPhone, 3) : $normalizedPhone;
        $compact = ltrim($normalizedPhone, '+');

        return PmTenant::query()
            ->whereIn('phone', [$normalizedPhone, $compact, $local])
            ->first();
    }

    public function stkCallback(Request $request): JsonResponse
    {
        $secret = (string) config('services.property_webhooks.secret', '');
        $providedSecret = (string) $request->header('X-Property-Webhook-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:pm_payments,id'],
            'status' => ['required', 'in:success,failed'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = app(PropertyPaymentSettlementService::class)->settlePending(
            (int) $data['payment_id'],
            (string) $data['status'],
            $data['external_ref'] ?? null,
            $data['paid_at'] ?? null,
            $data['message'] ?? null,
            'stk',
            null,
        );

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }

    public function bankCallback(Request $request, string $provider): JsonResponse
    {
        $providerConfig = (array) config('services.property_banks.providers.'.$provider, []);
        $secret = (string) ($providerConfig['webhook_secret'] ?? '');
        $providedSecret = (string) $request->header('X-Property-Bank-Webhook-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:pm_payments,id'],
            'status' => ['required', 'in:success,failed'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = app(PropertyPaymentSettlementService::class)->settlePending(
            (int) $data['payment_id'],
            (string) $data['status'],
            $data['external_ref'] ?? null,
            $data['paid_at'] ?? null,
            $data['message'] ?? null,
            $provider,
            null,
        );

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }
}
