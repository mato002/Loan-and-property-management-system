<?php

namespace App\Services\Property;

use App\Models\PmEzenReceiptRegister;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\User;
use App\Support\Property\PmPaymentPresentation;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EzenRentReceiptsImportService
{
    public function __construct(
        private readonly EzenRentReceiptListingParser $parser,
        private readonly PropertyPaymentSettlementService $payments,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     imported:int,
     *     skipped_existing:int,
     *     skipped_no_tenant:int,
     *     skipped_no_open_balance:int,
     *     skipped_zero_amount:int,
     *     enriched_existing:int,
     *     skipped_no_match:int,
     *     register_upserted:int,
     *     allocated:float,
     *     unallocated:float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        ?User $actor = null,
        bool $dryRun = false,
        bool $skipIfNoOpenBalance = true,
        ?string $propertyCodeFilter = null,
        ?int $limit = null,
        bool $enrichOnly = false,
        bool $registerOnly = false,
    ): array {
        $rows = $this->parser->parsePath($path);
        if ($propertyCodeFilter !== null && trim($propertyCodeFilter) !== '') {
            $filter = strtoupper(trim($propertyCodeFilter));
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => strtoupper((string) ($row['property_code'] ?? '')) === $filter,
            ));
        }
        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $summary = [
            'parsed' => count($rows),
            'imported' => 0,
            'skipped_existing' => 0,
            'skipped_no_tenant' => 0,
            'skipped_no_open_balance' => 0,
            'skipped_zero_amount' => 0,
            'enriched_existing' => 0,
            'skipped_no_match' => 0,
            'register_upserted' => 0,
            'allocated' => 0.0,
            'unallocated' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            try {
                $result = $this->importRow($row, $agentUserId, $actor, $dryRun, $skipIfNoOpenBalance, $rowNum, $enrichOnly, $registerOnly);
                $summary['imported'] += $result['imported'] ? 1 : 0;
                $summary['skipped_existing'] += $result['skipped_existing'] ? 1 : 0;
                $summary['skipped_no_tenant'] += $result['skipped_no_tenant'] ? 1 : 0;
                $summary['skipped_no_open_balance'] += $result['skipped_no_open_balance'] ? 1 : 0;
                $summary['skipped_zero_amount'] += $result['skipped_zero_amount'] ? 1 : 0;
                $summary['enriched_existing'] += $result['enriched_existing'] ? 1 : 0;
                $summary['skipped_no_match'] += $result['skipped_no_match'] ? 1 : 0;
                $summary['register_upserted'] += $result['register_upserted'] ? 1 : 0;
                $summary['allocated'] += $result['allocated'];
                $summary['unallocated'] += $result['unallocated'];
                $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
            } catch (RuntimeException $e) {
                $summary['errors'][] = 'Row '.$rowNum.' '.($row['ezen_receipt_no'] ?? '?').': '.$e->getMessage();
            } catch (QueryException $e) {
                if ($this->isDuplicatePayment($e, (string) ($row['ezen_receipt_no'] ?? ''))) {
                    $summary['skipped_existing']++;
                    continue;
                }
                $summary['errors'][] = 'Row '.$rowNum.' '.($row['ezen_receipt_no'] ?? '?').': '.$e->getMessage();
            }
        }

        $summary['allocated'] = round($summary['allocated'], 2);
        $summary['unallocated'] = round($summary['unallocated'], 2);

        return $summary;
    }

    /**
     * Push M-Pesa ref / payment method from the receipt register onto invoice-import payments.
     *
     * @return array{
     *     register_rows:int,
     *     payments_updated:int,
     *     register_rows_matched:int,
     *     skipped_no_tenant:int,
     *     skipped_no_payment:int,
     *     warnings:list<string>
     * }
     */
    public function syncPaymentsFromRegister(int $agentUserId, bool $dryRun = false): array
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            throw new RuntimeException('Receipt register table not found — run migrations first.');
        }

        $summary = [
            'register_rows' => 0,
            'payments_updated' => 0,
            'register_rows_matched' => 0,
            'group_matches' => 0,
            'fallback_matches' => 0,
            'skipped_no_tenant' => 0,
            'skipped_no_payment' => 0,
            'warnings' => [],
        ];

        $registers = PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->whereNotNull('ref_no')
            ->where('ref_no', '!=', '')
            ->orderBy('banking_date')
            ->orderBy('id')
            ->get();

        $summary['register_rows'] = $registers->count();

        foreach ($registers as $register) {
            if (! $this->registerShouldProcess($register)) {
                continue;
            }

            $tenant = $register->pm_tenant_id
                ? PmTenant::query()->withoutGlobalScopes()->find($register->pm_tenant_id)
                : null;
            if ($tenant === null && trim((string) ($register->tnt_account ?? '')) !== '') {
                $tenant = $this->resolveTenant((string) $register->tnt_account, $agentUserId);
            }
            if ($tenant === null) {
                $summary['skipped_no_tenant']++;
                continue;
            }

            $paidAt = Carbon::parse($register->banking_date ?? $register->txn_date ?? now());
            $targets = $this->findPaymentsForRegisterRow($register, $tenant);

            if ($targets->isEmpty()) {
                $summary['skipped_no_payment']++;
                continue;
            }

            $summary['register_rows_matched']++;

            if ($dryRun) {
                $summary['payments_updated'] += $targets->count();
                continue;
            }

            $phone = trim((string) ($register->phone ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($tenant->phone ?? $tenant->user?->phone ?? ''));
            }

            $this->applyEnrichmentToPayments(
                $targets,
                [(string) $register->ezen_receipt_no],
                [(string) $register->ref_no],
                $phone,
                (string) ($register->receipted_to ?? ''),
                (string) ($register->property_code ?? ''),
                (string) ($register->unit_label ?? ''),
            );

            $summary['payments_updated'] += $targets->count();

            $this->linkRegistersToPayments(collect([$register]), $tenant->id, $targets);
        }

        if (! $dryRun) {
            $groupSummary = $this->syncPaymentGroupsFromRegister($agentUserId);
            $summary['group_matches'] = $groupSummary['groups_matched'];
            $summary['payments_updated'] += $groupSummary['payments_updated'];

            $fallbackSummary = $this->syncUnmatchedPaymentsFromRegister($agentUserId);
            $summary['fallback_matches'] = $fallbackSummary['payments_updated'];
            $summary['payments_updated'] += $fallbackSummary['payments_updated'];

            $summary['payments_updated'] += $this->normalizeCashReceiptMetadata($agentUserId);
        }

        return $summary;
    }

    /**
     * @return array{groups_matched:int, payments_updated:int}
     */
    private function syncPaymentGroupsFromRegister(int $agentUserId): array
    {
        $summary = ['groups_matched' => 0, 'payments_updated' => 0];

        $registers = PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->whereNotNull('ref_no')
            ->where('ref_no', '!=', '')
            ->whereNull('pm_payment_id')
            ->whereNotNull('pm_tenant_id')
            ->get();

        $registerGroups = $registers->groupBy(
            fn (PmEzenReceiptRegister $register): string => $this->tenantUnitMonthKey(
                (int) $register->pm_tenant_id,
                (string) ($register->unit_label ?? ''),
                Carbon::parse($register->banking_date ?? $register->txn_date ?? now()),
            )
        );

        foreach ($registerGroups as $groupRegisters) {
            if ($groupRegisters->isEmpty()) {
                continue;
            }

            /** @var PmEzenReceiptRegister $sample */
            $sample = $groupRegisters->first();
            $tenant = PmTenant::query()->withoutGlobalScopes()->find($sample->pm_tenant_id);
            if ($tenant === null) {
                continue;
            }

            $paidAt = Carbon::parse($sample->banking_date ?? $sample->txn_date ?? now());
            $unitLabel = trim((string) ($sample->unit_label ?? ''));
            $payments = $this->findPaymentsForRegisterGroup($tenant, $paidAt, $unitLabel);
            if ($payments->isEmpty()) {
                continue;
            }

            $regSum = round((float) $groupRegisters->sum(fn (PmEzenReceiptRegister $register): float => (float) $register->amount), 2);
            $paySum = round((float) $payments->sum(fn (PmPayment $payment): float => (float) $payment->amount), 2);
            if (! $this->registerPaymentSumsMatch($regSum, $paySum, $groupRegisters->count(), $payments->count())) {
                continue;
            }

            $phone = trim((string) ($sample->phone ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($tenant->phone ?? $tenant->user?->phone ?? ''));
            }

            $this->applyEnrichmentToPayments(
                $payments,
                $groupRegisters->pluck('ezen_receipt_no')->map(fn ($no) => (string) $no)->all(),
                $groupRegisters->pluck('ref_no')->map(fn ($ref) => (string) $ref)->all(),
                $phone,
                (string) ($sample->receipted_to ?? ''),
                (string) ($sample->property_code ?? ''),
                $unitLabel,
            );

            $this->linkRegistersToPayments($groupRegisters, $tenant->id, $payments);

            $summary['groups_matched']++;
            $summary['payments_updated'] += $payments->count();
        }

        return $summary;
    }

    /**
     * @return array{payments_updated:int}
     */
    private function syncUnmatchedPaymentsFromRegister(int $agentUserId): array
    {
        $summary = ['payments_updated' => 0];

        $payments = PmPayment::query()
            ->withoutGlobalScopes()
            ->with(['allocations.invoice.unit', 'tenant'])
            ->where('channel', 'ezen_import')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PmPayment $payment): bool => $this->paymentNeedsReceiptEnrichment($payment));

        foreach ($payments as $payment) {
            $tenant = PmTenant::query()->withoutGlobalScopes()->find($payment->pm_tenant_id);
            if ($tenant === null) {
                continue;
            }

            $paidAt = Carbon::parse($payment->paid_at ?? now());
            $unitLabel = $this->paymentUnitLabel($payment);
            $amount = (float) $payment->amount;

            $candidates = PmEzenReceiptRegister::query()
                ->withoutGlobalScopes()
                ->where('agent_user_id', $agentUserId)
                ->where('pm_tenant_id', $tenant->id)
                ->whereNull('pm_payment_id')
                ->whereNotNull('ref_no')
                ->where('ref_no', '!=', '')
                ->get()
                ->filter(function (PmEzenReceiptRegister $register) use ($amount, $unitLabel, $paidAt): bool {
                    if (abs((float) $register->amount - $amount) > 0.02) {
                        return false;
                    }
                    if ($unitLabel !== '' && trim((string) ($register->unit_label ?? '')) !== '' && ! $this->paymentMatchesUnitLabel($unitLabel, (string) $register->unit_label)) {
                        return false;
                    }
                    $bankDate = Carbon::parse($register->banking_date ?? $register->txn_date ?? now());
                    $monthDiff = abs(($paidAt->year * 12 + $paidAt->month) - ($bankDate->year * 12 + $bankDate->month));

                    return $monthDiff <= 6;
                })
                ->values();

            if ($candidates->count() !== 1) {
                continue;
            }

            /** @var PmEzenReceiptRegister $register */
            $register = $candidates->first();
            $phone = trim((string) ($register->phone ?? ''));
            if ($phone === '') {
                $phone = trim((string) ($tenant->phone ?? $tenant->user?->phone ?? ''));
            }

            $targets = collect([$payment]);
            $this->applyEnrichmentToPayments(
                $targets,
                [(string) $register->ezen_receipt_no],
                [(string) $register->ref_no],
                $phone,
                (string) ($register->receipted_to ?? ''),
                (string) ($register->property_code ?? ''),
                (string) ($register->unit_label ?? ''),
            );
            $this->linkRegistersToPayments(collect([$register]), $tenant->id, $targets);
            $summary['payments_updated']++;
        }

        return $summary;
    }

    private function normalizeCashReceiptMetadata(int $agentUserId): int
    {
        $updated = 0;

        $registers = PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('ref_no', 'CASH')
            ->whereNotNull('pm_payment_id')
            ->get();

        foreach ($registers as $register) {
            $payment = PmPayment::query()->withoutGlobalScopes()->find($register->pm_payment_id);
            if ($payment === null) {
                continue;
            }

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $receiptedTo = strtoupper(trim((string) ($meta['receipted_to'] ?? '')));
            if ($receiptedTo === 'CASH') {
                continue;
            }

            $meta['receipted_to'] = 'CASH';
            $meta['ezen_receipt_no'] = (string) $register->ezen_receipt_no;
            $meta['ezen_receipt_nos'] = [(string) $register->ezen_receipt_no];
            $meta['enriched_from_receipt_import'] = true;
            $payment->update(['meta' => $meta]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     imported:bool,
     *     skipped_existing:bool,
     *     skipped_no_tenant:bool,
     *     skipped_no_open_balance:bool,
     *     skipped_zero_amount:bool,
     *     enriched_existing:bool,
     *     skipped_no_match:bool,
     *     register_upserted:bool,
     *     allocated:float,
     *     unallocated:float,
     *     warnings:list<string>
     * }
     */
    private function importRow(
        array $row,
        int $agentUserId,
        ?User $actor,
        bool $dryRun,
        bool $skipIfNoOpenBalance,
        int $rowNum,
        bool $enrichOnly = false,
        bool $registerOnly = false,
    ): array {
        $warnings = [];
        $receiptNo = strtoupper(trim((string) ($row['ezen_receipt_no'] ?? '')));
        $refNo = strtoupper(trim((string) ($row['ref_no'] ?? '')));
        $amount = round((float) ($row['amount'] ?? 0), 2);

        if ($receiptNo === '') {
            throw new RuntimeException('Missing EZEN receipt number.');
        }
        if ($amount <= 0.009) {
            return $this->skipResult('skipped_zero_amount', $warnings);
        }

        $tenant = $this->resolveTenant((string) ($row['tnt_account'] ?? ''), $agentUserId);
        $registerUpserted = $this->upsertReceiptRegister($row, $agentUserId, $tenant, $dryRun);

        if ($registerOnly) {
            return [
                'imported' => false,
                'skipped_existing' => false,
                'skipped_no_tenant' => false,
                'skipped_no_open_balance' => false,
                'skipped_zero_amount' => false,
                'enriched_existing' => false,
                'skipped_no_match' => false,
                'register_upserted' => $registerUpserted,
                'allocated' => 0.0,
                'unallocated' => 0.0,
                'warnings' => $warnings,
            ];
        }

        if ($this->findExistingPayment($receiptNo, $refNo) !== null) {
            return $this->skipResult('skipped_existing', $warnings, $registerUpserted);
        }

        if ($tenant === null) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': tenant '.($row['tnt_account'] ?? '?').' not found — skipped.';

            return $this->skipResult('skipped_no_tenant', $warnings, $registerUpserted);
        }

        if (! $this->namesLooselyMatch((string) ($row['tenant_name'] ?? ''), (string) $tenant->name)) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': register "'.($row['tenant_name'] ?? '')
                .'" vs system "'.$tenant->name.'" — applied to '.$tenant->account_number.'.';
        }

        $openBalance = $this->openInvoiceBalanceForTenant((int) $tenant->id);
        $paidAt = Carbon::parse((string) ($row['banking_date'] ?? $row['txn_date'] ?? now()->toDateString()))->startOfDay();
        $payerPhone = trim((string) ($row['phone'] ?? ''));
        if ($payerPhone === '') {
            $payerPhone = trim((string) ($tenant->phone ?? $tenant->user?->phone ?? ''));
        }
        $unitLabel = trim((string) ($row['unit_label'] ?? ''));
        $propertyCode = trim((string) ($row['property_code'] ?? ''));
        $receiptedTo = trim((string) ($row['receipted_to'] ?? ''));

        if ($enrichOnly || ($skipIfNoOpenBalance && $openBalance <= 0.009)) {
            $enriched = $this->tryEnrichExistingPayment(
                $tenant,
                $amount,
                $paidAt,
                $receiptNo,
                $refNo,
                $payerPhone,
                $receiptedTo,
                $propertyCode,
                $unitLabel,
                $dryRun,
            );
            if ($enriched) {
                if (! $dryRun) {
                    $this->linkRegisterToPayments($receiptNo, $agentUserId, $refNo, $tenant->id);
                }

                return [
                    'imported' => false,
                    'skipped_existing' => false,
                    'skipped_no_tenant' => false,
                    'skipped_no_open_balance' => false,
                    'skipped_zero_amount' => false,
                    'enriched_existing' => true,
                    'skipped_no_match' => false,
                    'register_upserted' => $registerUpserted,
                    'allocated' => 0.0,
                    'unallocated' => 0.0,
                    'warnings' => $warnings,
                ];
            }

            if ($enrichOnly) {
                return $this->skipResult('skipped_no_match', $warnings, $registerUpserted);
            }

            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': tenant has no open invoice balance (likely already paid via invoice import) — skipped.';

            return $this->skipResult('skipped_no_open_balance', $warnings, $registerUpserted);
        }

        $channel = $this->channelFromReceiptedTo($receiptedTo);
        $externalRef = ($refNo !== '' && $refNo !== 'CASH') ? $refNo : 'EZEN-'.$receiptNo;
        $meta = [
            'source' => 'ezen_rent_receipt_import',
            'ezen_receipt_no' => $receiptNo,
            'ezen_ref_no' => $refNo,
            'particulars' => (string) ($row['particulars'] ?? ''),
            'unit_label' => $unitLabel,
            'property_code' => $propertyCode,
            'receipted_to' => $receiptedTo,
            'done_by' => (string) ($row['done_by'] ?? ''),
            'mpesa_ref' => $refNo !== '' && $refNo !== 'CASH' ? $refNo : null,
            'payer_phone' => $payerPhone,
        ];

        if ($dryRun) {
            $allocatable = min($amount, $openBalance > 0 ? $openBalance : $amount);

            return [
                'imported' => true,
                'skipped_existing' => false,
                'skipped_no_tenant' => false,
                'skipped_no_open_balance' => false,
                'skipped_zero_amount' => false,
                'enriched_existing' => false,
                'skipped_no_match' => false,
                'register_upserted' => $registerUpserted,
                'allocated' => round($allocatable, 2),
                'unallocated' => round(max(0, $amount - $allocatable), 2),
                'warnings' => $warnings,
            ];
        }

        $payment = $this->payments->recordAdvancePayment([
            'pm_tenant_id' => $tenant->id,
            'channel' => $channel,
            'amount' => $amount,
            'external_ref' => $externalRef,
            'paid_at' => $paidAt,
            'meta' => $meta,
        ], $actor);

        $payment->refresh()->load('allocations');
        $allocated = round((float) $payment->allocations->sum('amount'), 2);
        $unallocated = round(max(0, $amount - $allocated), 2);
        if ($unallocated > 0.009) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': '.number_format($unallocated, 2).' unallocated to invoices (tenant credit).';
        }

        $this->linkRegisterPayment($receiptNo, $agentUserId, (int) $payment->id, (int) $tenant->id);

        return [
            'imported' => true,
            'skipped_existing' => false,
            'skipped_no_tenant' => false,
            'skipped_no_open_balance' => false,
            'skipped_zero_amount' => false,
            'enriched_existing' => false,
            'skipped_no_match' => false,
            'register_upserted' => $registerUpserted,
            'allocated' => $allocated,
            'unallocated' => $unallocated,
            'warnings' => $warnings,
        ];
    }

    private function tryEnrichExistingPayment(
        PmTenant $tenant,
        float $amount,
        Carbon $paidAt,
        string $receiptNo,
        string $refNo,
        string $payerPhone,
        string $receiptedTo,
        string $propertyCode,
        string $unitLabel,
        bool $dryRun,
    ): bool {
        $targets = $this->findPaymentsToEnrich($tenant, $amount, $paidAt, $unitLabel);
        if ($targets->isEmpty()) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $this->applyEnrichmentToPayments(
            $targets,
            [$receiptNo],
            [$refNo],
            $payerPhone,
            $receiptedTo,
            $propertyCode,
            $unitLabel,
        );

        return true;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmPayment>
     */
    private function findPaymentsToEnrich(
        PmTenant $tenant,
        float $amount,
        Carbon $paidAt,
        string $unitLabel,
        bool $onlyMissingRef = true,
    ): \Illuminate\Support\Collection {
        $fullPool = $this->enrichablePaymentsForTenant($tenant, $paidAt, false);
        $pool = $onlyMissingRef
            ? $fullPool->filter(fn (PmPayment $payment): bool => trim((string) data_get($payment->meta, 'mpesa_ref', '')) === '')
            : $fullPool;

        if ($pool->isEmpty()) {
            return collect();
        }

        $exact = $pool->filter(
            fn (PmPayment $payment): bool => abs((float) $payment->amount - $amount) <= 0.01
        );
        if ($exact->count() === 1) {
            return $exact->values();
        }
        if ($exact->count() > 1 && $unitLabel !== '') {
            $unitExact = $exact->filter(
                fn (PmPayment $payment): bool => $this->paymentMatchesUnit($payment, $unitLabel)
            )->values();
            if ($unitExact->count() === 1) {
                return $unitExact;
            }
        }

        $sameMonthFull = $fullPool->filter(function (PmPayment $payment) use ($paidAt): bool {
            $paymentDate = Carbon::parse($payment->paid_at);

            return $paymentDate->year === $paidAt->year && $paymentDate->month === $paidAt->month;
        })->values();

        if ($unitLabel !== '') {
            $unitPoolFull = $sameMonthFull->filter(
                fn (PmPayment $payment): bool => $this->paymentMatchesUnit($payment, $unitLabel)
            )->values();
            if ($unitPoolFull->isNotEmpty() && abs($unitPoolFull->sum(fn (PmPayment $p): float => (float) $p->amount) - $amount) <= 0.02) {
                return $unitPoolFull->filter(
                    fn (PmPayment $payment): bool => ! $onlyMissingRef || trim((string) data_get($payment->meta, 'mpesa_ref', '')) === ''
                )->values();
            }
        }

        if ($sameMonthFull->isNotEmpty() && abs($sameMonthFull->sum(fn (PmPayment $p): float => (float) $p->amount) - $amount) <= 0.02) {
            return $sameMonthFull->filter(
                fn (PmPayment $payment): bool => ! $onlyMissingRef || trim((string) data_get($payment->meta, 'mpesa_ref', '')) === ''
            )->values();
        }

        return collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmPayment>
     */
    private function findPaymentsForRegisterRow(PmEzenReceiptRegister $register, PmTenant $tenant): \Illuminate\Support\Collection
    {
        $paidAt = Carbon::parse($register->banking_date ?? $register->txn_date ?? now());
        $amount = (float) $register->amount;
        $unitLabel = trim((string) ($register->unit_label ?? ''));

        $missing = $this->enrichablePaymentsForTenant($tenant, $paidAt, true);
        if ($missing->isEmpty()) {
            return collect();
        }

        $sameMonth = $missing->filter(function (PmPayment $payment) use ($paidAt): bool {
            $paymentDate = Carbon::parse($payment->paid_at);
            $monthDiff = ($paidAt->year * 12 + $paidAt->month) - ($paymentDate->year * 12 + $paymentDate->month);

            return $monthDiff >= -1 && $monthDiff <= 1;
        })->values();

        $scoped = $unitLabel !== ''
            ? $sameMonth->filter(fn (PmPayment $payment): bool => $this->paymentMatchesUnit($payment, $unitLabel))->values()
            : $sameMonth;

        if ($scoped->isEmpty()) {
            $scoped = $sameMonth;
        }

        $exact = $scoped->filter(
            fn (PmPayment $payment): bool => abs((float) $payment->amount - $amount) <= 0.01
        )->values();
        if ($exact->count() === 1) {
            return $exact;
        }
        if ($exact->count() > 1) {
            $sameMonthExact = $exact->filter(function (PmPayment $payment) use ($paidAt): bool {
                $paymentDate = Carbon::parse($payment->paid_at);

                return $paymentDate->year === $paidAt->year && $paymentDate->month === $paidAt->month;
            })->values();
            if ($sameMonthExact->count() === 1) {
                return $sameMonthExact;
            }
        }

        $scopedSum = round((float) $scoped->sum(fn (PmPayment $payment): float => (float) $payment->amount), 2);
        if ($scoped->isNotEmpty() && abs($scopedSum - $amount) <= 0.02) {
            return $scoped;
        }
        if ($scoped->count() > 1 && $scoped->isNotEmpty() && $amount + 0.02 >= $scopedSum) {
            return $scoped;
        }

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmPayment>
     */
    private function enrichablePaymentsForTenant(PmTenant $tenant, Carbon $paidAt, bool $onlyMissingRef = true): \Illuminate\Support\Collection
    {
        return PmPayment::query()
            ->withoutGlobalScopes()
            ->with(['allocations.invoice.unit'])
            ->where('pm_tenant_id', $tenant->id)
            ->where('channel', 'ezen_import')
            ->orderByDesc('id')
            ->get()
            ->filter(function (PmPayment $payment): bool {
                $existingRef = trim((string) ($payment->external_ref ?? ''));

                return PmPaymentPresentation::isInternalEzenReference($existingRef)
                    || data_get($payment->meta, 'source') === 'ezen_rental_invoice_import';
            })
            ->filter(function (PmPayment $payment) use ($paidAt): bool {
                $paymentDate = Carbon::parse($payment->paid_at);
                $monthDiff = ($paidAt->year * 12 + $paidAt->month) - ($paymentDate->year * 12 + $paymentDate->month);

                return $monthDiff >= -1 && $monthDiff <= 1;
            })
            ->when($onlyMissingRef, fn ($collection) => $collection->filter(
                fn (PmPayment $payment): bool => trim((string) data_get($payment->meta, 'mpesa_ref', '')) === ''
            ))
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PmPayment>  $payments
     * @param  list<string>  $receiptNos
     * @param  list<string>  $refNos
     */
    private function applyEnrichmentToPayments(
        \Illuminate\Support\Collection $payments,
        array $receiptNos,
        array $refNos,
        string $payerPhone,
        string $receiptedTo,
        string $propertyCode,
        string $unitLabel,
    ): void {
        $primaryUpdated = false;
        $receiptNos = array_values(array_filter(array_map(
            static fn (string $no): string => strtoupper(trim($no)),
            $receiptNos,
        )));
        $refNos = array_values(array_filter(array_map(
            static fn (string $ref): string => strtoupper(trim($ref)),
            $refNos,
        )));
        $mpesaRefs = array_values(array_filter(
            $refNos,
            static fn (string $ref): bool => $ref !== '' && $ref !== 'CASH',
        ));
        $combinedRef = implode(', ', $mpesaRefs);
        $combinedReceiptNo = implode(', ', $receiptNos);
        if (in_array('CASH', $refNos, true)) {
            $receiptedTo = 'CASH';
        } elseif ($receiptedTo === '' && in_array('CASH', $refNos, true)) {
            $receiptedTo = 'CASH';
        }

        foreach ($payments as $payment) {
            $meta = is_array($payment->meta) ? $payment->meta : [];
            if ($combinedRef !== '') {
                $meta['mpesa_ref'] = $combinedRef;
                $meta['ezen_ref_no'] = $mpesaRefs[0] ?? $combinedRef;
                $meta['mpesa_refs'] = $mpesaRefs;
            }
            if ($payerPhone !== '') {
                $meta['payer_phone'] = $payerPhone;
            }
            if ($receiptedTo !== '') {
                $meta['receipted_to'] = $receiptedTo;
            }
            if ($propertyCode !== '') {
                $meta['property_code'] = strtoupper(trim($propertyCode));
            }
            if ($unitLabel !== '') {
                $meta['unit_label'] = trim($unitLabel);
            }
            if ($combinedReceiptNo !== '') {
                $meta['ezen_receipt_no'] = $combinedReceiptNo;
                $meta['ezen_receipt_nos'] = $receiptNos;
            }
            $meta['enriched_from_receipt_import'] = true;

            $updates = ['meta' => $meta];
            $primaryRef = $mpesaRefs[0] ?? '';
            if (
                ! $primaryUpdated
                && $primaryRef !== ''
                && PmPaymentPresentation::isInternalEzenReference((string) $payment->external_ref)
            ) {
                $updates['external_ref'] = $primaryRef;
                $primaryUpdated = true;
            }

            $payment->update($updates);
        }
    }

    private function paymentMatchesUnit(PmPayment $payment, string $unitLabel): bool
    {
        return $this->paymentMatchesUnitLabel($this->paymentUnitLabel($payment), $unitLabel);
    }

    private function paymentMatchesUnitLabel(string $paymentUnit, string $unitLabel): bool
    {
        $unitLabel = strtoupper(trim($unitLabel));
        $paymentUnit = strtoupper(trim($paymentUnit));
        if ($paymentUnit === '' || $unitLabel === '') {
            return false;
        }

        return $paymentUnit === $unitLabel
            || str_contains($paymentUnit, $unitLabel)
            || str_contains($unitLabel, $paymentUnit);
    }

    private function paymentUnitLabel(PmPayment $payment): string
    {
        $meta = strtoupper(trim((string) data_get($payment->meta, 'unit_label', '')));
        if ($meta !== '') {
            return $meta;
        }

        $invoice = $payment->allocations->first()?->invoice;

        return strtoupper(trim((string) ($invoice?->unit?->label ?? '')));
    }

    private function registerAlreadyLinked(int $registerId): bool
    {
        return PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->whereKey($registerId)
            ->whereNotNull('pm_payment_id')
            ->exists();
    }

    private function registerShouldProcess(PmEzenReceiptRegister $register): bool
    {
        if ($register->pm_payment_id === null) {
            return true;
        }

        $payment = PmPayment::query()->withoutGlobalScopes()->find($register->pm_payment_id);
        if ($payment === null) {
            return true;
        }

        $bankMonth = Carbon::parse($register->banking_date ?? $register->txn_date ?? now())->format('Y-m');
        $payMonth = Carbon::parse($payment->paid_at ?? now())->format('Y-m');

        return $bankMonth !== $payMonth;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PmEzenReceiptRegister>  $registers
     * @param  \Illuminate\Support\Collection<int, PmPayment>  $payments
     */
    private function linkRegistersToPayments(
        \Illuminate\Support\Collection $registers,
        int $tenantId,
        \Illuminate\Support\Collection $payments,
    ): void {
        if (! Schema::hasTable('pm_ezen_receipt_register') || $payments->isEmpty()) {
            return;
        }

        $paymentId = (int) $payments->first()->id;
        PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $registers->pluck('id')->all())
            ->update([
                'pm_payment_id' => $paymentId,
                'pm_tenant_id' => $tenantId,
                'link_status' => PmEzenReceiptRegister::LINK_PAYMENT,
            ]);
    }

    private function tenantUnitMonthKey(int $tenantId, string $unitLabel, Carbon $date): string
    {
        return $tenantId.'|'.strtoupper(trim($unitLabel)).'|'.$date->format('Y-m');
    }

    /**
     * @return \Illuminate\Support\Collection<int, PmPayment>
     */
    private function findPaymentsForRegisterGroup(PmTenant $tenant, Carbon $paidAt, string $unitLabel): \Illuminate\Support\Collection
    {
        $missing = $this->enrichablePaymentsForTenant($tenant, $paidAt, true);
        if ($missing->isEmpty()) {
            return collect();
        }

        $sameMonth = $missing->filter(function (PmPayment $payment) use ($paidAt): bool {
            $paymentDate = Carbon::parse($payment->paid_at);

            return $paymentDate->year === $paidAt->year && $paymentDate->month === $paidAt->month;
        })->values();

        if ($unitLabel !== '') {
            $scoped = $sameMonth->filter(
                fn (PmPayment $payment): bool => $this->paymentMatchesUnit($payment, $unitLabel)
            )->values();
            if ($scoped->isNotEmpty()) {
                return $scoped;
            }
        }

        return $sameMonth;
    }

    private function registerPaymentSumsMatch(float $regSum, float $paySum, int $registerCount, int $paymentCount): bool
    {
        if (abs($regSum - $paySum) <= 0.02) {
            return true;
        }

        if ($regSum >= $paySum && ($regSum - $paySum) <= max(250.0, $paySum * 0.05)) {
            return true;
        }

        if ($registerCount === 1 && $paymentCount === 1 && $paySum >= $regSum && ($paySum - $regSum) <= max(1000.0, $paySum * 0.25)) {
            return true;
        }

        return false;
    }

    private function paymentNeedsReceiptEnrichment(PmPayment $payment): bool
    {
        if (trim((string) data_get($payment->meta, 'mpesa_ref', '')) !== '') {
            return false;
        }

        $receiptedTo = strtoupper(trim((string) data_get($payment->meta, 'receipted_to', '')));
        if ($receiptedTo !== '' && ! in_array($receiptedTo, ['MR.', 'MR'], true)) {
            return false;
        }

        $existingRef = trim((string) ($payment->external_ref ?? ''));

        return PmPaymentPresentation::isInternalEzenReference($existingRef)
            || data_get($payment->meta, 'source') === 'ezen_rental_invoice_import';
    }

    private function findExistingPayment(string $receiptNo, string $refNo): ?PmPayment
    {
        $payment = PmPayment::query()
            ->withoutGlobalScopes()
            ->where('external_ref', 'EZEN-'.$receiptNo)
            ->first();

        if ($payment !== null) {
            return $payment;
        }

        if ($refNo !== '') {
            return PmPayment::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($refNo): void {
                    $query->where('external_ref', $refNo)
                        ->orWhere('meta->mpesa_ref', $refNo)
                        ->orWhere('meta->ezen_ref_no', $refNo);
                })
                ->first();
        }

        return null;
    }

    private function openInvoiceBalanceForTenant(int $tenantId): float
    {
        return round((float) PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_tenant_id', $tenantId)
            ->whereNotIn('status', [PmInvoice::STATUS_CANCELLED, PmInvoice::STATUS_PAID])
            ->get()
            ->sum(fn (PmInvoice $invoice): float => max(0, $invoice->balanceFloat())), 2);
    }

    private function resolveTenant(string $account, int $agentUserId): ?PmTenant
    {
        foreach ($this->tntAccountCandidates($account) as $candidate) {
            $matches = PmTenant::query()
                ->withoutGlobalScopes()
                ->where('account_number', $candidate)
                ->orderByDesc('id')
                ->get();

            if ($matches->isEmpty()) {
                continue;
            }

            if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                $scoped = $matches->first(fn (PmTenant $tenant) => (int) $tenant->agent_user_id === $agentUserId);
                if ($scoped) {
                    return $scoped;
                }
            }

            return $matches->first();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tntAccountCandidates(string $account): array
    {
        $account = strtoupper(trim($account));
        $candidates = [$account];
        if (preg_match('/^TNT0*(\d+)$/', $account, $match) === 1) {
            $number = (int) $match[1];
            $candidates[] = 'TNT'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $candidates[] = 'TNT'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            $candidates[] = 'TNT'.$number;
        }

        return array_values(array_unique($candidates));
    }

    private function channelFromReceiptedTo(string $receiptedTo): string
    {
        $upper = strtoupper(trim($receiptedTo));
        if (str_contains($upper, 'M-PESA') || str_contains($upper, 'MPESA')) {
            return 'mpesa';
        }
        if (str_contains($upper, 'CASH')) {
            return 'cash';
        }
        if (str_contains($upper, 'BANK') || str_contains($upper, 'CO-OPERATIVE') || str_contains($upper, 'EQUITY') || str_contains($upper, 'KCB')) {
            return 'bank_transfer';
        }

        return 'ezen_receipt';
    }

    private function isDuplicatePayment(QueryException $exception, string $receiptNo): bool
    {
        $message = $exception->getMessage();
        if (! str_contains($message, '1062') && ! str_contains($message, 'Duplicate entry')) {
            return false;
        }

        return $this->findExistingPayment($receiptNo, '') !== null
            || str_contains($message, 'external_ref');
    }

    /**
     * @param  'skipped_existing'|'skipped_no_tenant'|'skipped_no_open_balance'|'skipped_zero_amount'|'skipped_no_match'  $reason
     * @param  list<string>  $warnings
     * @return array{
     *     imported:bool,
     *     skipped_existing:bool,
     *     skipped_no_tenant:bool,
     *     skipped_no_open_balance:bool,
     *     skipped_zero_amount:bool,
     *     enriched_existing:bool,
     *     skipped_no_match:bool,
     *     register_upserted:bool,
     *     allocated:float,
     *     unallocated:float,
     *     warnings:list<string>
     * }
     */
    private function skipResult(string $reason, array $warnings, bool $registerUpserted = false): array
    {
        return [
            'imported' => false,
            'skipped_existing' => $reason === 'skipped_existing',
            'skipped_no_tenant' => $reason === 'skipped_no_tenant',
            'skipped_no_open_balance' => $reason === 'skipped_no_open_balance',
            'skipped_zero_amount' => $reason === 'skipped_zero_amount',
            'enriched_existing' => false,
            'skipped_no_match' => $reason === 'skipped_no_match',
            'register_upserted' => $registerUpserted,
            'allocated' => 0.0,
            'unallocated' => 0.0,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertReceiptRegister(array $row, int $agentUserId, ?PmTenant $tenant, bool $dryRun): bool
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return false;
        }

        $receiptNo = strtoupper(trim((string) ($row['ezen_receipt_no'] ?? '')));
        if ($receiptNo === '') {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $refNo = strtoupper(trim((string) ($row['ref_no'] ?? '')));
        $payment = $this->resolveLinkedPayment($receiptNo, $refNo, $tenant?->id);

        $linkStatus = PmEzenReceiptRegister::LINK_IMPORTED;
        if ($tenant === null) {
            $linkStatus = PmEzenReceiptRegister::LINK_NO_TENANT;
        } elseif ($payment !== null) {
            $linkStatus = PmEzenReceiptRegister::LINK_PAYMENT;
        } elseif ($tenant !== null) {
            $linkStatus = PmEzenReceiptRegister::LINK_TENANT;
        }

        PmEzenReceiptRegister::query()->updateOrCreate(
            [
                'agent_user_id' => $agentUserId,
                'ezen_receipt_no' => $receiptNo,
            ],
            [
                'ref_no' => $refNo !== '' ? $refNo : null,
                'property_code' => strtoupper(trim((string) ($row['property_code'] ?? ''))) ?: null,
                'unit_label' => trim((string) ($row['unit_label'] ?? '')) ?: null,
                'tnt_account' => strtoupper(trim((string) ($row['tnt_account'] ?? ''))) ?: null,
                'register_tenant_name' => trim((string) ($row['tenant_name'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                'particulars' => trim((string) ($row['particulars'] ?? '')) ?: null,
                'amount' => round((float) ($row['amount'] ?? 0), 2),
                'txn_date' => (string) ($row['txn_date'] ?? '') ?: null,
                'banking_date' => (string) ($row['banking_date'] ?? '') ?: null,
                'receipted_to' => trim((string) ($row['receipted_to'] ?? '')) ?: null,
                'done_by' => trim((string) ($row['done_by'] ?? '')) ?: null,
                'pm_tenant_id' => $tenant?->id,
                'pm_payment_id' => $payment?->id,
                'link_status' => $linkStatus,
            ],
        );

        return true;
    }

    private function resolveLinkedPayment(string $receiptNo, string $refNo, ?int $tenantId): ?PmPayment
    {
        if ($refNo !== '' && $refNo !== 'CASH') {
            $payment = PmPayment::query()
                ->withoutGlobalScopes()
                ->where('external_ref', $refNo)
                ->first();
            if ($payment !== null) {
                return $payment;
            }

            $payment = PmPayment::query()
                ->withoutGlobalScopes()
                ->where('meta->mpesa_ref', $refNo)
                ->first();
            if ($payment !== null) {
                return $payment;
            }
        }

        return PmPayment::query()
            ->withoutGlobalScopes()
            ->where('external_ref', 'EZEN-'.$receiptNo)
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->first();
    }

    private function linkRegisterPayment(string $receiptNo, int $agentUserId, int $paymentId, int $tenantId): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('ezen_receipt_no', $receiptNo)
            ->update([
                'pm_payment_id' => $paymentId,
                'pm_tenant_id' => $tenantId,
                'link_status' => PmEzenReceiptRegister::LINK_PAYMENT,
            ]);
    }

    private function linkRegisterToPayments(string $receiptNo, int $agentUserId, string $refNo, int $tenantId): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        $payment = $this->resolveLinkedPayment($receiptNo, $refNo, $tenantId);
        if ($payment === null) {
            return;
        }

        PmEzenReceiptRegister::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('ezen_receipt_no', $receiptNo)
            ->update([
                'pm_payment_id' => $payment->id,
                'pm_tenant_id' => $tenantId,
                'link_status' => PmEzenReceiptRegister::LINK_PAYMENT,
            ]);
    }

    private function namesLooselyMatch(string $a, string $b): bool
    {
        $a = $this->nameKey($a);
        $b = $this->nameKey($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }
        $max = max(strlen($a), strlen($b));

        return $max > 8 && (similar_text($a, $b) / $max) >= 0.88;
    }

    private function nameKey(string $name): string
    {
        $n = strtoupper($name);
        $n = str_replace(["'", '`'], '', $n);
        $n = preg_replace('/[^A-Z0-9]+/', ' ', $n) ?? '';
        $n = preg_replace('/\b(OCCP|OCCUPIED|OCC)\b/', ' ', $n) ?? '';
        $n = preg_replace('/\s+/', ' ', trim($n)) ?? '';

        return trim($n);
    }
}
