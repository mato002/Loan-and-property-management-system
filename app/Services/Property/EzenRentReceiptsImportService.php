<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\User;
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
            'allocated' => 0.0,
            'unallocated' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            try {
                $result = $this->importRow($row, $agentUserId, $actor, $dryRun, $skipIfNoOpenBalance, $rowNum);
                $summary['imported'] += $result['imported'] ? 1 : 0;
                $summary['skipped_existing'] += $result['skipped_existing'] ? 1 : 0;
                $summary['skipped_no_tenant'] += $result['skipped_no_tenant'] ? 1 : 0;
                $summary['skipped_no_open_balance'] += $result['skipped_no_open_balance'] ? 1 : 0;
                $summary['skipped_zero_amount'] += $result['skipped_zero_amount'] ? 1 : 0;
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
     * @param  array<string, mixed>  $row
     * @return array{
     *     imported:bool,
     *     skipped_existing:bool,
     *     skipped_no_tenant:bool,
     *     skipped_no_open_balance:bool,
     *     skipped_zero_amount:bool,
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
        if ($this->findExistingPayment($receiptNo, $refNo) !== null) {
            return $this->skipResult('skipped_existing', $warnings);
        }

        $tenant = $this->resolveTenant((string) ($row['tnt_account'] ?? ''), $agentUserId);
        if ($tenant === null) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': tenant '.($row['tnt_account'] ?? '?').' not found — skipped.';

            return $this->skipResult('skipped_no_tenant', $warnings);
        }

        if (! $this->namesLooselyMatch((string) ($row['tenant_name'] ?? ''), (string) $tenant->name)) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': register "'.($row['tenant_name'] ?? '')
                .'" vs system "'.$tenant->name.'" — applied to '.$tenant->account_number.'.';
        }

        $openBalance = $this->openInvoiceBalanceForTenant((int) $tenant->id);
        if ($skipIfNoOpenBalance && $openBalance <= 0.009) {
            $warnings[] = 'Row '.$rowNum.' '.$receiptNo.': tenant has no open invoice balance (likely already paid via invoice import) — skipped.';

            return $this->skipResult('skipped_no_open_balance', $warnings);
        }

        $paidAt = Carbon::parse((string) ($row['banking_date'] ?? $row['txn_date'] ?? now()->toDateString()))->startOfDay();
        $channel = $this->channelFromReceiptedTo((string) ($row['receipted_to'] ?? ''));
        $externalRef = 'EZEN-'.$receiptNo;
        $meta = [
            'source' => 'ezen_rent_receipt_import',
            'ezen_receipt_no' => $receiptNo,
            'ezen_ref_no' => $refNo,
            'particulars' => (string) ($row['particulars'] ?? ''),
            'unit_label' => (string) ($row['unit_label'] ?? ''),
            'property_code' => (string) ($row['property_code'] ?? ''),
            'receipted_to' => (string) ($row['receipted_to'] ?? ''),
            'done_by' => (string) ($row['done_by'] ?? ''),
            'mpesa_ref' => $refNo,
        ];

        if ($dryRun) {
            $allocatable = min($amount, $openBalance > 0 ? $openBalance : $amount);

            return [
                'imported' => true,
                'skipped_existing' => false,
                'skipped_no_tenant' => false,
                'skipped_no_open_balance' => false,
                'skipped_zero_amount' => false,
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

        return [
            'imported' => true,
            'skipped_existing' => false,
            'skipped_no_tenant' => false,
            'skipped_no_open_balance' => false,
            'skipped_zero_amount' => false,
            'allocated' => $allocated,
            'unallocated' => $unallocated,
            'warnings' => $warnings,
        ];
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
        $account = strtoupper(trim($account));
        if ($account === '') {
            return null;
        }

        $matches = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('account_number', $account)
            ->orderByDesc('id')
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $scoped = $matches->first(fn (PmTenant $tenant) => (int) $tenant->agent_user_id === $agentUserId);
            if ($scoped) {
                return $scoped;
            }
        }

        return $matches->first();
    }

    private function channelFromReceiptedTo(string $receiptedTo): string
    {
        $upper = strtoupper(trim($receiptedTo));
        if (str_contains($upper, 'M-PESA') || str_contains($upper, 'MPESA')) {
            return 'mpesa';
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
     * @param  'skipped_existing'|'skipped_no_tenant'|'skipped_no_open_balance'|'skipped_zero_amount'  $reason
     * @param  list<string>  $warnings
     * @return array{
     *     imported:bool,
     *     skipped_existing:bool,
     *     skipped_no_tenant:bool,
     *     skipped_no_open_balance:bool,
     *     skipped_zero_amount:bool,
     *     allocated:float,
     *     unallocated:float,
     *     warnings:list<string>
     * }
     */
    private function skipResult(string $reason, array $warnings): array
    {
        return [
            'imported' => false,
            'skipped_existing' => $reason === 'skipped_existing',
            'skipped_no_tenant' => $reason === 'skipped_no_tenant',
            'skipped_no_open_balance' => $reason === 'skipped_no_open_balance',
            'skipped_zero_amount' => $reason === 'skipped_zero_amount',
            'allocated' => 0.0,
            'unallocated' => 0.0,
            'warnings' => $warnings,
        ];
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
