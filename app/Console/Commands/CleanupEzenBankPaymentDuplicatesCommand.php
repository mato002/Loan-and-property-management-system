<?php

namespace App\Console\Commands;

use App\Models\PmBankStatementLine;
use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Services\Property\PropertyTransactionReversalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CleanupEzenBankPaymentDuplicatesCommand extends Command
{
    protected $signature = 'property:cleanup-ezen-bank-payment-duplicates
                            {--agent-user-id= : Limit to one agent portfolio}
                            {--tenant-id= : Limit to one tenant}
                            {--from= : First payment date (YYYY-MM-DD)}
                            {--to= : Last payment date (YYYY-MM-DD)}
                            {--limit=1000 : Maximum completed tenant payments to inspect}
                            {--apply : Reverse only exact-reference Ezen duplicates (default is preview only)}';

    protected $description = 'Find and optionally reverse Ezen-imported payments duplicated by bank-statement payments';

    public function handle(): int
    {
        if (! Schema::hasTable('pm_payments') || ! Schema::hasTable('pm_payment_allocations')) {
            $this->error('Property payment tables are missing.');

            return self::FAILURE;
        }

        $agentOption = $this->option('agent-user-id');
        $agentId = is_numeric($agentOption) ? (int) $agentOption : null;
        $tenantOption = $this->option('tenant-id');
        $tenantId = is_numeric($tenantOption) ? (int) $tenantOption : null;
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        $query = PmPayment::withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', '>', 0)
            ->orderBy('paid_at')
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('pm_tenant_id', $tenantId);
        } elseif ($agentId !== null && Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->whereExists(function ($sub) use ($agentId): void {
                $sub->selectRaw('1')->from('pm_tenants as t')
                    ->whereColumn('t.id', 'pm_payments.pm_tenant_id')
                    ->where('t.agent_user_id', $agentId);
            });
        }
        if ($from = $this->option('from')) {
            $query->whereDate('paid_at', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('paid_at', '<=', $to);
        }

        $payments = $query->limit($limit)->get();
        $ezenPayments = $payments->filter(fn (PmPayment $payment): bool => $this->isEzenImport($payment));
        $summary = ['scanned' => $payments->count(), 'candidates' => 0, 'safe' => 0, 'reversed' => 0, 'ambiguous' => 0, 'errors' => []];

        foreach ($ezenPayments as $ezenPayment) {
            $summary['candidates']++;
            $matches = $this->matchingBankPayments($ezenPayment, $payments);
            if ($matches->count() !== 1) {
                if ($matches->count() > 1) {
                    $summary['ambiguous']++;
                    $this->line(sprintf(
                        'AMBIGUOUS Ezen payment #%d (%s), tenant #%d, KES %s: %d bank payments match its receipt reference; skipped.',
                        $ezenPayment->id,
                        $ezenPayment->external_ref,
                        $ezenPayment->pm_tenant_id,
                        number_format((float) $ezenPayment->amount, 2),
                        $matches->count(),
                    ));
                }
                continue;
            }

            /** @var PmPayment $bankPayment */
            $bankPayment = $matches->first();
            $summary['safe']++;
            $this->line(sprintf(
                '%s: Ezen payment #%d (%s) duplicates bank payment #%d (%s), tenant #%d, KES %s.',
                $apply ? 'Exact duplicate' : 'Would reverse',
                $ezenPayment->id,
                $ezenPayment->external_ref,
                $bankPayment->id,
                $bankPayment->external_ref,
                $ezenPayment->pm_tenant_id,
                number_format((float) $ezenPayment->amount, 2),
            ));

            if (! $apply) {
                continue;
            }

            try {
                DB::transaction(function () use ($ezenPayment, $bankPayment): void {
                    $ezenPayment->refresh();
                    $bankPayment->refresh();
                    if ($ezenPayment->status !== PmPayment::STATUS_COMPLETED
                        || $bankPayment->status !== PmPayment::STATUS_COMPLETED
                        || ! $this->hasExactReferenceMatch($ezenPayment, $bankPayment)) {
                        return;
                    }

                    $reason = 'Duplicate Ezen-imported receipt; bank-statement payment #'.$bankPayment->id.' is the trusted source.';
                    app(PropertyTransactionReversalService::class)->reversePayment($ezenPayment, null, $reason);

                    $meta = is_array($ezenPayment->meta) ? $ezenPayment->meta : [];
                    $meta['reversal']['cleanup'] = 'ezen_bank_payment_duplicate';
                    $meta['reversal']['bank_payment_id'] = (int) $bankPayment->id;
                    $ezenPayment->meta = $meta;
                    $ezenPayment->reversal_status = PmPayment::REVERSAL_STATUS_REVERSED;
                    $ezenPayment->reversal_reason = $reason;
                    $ezenPayment->save();
                });
                $summary['reversed']++;
            } catch (Throwable $e) {
                $summary['errors'][] = 'Ezen payment #'.$ezenPayment->id.': '.$e->getMessage();
            }
        }

        $this->newLine();
        $this->info(($apply ? '' : '[DRY RUN] ').'Scanned: '.$summary['scanned'].'; Ezen imports: '.$summary['candidates'].'; exact duplicates: '.$summary['safe'].'; reversed: '.$summary['reversed'].'; ambiguous: '.$summary['ambiguous']);
        if (! $apply) {
            $this->comment('Preview only. Rerun with --apply to reverse exact reference matches. Unmatched or ambiguous rows are left untouched.');
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function isEzenImport(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return in_array($meta['source'] ?? null, ['ezen_rental_invoice_import', 'ezen_rent_receipt_import'], true)
            || str_starts_with(strtoupper((string) $payment->external_ref), 'EZEN-RC');
    }

    private function matchingBankPayments(PmPayment $ezenPayment, $payments)
    {
        return $payments->filter(function (PmPayment $candidate) use ($ezenPayment): bool {
            return (int) $candidate->id !== (int) $ezenPayment->id
                && (int) $candidate->pm_tenant_id === (int) $ezenPayment->pm_tenant_id
                && round((float) $candidate->amount, 2) === round((float) $ezenPayment->amount, 2)
                && $this->isBankPayment($candidate)
                && $this->hasExactReferenceMatch($ezenPayment, $candidate)
                && $this->sharesActiveInvoiceAllocation($ezenPayment, $candidate);
        })->values();
    }

    private function hasExactReferenceMatch(PmPayment $ezenPayment, PmPayment $bankPayment): bool
    {
        $meta = is_array($ezenPayment->meta) ? $ezenPayment->meta : [];
        $receiptNo = strtoupper(trim((string) ($meta['ezen_receipt_no'] ?? '')));
        $register = null;
        if ($receiptNo !== '' && Schema::hasTable('pm_ezen_receipt_register')) {
            $register = PmEzenReceiptRegister::withoutGlobalScopes()
                ->whereRaw('UPPER(TRIM(ezen_receipt_no)) = ?', [$receiptNo])
                ->where('pm_tenant_id', (int) $ezenPayment->pm_tenant_id)
                ->first(['id', 'ref_no']);
            if ($register && PmBankStatementLine::withoutGlobalScopes()
                ->where('pm_payment_id', (int) $bankPayment->id)
                ->where('pm_ezen_receipt_register_id', (int) $register->id)
                ->exists()) {
                return true;
            }
        }

        $receiptReferences = array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            [$meta['ezen_ref_no'] ?? null, $meta['mpesa_ref'] ?? null, $register?->ref_no],
        ));
        $receiptReferences = array_values(array_filter(
            $receiptReferences,
            static fn (string $reference): bool => $reference !== ''
                && $reference !== 'CASH'
                && ! str_starts_with($reference, 'EZEN-')
        ));
        if ($receiptReferences === []) {
            return false;
        }

        $bankReferences = [strtoupper(trim((string) $bankPayment->external_ref))];
        $line = $this->statementLineFor($bankPayment);
        if ($line) {
            $bankReferences[] = strtoupper(trim((string) $line->reference));
            foreach ($receiptReferences as $reference) {
                if ($reference !== '' && str_contains(strtoupper((string) $line->narration), $reference)) {
                    return true;
                }
            }
        }

        return count(array_intersect($receiptReferences, array_filter($bankReferences))) > 0;
    }

    private function isBankPayment(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $source = strtolower((string) ($meta['source'] ?? ''));
        $channel = strtolower((string) $payment->channel);
        $payload = is_array($meta['raw_payload'] ?? null) ? $meta['raw_payload'] : [];

        return str_contains($source, 'statement')
            || str_contains($source, 'bank')
            || str_contains($channel, 'statement')
            || str_contains($channel, 'bank')
            || (int) ($payload['pm_bank_statement_line_id'] ?? 0) > 0
            || PmBankStatementLine::withoutGlobalScopes()->where('pm_payment_id', $payment->id)->exists();
    }

    private function statementLineFor(PmPayment $payment): ?PmBankStatementLine
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $payload = is_array($meta['raw_payload'] ?? null) ? $meta['raw_payload'] : [];
        $lineId = (int) ($payload['pm_bank_statement_line_id'] ?? 0);

        return $lineId > 0
            ? PmBankStatementLine::withoutGlobalScopes()->find($lineId)
            : PmBankStatementLine::withoutGlobalScopes()->where('pm_payment_id', $payment->id)->latest('id')->first();
    }

    private function sharesActiveInvoiceAllocation(PmPayment $first, PmPayment $second): bool
    {
        $firstInvoiceIds = $first->allocations()
            ->where(fn ($query) => $query->where('is_reversed', false)->orWhereNull('is_reversed'))
            ->whereNotNull('pm_invoice_id')
            ->pluck('pm_invoice_id');
        if ($firstInvoiceIds->isEmpty()) {
            return false;
        }

        return $second->allocations()
            ->where(fn ($query) => $query->where('is_reversed', false)->orWhereNull('is_reversed'))
            ->whereIn('pm_invoice_id', $firstInvoiceIds)
            ->exists();
    }
}
