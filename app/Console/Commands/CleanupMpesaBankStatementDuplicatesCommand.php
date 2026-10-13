<?php

namespace App\Console\Commands;

use App\Models\AccountingJournalBatch;
use App\Models\PmBankStatementLine;
use App\Models\PmPayment;
use App\Services\Property\PropertyAccountingFinalizeService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\PropertyTransactionReversalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CleanupMpesaBankStatementDuplicatesCommand extends Command
{
    protected $signature = 'property:cleanup-mpesa-bank-statement-duplicates
                            {--agent-user-id= : Limit to one agent portfolio (recommended)}
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

        $query = PmPayment::withoutGlobalScopes()
            ->where('channel', 'bank_statement')
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', '>', 0)
            ->orderBy('id');
        if (Schema::hasColumn('pm_payments', 'agent_user_id') && $agentId !== null) {
            $query->where('agent_user_id', $agentId);
        }

        foreach ($query->limit($limit)->get() as $bankPayment) {
            $summary['scanned']++;
            $meta = is_array($bankPayment->meta) ? $bankPayment->meta : [];
            $payload = is_array($meta['raw_payload'] ?? null) ? $meta['raw_payload'] : [];
            $lineId = (int) ($payload['pm_bank_statement_line_id'] ?? 0);
            if (($meta['source'] ?? '') !== 'statement_import' || $lineId <= 0) {
                $summary['skipped']++;
                continue;
            }

            $line = PmBankStatementLine::withoutGlobalScopes()->find($lineId);
            if (! $line || ($agentId !== null && (int) $line->agent_user_id !== $agentId)) {
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
        if (! $line->txn_date || trim((string) $line->narration) === '') {
            return null;
        }

        preg_match_all('/\b[A-Z0-9]{8,16}\b/i', (string) $line->narration, $matches);
        $references = array_values(array_unique(array_map(
            static fn (string $value): string => strtoupper($value),
            $matches[0] ?? []
        )));
        if ($references === []) {
            return null;
        }

        $matches = PmPayment::withoutGlobalScopes()
            ->where('channel', 'mpesa_sms_ingest')
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', (int) $bankPayment->pm_tenant_id)
            ->whereRaw('ROUND(amount, 2) = ?', [round((float) $bankPayment->amount, 2)])
            ->whereDate('paid_at', $line->txn_date->toDateString())
            ->where(function ($query) use ($references): void {
                foreach ($references as $reference) {
                    $query->orWhereRaw('UPPER(TRIM(external_ref)) = ?', [$reference]);
                }
            })
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
