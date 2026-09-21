<?php

namespace App\Services\Property;

use App\Models\AccountingPayrollLine;
use App\Models\AccountingPayrollPeriod;
use App\Models\MpesaPlatformTransaction;
use App\Models\PmAccountingEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmMaintenanceJob;
use App\Models\PmVendor;
use App\Models\User;
use App\Services\Integrations\MpesaDarajaService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PropertyB2cPayoutService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
        private readonly LandlordSettlementService $landlordSettlements,
    ) {}

    /**
     * @return array{ok:bool, message:string}
     */
    public function initiateLandlordPayout(PmLandlordPayout $payout, string $phone, User $actor): array
    {
        if (! in_array($payout->status, ['draft', 'approved'], true)) {
            return ['ok' => false, 'message' => 'Only draft or approved landlord payouts can be sent via M-Pesa.'];
        }
        if (in_array((string) ($payout->payout_status ?? ''), ['pending', 'queued'], true)) {
            return ['ok' => false, 'message' => 'A B2C payout is already in progress for this batch.'];
        }

        $msisdn = $this->daraja->normalizeMsisdn($phone);
        if ($msisdn === '') {
            return ['ok' => false, 'message' => 'Invalid M-Pesa phone number.'];
        }
        if (! $this->daraja->isB2cConfigured()) {
            return ['ok' => false, 'message' => 'Daraja B2C is not configured (MPESA_B2C_*).'];
        }

        if ($payout->status === 'draft') {
            $this->landlordSettlements->approvePayout($payout, $actor);
            $payout->refresh();
        }

        return $this->sendB2c(
            amount: (float) $payout->total_amount,
            phone: $msisdn,
            remarks: 'Landlord payout #'.$payout->id,
            occasion: 'landlord_payout_'.$payout->id,
            meta: [
                'purpose' => 'landlord_payout',
                'pm_landlord_payout_id' => $payout->id,
                'requested_by' => $actor->id,
            ],
            onInitiated: function (array $response, array $body, string $conversationId, string $originatorConversationId, $resultCode, string $resultDesc) use ($payout, $msisdn) {
                $payout->update([
                    'payout_provider' => 'mpesa',
                    'payout_phone' => $msisdn,
                    'payout_status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
                    'payout_conversation_id' => $conversationId !== '' ? $conversationId : null,
                    'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
                    'payout_result_desc' => $resultDesc !== '' ? $resultDesc : null,
                    'payout_requested_at' => now(),
                    'payout_meta' => [
                        'initiation' => $response,
                        'body' => $body,
                    ],
                ]);
            }
        );
    }

    /**
     * @return array{ok:bool, message:string, amount?:float}
     */
    public function initiateVendorOutstanding(PmVendor $vendor, string $phone, User $actor, ?string $note = null): array
    {
        $eligible = $this->eligibleVendorJobs($vendor);
        if ($eligible->isEmpty()) {
            return ['ok' => false, 'message' => 'No outstanding allocated vendor jobs to pay.'];
        }

        $amount = round((float) $eligible->sum(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0)), 2);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Outstanding amount is too small for B2C.'];
        }

        $msisdn = $this->daraja->normalizeMsisdn($phone);
        if ($msisdn === '') {
            return ['ok' => false, 'message' => 'Invalid vendor M-Pesa phone number.'];
        }
        if (! $this->daraja->isB2cConfigured()) {
            return ['ok' => false, 'message' => 'Daraja B2C is not configured (MPESA_B2C_*).'];
        }

        $jobIds = $eligible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return $this->sendB2c(
            amount: $amount,
            phone: $msisdn,
            remarks: 'Vendor payout '.$vendor->name,
            occasion: 'vendor_'.$vendor->id.'_'.now()->format('YmdHis'),
            meta: [
                'purpose' => 'vendor_payment',
                'pm_vendor_id' => $vendor->id,
                'job_ids' => $jobIds,
                'payment_note' => $note,
                'requested_by' => $actor->id,
            ],
            onInitiated: null
        ) + ['amount' => $amount];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function initiatePayrollLine(AccountingPayrollPeriod $period, AccountingPayrollLine $line, string $phone, User $actor): array
    {
        if ((string) $period->status !== AccountingPayrollPeriod::STATUS_POSTED) {
            return ['ok' => false, 'message' => 'Only posted payroll runs can be paid via M-Pesa.'];
        }
        if ((string) ($line->payment_status ?? '') === 'paid') {
            return ['ok' => false, 'message' => 'This payslip is already marked paid.'];
        }
        if (in_array((string) ($line->payout_status ?? ''), ['pending', 'queued'], true)) {
            return ['ok' => false, 'message' => 'A B2C payout is already in progress for this payslip.'];
        }

        $amount = round((float) $line->net_pay, 2);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Net pay must be at least 1.'];
        }

        $msisdn = $this->daraja->normalizeMsisdn($phone);
        if ($msisdn === '') {
            return ['ok' => false, 'message' => 'Invalid employee M-Pesa phone number.'];
        }
        if (! $this->daraja->isB2cConfigured()) {
            return ['ok' => false, 'message' => 'Daraja B2C is not configured (MPESA_B2C_*).'];
        }

        return $this->sendB2c(
            amount: $amount,
            phone: $msisdn,
            remarks: 'Payroll '.$line->payslip_number,
            occasion: 'payroll_line_'.$line->id,
            meta: [
                'purpose' => 'payroll_line',
                'accounting_payroll_line_id' => $line->id,
                'accounting_payroll_period_id' => $period->id,
                'requested_by' => $actor->id,
            ],
            onInitiated: function (array $response, array $body, string $conversationId, string $originatorConversationId, $resultCode, string $resultDesc) use ($line, $msisdn) {
                $line->forceFill([
                    'payout_provider' => 'mpesa',
                    'payout_phone' => $msisdn,
                    'payout_status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
                    'payout_conversation_id' => $conversationId !== '' ? $conversationId : null,
                    'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
                    'payout_meta' => [
                        'initiation' => $response,
                        'body' => $body,
                        'result_desc' => $resultDesc,
                    ],
                ])->save();
            }
        );
    }

    /**
     * Apply B2C callback to property payouts (landlord / vendor / payroll).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $resultMap
     */
    public function applyB2cCallback(array $payload, array $resultMap, string $status): bool
    {
        $result = (array) ($payload['Result'] ?? []);
        $conversationId = (string) ($result['ConversationID'] ?? '');
        $originatorConversationId = (string) ($result['OriginatorConversationID'] ?? '');
        $transactionId = (string) ($result['TransactionID'] ?? '');
        $resultDesc = (string) ($result['ResultDesc'] ?? '');

        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'b2c')
            ->where(function ($q) use ($conversationId, $originatorConversationId, $transactionId) {
                $matched = false;
                if ($conversationId !== '') {
                    $q->orWhere('conversation_id', $conversationId);
                    $matched = true;
                }
                if ($originatorConversationId !== '') {
                    $q->orWhere('originator_conversation_id', $originatorConversationId);
                    $matched = true;
                }
                if ($transactionId !== '') {
                    $q->orWhere('transaction_id', $transactionId);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('id')
            ->first();

        $purpose = (string) data_get($tx?->meta, 'purpose', '');
        if ($purpose === '' || ! in_array($purpose, ['landlord_payout', 'vendor_payment', 'payroll_line'], true)) {
            // Fallback: landlord payout by conversation id on the payout row
            $payout = PmLandlordPayout::query()
                ->where(function ($q) use ($conversationId, $originatorConversationId) {
                    if ($conversationId !== '') {
                        $q->orWhere('payout_conversation_id', $conversationId);
                    }
                    if ($originatorConversationId !== '') {
                        $q->orWhere('payout_originator_conversation_id', $originatorConversationId);
                    }
                    if ($conversationId === '' && $originatorConversationId === '') {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->orderByDesc('id')
                ->first();
            if ($payout) {
                $purpose = 'landlord_payout';
                $txMeta = ['pm_landlord_payout_id' => $payout->id, 'purpose' => 'landlord_payout'];
            } else {
                return false;
            }
        } else {
            $txMeta = is_array($tx?->meta) ? $tx->meta : [];
        }

        try {
            if ($purpose === 'landlord_payout') {
                $this->completeLandlordPayout($txMeta, $status, $conversationId, $originatorConversationId, $transactionId, $resultDesc, $payload, $resultMap);

                return true;
            }
            if ($purpose === 'vendor_payment') {
                $this->completeVendorPayment($txMeta, $status, $transactionId, $resultDesc);

                return true;
            }
            if ($purpose === 'payroll_line') {
                $this->completePayrollLine($txMeta, $status, $conversationId, $originatorConversationId, $transactionId, $resultDesc);

                return true;
            }
        } catch (Throwable $e) {
            Log::error('Property B2C callback apply failed', [
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmMaintenanceJob>
     */
    public function eligibleVendorJobs(PmVendor $vendor)
    {
        $jobs = PmMaintenanceJob::query()
            ->with('request.unit')
            ->where('pm_vendor_id', $vendor->id)
            ->where('status', 'done')
            ->whereNotNull('quote_amount')
            ->get()
            ->filter(fn (PmMaintenanceJob $j) => (float) ($j->quote_amount ?? 0) > 0);

        $references = $jobs->map(fn (PmMaintenanceJob $j) => 'MNT-'.$j->id)->values();
        $allocatedRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_expense')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();
        $paidRefs = PmAccountingEntry::query()
            ->where('source_key', 'maintenance_payment')
            ->where('category', PmAccountingEntry::CATEGORY_LIABILITY)
            ->where('entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->flip();

        return $jobs->filter(function (PmMaintenanceJob $j) use ($allocatedRefs, $paidRefs) {
            $ref = 'MNT-'.$j->id;

            return isset($allocatedRefs[$ref]) && ! isset($paidRefs[$ref]);
        })->values();
    }

    /**
     * @param  callable|null  $onInitiated
     * @param  array<string, mixed>  $meta
     * @return array{ok:bool, message:string}
     */
    private function sendB2c(
        float $amount,
        string $phone,
        string $remarks,
        string $occasion,
        array $meta,
        $onInitiated,
    ): array {
        $response = $this->daraja->b2cPayout([
            'Amount' => (int) round($amount, 0),
            'PartyB' => $phone,
            'Remarks' => $remarks,
            'Occasion' => $occasion,
        ]);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $conversationId = (string) ($body['ConversationID'] ?? '');
        $originatorConversationId = (string) ($body['OriginatorConversationID'] ?? '');
        $resultCode = Arr::get($body, 'ResponseCode');
        $resultDesc = (string) ($body['ResponseDescription'] ?? $response['message'] ?? '');

        DB::transaction(function () use (
            $amount,
            $phone,
            $remarks,
            $response,
            $body,
            $conversationId,
            $originatorConversationId,
            $resultCode,
            $resultDesc,
            $meta,
            $onInitiated,
        ): void {
            MpesaPlatformTransaction::query()->create([
                'reference' => $remarks,
                'amount' => $amount,
                'channel' => 'b2c',
                'status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
                'notes' => $resultDesc,
                'conversation_id' => $conversationId !== '' ? $conversationId : null,
                'originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
                'result_code' => is_numeric($resultCode) ? (int) $resultCode : null,
                'result_desc' => $resultDesc !== '' ? $resultDesc : null,
                'meta' => array_merge($meta, [
                    'module' => 'property',
                    'phone' => $phone,
                    'initiation_body' => $body,
                ]),
            ]);

            if (is_callable($onInitiated)) {
                $onInitiated($response, $body, $conversationId, $originatorConversationId, $resultCode, $resultDesc);
            }
        });

        return [
            'ok' => (bool) ($response['ok'] ?? false),
            'message' => (string) ($response['message'] ?? ($response['ok'] ? 'B2C submitted.' : 'B2C failed.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $txMeta
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $resultMap
     */
    private function completeLandlordPayout(
        array $txMeta,
        string $status,
        string $conversationId,
        string $originatorConversationId,
        string $transactionId,
        string $resultDesc,
        array $payload,
        array $resultMap,
    ): void {
        $id = (int) ($txMeta['pm_landlord_payout_id'] ?? 0);
        $payout = $id > 0 ? PmLandlordPayout::query()->find($id) : null;
        if (! $payout) {
            return;
        }

        $meta = is_array($payout->payout_meta) ? $payout->payout_meta : [];
        $meta['callback'] = [
            'received_at' => now()->toIso8601String(),
            'payload' => $payload,
            'params' => $resultMap,
        ];

        $payout->update([
            'payout_status' => $status,
            'payout_conversation_id' => $conversationId !== '' ? $conversationId : $payout->payout_conversation_id,
            'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : $payout->payout_originator_conversation_id,
            'payout_transaction_id' => $transactionId !== '' ? $transactionId : $payout->payout_transaction_id,
            'payout_result_desc' => $resultDesc !== '' ? $resultDesc : $payout->payout_result_desc,
            'payout_meta' => $meta,
        ]);

        if ($status === 'completed' && $payout->status !== 'paid') {
            $actorId = (int) ($txMeta['requested_by'] ?? $payout->approved_by ?? $payout->created_by ?? 0);
            $actor = $actorId > 0 ? User::query()->find($actorId) : User::query()->find((int) $payout->approved_by);
            if (! $actor) {
                $actor = User::query()->orderBy('id')->first();
            }
            if ($actor) {
                $this->landlordSettlements->markPayoutPaid($payout->fresh(), $actor);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $txMeta
     */
    private function completeVendorPayment(array $txMeta, string $status, string $transactionId, string $resultDesc): void
    {
        if ($status !== 'completed') {
            return;
        }

        $vendorId = (int) ($txMeta['pm_vendor_id'] ?? 0);
        $vendor = $vendorId > 0 ? PmVendor::query()->find($vendorId) : null;
        if (! $vendor) {
            return;
        }

        $jobIds = array_map('intval', (array) ($txMeta['job_ids'] ?? []));
        $note = trim((string) ($txMeta['payment_note'] ?? ''));
        $description = 'Vendor payment via M-Pesa B2C'.($transactionId !== '' ? ' ('.$transactionId.')' : '')
            .($note !== '' ? ' - '.$note : '');
        $actorId = (int) ($txMeta['requested_by'] ?? 0);
        $entryDate = now()->toDateString();

        $jobs = PmMaintenanceJob::query()
            ->with('request.unit')
            ->whereIn('id', $jobIds)
            ->get();

        DB::transaction(function () use ($jobs, $entryDate, $actorId, $description, $transactionId) {
            foreach ($jobs as $job) {
                $amount = (float) ($job->quote_amount ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $reference = 'MNT-'.$job->id;
                if (PmAccountingEntry::query()
                    ->where('source_key', 'maintenance_payment')
                    ->where('reference', $reference)
                    ->exists()) {
                    continue;
                }
                $propertyId = optional(optional($job->request)->unit)->property_id;

                PmAccountingEntry::query()->create([
                    'entry_date' => $entryDate,
                    'property_id' => $propertyId,
                    'recorded_by_user_id' => $actorId ?: null,
                    'account_name' => 'Accounts Payable',
                    'category' => PmAccountingEntry::CATEGORY_LIABILITY,
                    'entry_type' => PmAccountingEntry::TYPE_DEBIT,
                    'amount' => $amount,
                    'reference' => $reference,
                    'description' => $description,
                    'source_key' => 'maintenance_payment',
                ]);
                PmAccountingEntry::query()->create([
                    'entry_date' => $entryDate,
                    'property_id' => $propertyId,
                    'recorded_by_user_id' => $actorId ?: null,
                    'account_name' => 'Cash / Bank',
                    'category' => PmAccountingEntry::CATEGORY_ASSET,
                    'entry_type' => PmAccountingEntry::TYPE_CREDIT,
                    'amount' => $amount,
                    'reference' => $reference,
                    'description' => $description,
                    'source_key' => 'maintenance_payment',
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $txMeta
     */
    private function completePayrollLine(
        array $txMeta,
        string $status,
        string $conversationId,
        string $originatorConversationId,
        string $transactionId,
        string $resultDesc,
    ): void {
        $lineId = (int) ($txMeta['accounting_payroll_line_id'] ?? 0);
        $line = $lineId > 0 ? AccountingPayrollLine::query()->find($lineId) : null;
        if (! $line) {
            return;
        }

        $meta = is_array($line->payout_meta) ? $line->payout_meta : [];
        $meta['callback'] = [
            'received_at' => now()->toIso8601String(),
            'result_desc' => $resultDesc,
            'transaction_id' => $transactionId,
        ];

        $update = [
            'payout_status' => $status,
            'payout_conversation_id' => $conversationId !== '' ? $conversationId : $line->payout_conversation_id,
            'payout_originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : $line->payout_originator_conversation_id,
            'payout_meta' => $meta,
        ];

        if ($status === 'completed') {
            $update['payment_status'] = 'paid';
            $update['payment_date'] = now()->toDateString();
            $update['payment_reference'] = $transactionId !== '' ? $transactionId : ($line->payment_reference ?: 'B2C');
        }

        $line->forceFill($update)->save();
    }
}
