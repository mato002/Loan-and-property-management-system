<?php

namespace App\Console\Commands;

use App\Models\AccountingJournalBatch;
use App\Models\PmBankStatementLine;
use App\Models\PmPayment;
use App\Models\Payment;
use App\Services\Property\PropertyAccountingFinalizeService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\PropertyTransactionReversalService;
use App\Services\Property\CoopBankAccountStatementParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CleanupMpesaBankStatementDuplicatesCommand extends Command
{
    protected $signature = 'property:cleanup-mpesa-bank-statement-duplicates
                            {--agent-user-id= : Limit to one agent portfolio (recommended)}
                            {--from= : First statement transaction date (YYYY-MM-DD)}
                            {--to= : Last statement transaction date (YYYY-MM-DD)}
                            {--limit=500 : Maximum statement payments to inspect}
                            {--apply : Reverse confirmed duplicates (default is preview only)}';

    protected $description = 'Find and optionally reverse bank-statement payments that duplicate an SMS-recorded M-Pesa receipt';

    public function handle(): int
    {
        if (! Schema::hasTable('pm_payments') || ! Schema::hasTable('pm_bank_statement_lines')) {
            $this->error('Property payment or bank statement tables are missing.');

            return self::FAILURE;
        }

        $agentOption = $this->option('agent-user-id');
        $agentId = is_numeric($agentOption) ? (int) $agentOption : null;
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $summary = ['scanned' => 0, 'duplicates' => 0, 'reversed' => 0, 'skipped' => 0, 'errors' => []];

        // Start from imported bank lines, not a specific pm_payments channel or
        // agent attribution. Older statement imports used different channel
        // values and sometimes stamped the actor instead of the portfolio owner.
        $query = PmBankStatementLine::withoutGlobalScopes()
            ->where('direction', 'credit')
            ->where('line_type', CoopBankAccountStatementParser::TYPE_MPESA)
            ->whereNotNull('pm_payment_id')
            ->orderBy('txn_date')
            ->orderBy('id');
        if ($agentId !== null) {
            $query->where('agent_user_id', $agentId);
        }
        if ($from = $this->option('from')) {
            $query->whereDate('txn_date', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('txn_date', '<=', $to);
        }

        foreach ($query->limit($limit)->get() as $line) {
            $summary['scanned']++;
            $bankPayment = PmPayment::withoutGlobalScopes()->find((int) $line->pm_payment_id);
            if (! $bankPayment || $bankPayment->status !== PmPayment::STATUS_COMPLETED
                || (int) $bankPayment->pm_tenant_id <= 0
                || ! $this->isStatementPayment($bankPayment, $line)) {
                $summary['skipped']++;
                continue;
            }

            $smsPayment = $this->matchingSmsPayment($bankPayment, $line);
            if (! $smsPayment || (int) $smsPayment->id === (int) $bankPayment->id) {
                $summary['skipped']++;
                continue;
            }

            $summary['duplicates']++;
            $label = sprintf(
                'bank payment #%d (%s) duplicates SMS payment #%d (%s), tenant #%d, KES %s, line #%d',
                $bankPayment->id,
                $bankPayment->external_ref,
                $smsPayment->id,
                $smsPayment->external_ref,
                $bankPayment->pm_tenant_id,
                number_format((float) $bankPayment->amount, 2),
                $line->id,
            );
            $this->line(($apply ? 'Duplicate: ' : 'Would clear: ').$label);

            if (! $apply) {
                continue;
            }

            try {
                DB::transaction(function () use ($bankPayment, $smsPayment, $line): void {
                    $bankPayment->refresh();
                    if ($bankPayment->status !== PmPayment::STATUS_COMPLETED) {
                        return;
                    }

                    $reason = 'Duplicate bank-statement representation of M-Pesa SMS receipt '.$smsPayment->external_ref;
                    $hasPostedBatch = AccountingJournalBatch::query()
                        ->where('source_type', 'pm_payment')
                        ->where('source_id', (int) $bankPayment->id)
                        ->whereIn('event_type', ['payment_received', 'payment_unmatched_suspense'])
                        ->where('status', AccountingJournalBatch::STATUS_POSTED)
                        ->exists();

                    if ($hasPostedBatch) {
                        app(PropertyTransactionReversalService::class)->reversePayment($bankPayment, null, $reason);
                    } else {
                        app(PropertyAccountingFinalizeService::class)->reversePayment($bankPayment, null, $reason);
                        app(PropertyPaymentSettlementService::class)->reversePaymentAllocations($bankPayment, null, $reason);
                        $meta = is_array($bankPayment->meta) ? $bankPayment->meta : [];
                        $meta['reversal'] = [
                            'reason' => $reason,
                            'reversed_at' => now()->toIso8601String(),
                            'cleanup' => 'mpesa_bank_statement_duplicate',
                        ];
                        $bankPayment->meta = $meta;
                        $bankPayment->status = PmPayment::STATUS_FAILED;
                    }

                    $bankPayment->refresh();
                    $bankPayment->reversal_status = PmPayment::REVERSAL_STATUS_REVERSED;
                    $bankPayment->reversal_reason = $reason;
                    $bankPayment->save();

                    $line->update([
                        'match_status' => PmBankStatementLine::MATCH_MATCHED,
                        'matched_type' => 'payment',
                        'pm_payment_id' => (int) $smsPayment->id,
                        'pm_ezen_receipt_register_id' => null,
                        'unassigned_payment_id' => null,
                    ]);
                });
                $summary['reversed']++;
            } catch (Throwable $e) {
                $summary['errors'][] = $label.': '.$e->getMessage();
            }
        }

        $prefix = $apply ? '' : '[DRY RUN] ';
        $this->newLine();
        $this->info($prefix.'Scanned: '.$summary['scanned'].'; confirmed duplicates: '.$summary['duplicates'].'; reversed: '.$summary['reversed'].'; skipped: '.$summary['skipped']);
        if (! $apply) {
            $this->comment('Review the matches, then rerun with --apply to reverse duplicate statement payments and relink their statement lines to the SMS payment.');
        }
        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function matchingSmsPayment(PmPayment $bankPayment, PmBankStatementLine $line): ?PmPayment
    {
        if (! $bankPayment->paid_at) {
            return null;
        }

        preg_match_all('/\b[A-Z0-9]{8,16}\b/i', (string) $line->narration, $matches);
        $references = array_values(array_unique(array_map(
            static fn (string $value): string => strtoupper($value),
            $matches[0] ?? []
        )));
        foreach ([$line->reference, $bankPayment->external_ref] as $reference) {
            $reference = strtoupper(trim((string) $reference));
            if ($reference !== '' && preg_match('/^[A-Z0-9]{8,16}$/', $reference)) {
                $references[] = $reference;
            }
        }
        $references = array_values(array_unique($references));
        if ($references === []) {
            return null;
        }

        $matches = PmPayment::withoutGlobalScopes()
            ->where('channel', 'mpesa_sms_ingest')
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', (int) $bankPayment->pm_tenant_id)
            ->whereRaw('ROUND(amount, 2) = ?', [round((float) $bankPayment->amount, 2)])
            ->where('paid_at', $bankPayment->paid_at->format('Y-m-d H:i:s'))
            ->where(function ($query) use ($references): void {
                foreach ($references as $reference) {
                    $query->orWhereRaw('UPPER(TRIM(external_ref)) = ?', [$reference]);
                }
            })
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function isStatementPayment(PmPayment $payment, PmBankStatementLine $line): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $payload = is_array($meta['raw_payload'] ?? null) ? $meta['raw_payload'] : [];
        if ((int) ($payload['pm_bank_statement_line_id'] ?? 0) === (int) $line->id) {
            return true;
        }

        $source = strtolower((string) ($meta['source'] ?? ''));
        $channel = strtolower((string) $payment->channel);
        if (str_contains($source, 'statement') || str_contains($channel, 'statement') || str_contains($channel, 'bank')) {
            return true;
        }

        return Payment::query()
            ->where('pm_payment_id', (int) $payment->id)
            ->where(function ($query): void {
                if (Schema::hasColumn('payments', 'payment_method')) {
                    $query->orWhere('payment_method', 'statement_import');
                }
                if (Schema::hasColumn('payments', 'payment_channel')) {
                    $query->orWhere('payment_channel', 'bank_statement');
                }
            })
            ->exists();
    }
}
