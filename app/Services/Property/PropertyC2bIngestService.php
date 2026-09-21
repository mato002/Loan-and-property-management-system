<?php

namespace App\Services\Property;

use App\Models\MpesaPlatformTransaction;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\PaymentMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class PropertyC2bIngestService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
        private readonly PaymentMatchingService $matcher,
        private readonly PropertyPaymentSettlementService $settler,
    ) {}

    /**
     * Try to post a Daraja C2B confirmation as a property tenant payment.
     *
     * @param  array<string, mixed>  $payload
     * @return array{handled:bool, duplicate?:bool, pm_payment_id?:int|null, message:string}
     */
    public function handleConfirmation(array $payload): array
    {
        $transId = trim((string) ($payload['TransID'] ?? $payload['TransactionID'] ?? ''));
        $amount = (float) ($payload['TransAmount'] ?? $payload['Amount'] ?? 0);
        $msisdn = $this->daraja->normalizeMsisdn((string) ($payload['MSISDN'] ?? ''));
        $billRef = trim((string) ($payload['BillRefNumber'] ?? $payload['AccountReference'] ?? ''));
        $txnTimeRaw = (string) ($payload['TransTime'] ?? '');
        $txnAt = $this->parseTransTime($txnTimeRaw) ?? now();

        if ($transId === '' || $amount <= 0) {
            return ['handled' => false, 'message' => 'Missing TransID or amount.'];
        }

        if (PmPayment::query()->where('external_ref', $transId)->exists()) {
            $this->upsertPlatformTx($transId, $amount, $msisdn, $billRef, $payload, null, 'completed');

            return [
                'handled' => true,
                'duplicate' => true,
                'pm_payment_id' => null,
                'message' => 'Property payment already exists for this receipt.',
            ];
        }

        $tenant = $this->findTenant($billRef, $msisdn !== '' ? $msisdn : null);

        try {
            if ($tenant) {
                $payment = DB::transaction(function () use ($tenant, $transId, $amount, $msisdn, $billRef, $payload, $txnAt) {
                    $payment = PmPayment::query()->create([
                        'pm_tenant_id' => $tenant->id,
                        'channel' => 'mpesa_c2b',
                        'amount' => $amount,
                        'external_ref' => $transId,
                        'paid_at' => $txnAt,
                        'status' => PmPayment::STATUS_COMPLETED,
                        'meta' => [
                            'source' => 'daraja_c2b',
                            'bill_ref' => $billRef,
                            'payer_phone' => $msisdn !== '' ? $msisdn : null,
                            'raw' => $payload,
                        ],
                    ]);

                    $this->settler->complete(
                        $payment,
                        $transId,
                        $txnAt,
                        'Payment received via Daraja C2B confirmation.',
                        'daraja_c2b',
                        $amount,
                    );

                    $this->upsertPlatformTx($transId, $amount, $msisdn, $billRef, $payload, (int) $payment->id, 'completed');

                    return $payment->fresh();
                });

                return [
                    'handled' => true,
                    'duplicate' => false,
                    'pm_payment_id' => (int) $payment->id,
                    'message' => 'Matched tenant and posted property payment.',
                ];
            }

            // No tenant match — let loan C2B handler (or equity unmatched via loan path) take over.
            return [
                'handled' => false,
                'duplicate' => false,
                'pm_payment_id' => null,
                'message' => 'No tenant match for C2B confirmation.',
            ];
        } catch (Throwable $e) {
            Log::error('Property C2B confirmation failed', [
                'trans_id' => $transId,
                'error' => $e->getMessage(),
            ]);

            return ['handled' => false, 'message' => $e->getMessage()];
        }
    }

    private function findTenant(string $billRef, ?string $msisdn): ?PmTenant
    {
        if ($billRef !== '' && Schema::hasColumn('pm_tenants', 'account_number')) {
            $byAccount = PmTenant::query()
                ->where('account_number', $billRef)
                ->orWhere('account_number', 'like', '%'.$billRef.'%')
                ->orderByDesc('id')
                ->first();
            if ($byAccount) {
                return $byAccount;
            }
        }

        $tx = [
            'transaction_id' => 'c2b-probe',
            'amount' => 0,
            'account_number' => $billRef,
            'reference' => $billRef,
            'phone' => (string) ($msisdn ?? ''),
            'transaction_date' => now(),
            'raw_payload' => [],
        ];

        $match = $this->matcher->match($tx, null);
        $tenantId = (int) ($match['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            return PmTenant::query()->find($tenantId);
        }

        if ($msisdn) {
            $variants = $this->phoneVariants($msisdn);
            return PmTenant::query()
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('phone', $v);
                    }
                })
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $normalized): array
    {
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        $variants = array_filter([
            $digits,
            Str::startsWith($digits, '254') ? '0'.substr($digits, 3) : null,
            Str::startsWith($digits, '254') ? substr($digits, 3) : null,
            Str::startsWith($digits, '0') ? '254'.substr($digits, 1) : null,
        ]);

        return array_values(array_unique($variants));
    }

    private function parseTransTime(string $raw): ?\Carbon\Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{14}$/', $raw)) {
                return \Carbon\Carbon::createFromFormat('YmdHis', $raw);
            }

            return \Carbon\Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertPlatformTx(
        string $transId,
        float $amount,
        string $msisdn,
        string $billRef,
        array $payload,
        ?int $pmPaymentId,
        string $status,
    ): void {
        $existing = MpesaPlatformTransaction::query()
            ->where('channel', 'c2b')
            ->where(function ($q) use ($transId) {
                $q->where('transaction_id', $transId)->orWhere('reference', $transId);
            })
            ->orderByDesc('id')
            ->first();

        $meta = is_array($existing?->meta) ? $existing->meta : [];
        $meta['module'] = 'property';
        $meta['daraja_c2b'] = [
            'received_at' => now()->toIso8601String(),
            'raw' => $payload,
            'bill_ref' => $billRef,
            'msisdn' => $msisdn,
        ];
        if ($pmPaymentId) {
            $meta['pm_payment_id'] = $pmPaymentId;
        }

        $data = [
            'reference' => $transId,
            'amount' => $amount,
            'channel' => 'c2b',
            'status' => $status,
            'notes' => $billRef !== '' ? 'Property BillRef: '.$billRef : 'Property Daraja C2B',
            'transaction_id' => $transId,
            'result_code' => 0,
            'result_desc' => 'Completed',
            'meta' => $meta,
        ];

        if ($existing) {
            $existing->update($data);
        } else {
            MpesaPlatformTransaction::query()->create($data);
        }
    }
}
