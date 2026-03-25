<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyPaymentWebhookController extends Controller
{
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
        );

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }
}
