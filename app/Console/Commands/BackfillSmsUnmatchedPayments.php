<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PmSmsIngest;
use App\Models\UnassignedPayment;
use App\Repositories\Equity\EquityPaymentRepository;
use Illuminate\Database\QueryException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillSmsUnmatchedPayments extends Command
{
    protected $signature = 'sms:backfill-unmatched {--dry-run : Show changes without writing} {--limit=500 : Max unmatched SMS ingests to process}';

    protected $description = 'Re-parse existing unmatched SMS ingests and sync corrected transaction IDs/dates to unmatched payment queues.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $hasUnassignedPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $payments = app(EquityPaymentRepository::class);

        $items = PmSmsIngest::query()
            ->where('match_status', 'unmatched')
            ->whereNull('pm_payment_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
        ];

        foreach ($items as $item) {
            $stats['processed']++;
            $raw = (string) ($item->raw_message ?? '');
            if (trim($raw) === '') {
                $stats['skipped']++;
                continue;
            }

            $parsedTxn = $this->extractTxnCode($raw);
            $parsedPaidAt = $this->extractPaidAt($raw);

            $oldTxn = (string) $item->provider_txn_code;
            $newTxn = $parsedTxn ?: $oldTxn;
            $newPaidAt = $parsedPaidAt ?? $item->paid_at;

            $txnChanged = $newTxn !== '' && $newTxn !== $oldTxn;
            $dateChanged = $newPaidAt !== null
                && $item->paid_at !== null
                && ! Carbon::parse($item->paid_at)->equalTo(Carbon::parse($newPaidAt));

            $needsQueueSync = ! Payment::query()->where('transaction_id', $newTxn)->exists()
                || ! UnassignedPayment::query()->where('transaction_id', $newTxn)->exists();

            if (! $txnChanged && ! $dateChanged && ! ($newPaidAt !== null && $item->paid_at === null)) {
                if (! $dryRun && $needsQueueSync) {
                    try {
                        $payments->storeUnmatched([
                            'transaction_id' => $newTxn,
                            'amount' => (float) $item->amount,
                            'account_number' => null,
                            'reference' => null,
                            'phone' => (string) ($item->payer_phone ?? ''),
                            'transaction_date' => $newPaidAt ?? $item->paid_at ?? $item->created_at ?? now(),
                            'raw_payload' => [
                                'source' => 'sms_backfill',
                                'ingest_id' => (int) $item->id,
                                'raw_message' => (string) ($item->raw_message ?? ''),
                            ],
                        ], (string) ($item->match_note ?? 'No tenant match by account number, phone, or reference'), [
                            'payment_method' => 'sms_forwarder',
                        ]);
                    } catch (QueryException $e) {
                        $sqlState = (string) ($e->errorInfo[0] ?? '');
                        $driverCode = (int) ($e->errorInfo[1] ?? 0);
                        if ($sqlState !== '23000' && $driverCode !== 1062) {
                            throw $e;
                        }
                    }
                }
                $stats['skipped']++;
                continue;
            }

            if ($txnChanged) {
                $txnConflict = PmSmsIngest::query()
                    ->where('provider_txn_code', $newTxn)
                    ->where('id', '!=', $item->id)
                    ->exists();
                if ($txnConflict) {
                    $stats['conflicts']++;
                    $this->warn("Skip ingest #{$item->id}: txn code conflict for {$newTxn}");
                    continue;
                }
            }

            $this->line("Ingest #{$item->id}: {$oldTxn} -> {$newTxn}, paid_at -> ".($newPaidAt?->toIso8601String() ?? 'unchanged'));

            if ($dryRun) {
                $stats['updated']++;
                continue;
            }

            DB::transaction(function () use ($item, $oldTxn, $newTxn, $newPaidAt, $txnChanged, $hasUnassignedPaymentMethod) {
                $ingestUpdate = [];
                if ($txnChanged) {
                    $ingestUpdate['provider_txn_code'] = $newTxn;
                }
                if ($newPaidAt !== null) {
                    $ingestUpdate['paid_at'] = $newPaidAt;
                }
                if ($ingestUpdate !== []) {
                    $item->update($ingestUpdate);
                }

                $fromTxn = $oldTxn;
                $toTxn = $txnChanged ? $newTxn : $oldTxn;

                Payment::query()
                    ->where('payment_method', 'sms_forwarder')
                    ->where('status', 'unmatched')
                    ->where('transaction_id', $fromTxn)
                    ->update([
                        'transaction_id' => $toTxn,
                        'transaction_date' => $newPaidAt ?? DB::raw('transaction_date'),
                    ]);

                $unassignedQuery = UnassignedPayment::query()
                    ->where('transaction_id', $fromTxn);
                if ($hasUnassignedPaymentMethod) {
                    $unassignedQuery->where('payment_method', 'sms_forwarder');
                }
                $unassignedQuery->update([
                    'transaction_id' => $toTxn,
                    'created_at' => $newPaidAt ?? DB::raw('created_at'),
                ]);
            });

            $currentTxn = $newTxn;
            $hasPayment = Payment::query()->where('transaction_id', $currentTxn)->exists();
            $hasUnassigned = UnassignedPayment::query()->where('transaction_id', $currentTxn)->exists();
            if (! $hasPayment || ! $hasUnassigned) {
                try {
                    $payments->storeUnmatched([
                        'transaction_id' => $currentTxn,
                        'amount' => (float) $item->amount,
                        'account_number' => null,
                        'reference' => null,
                        'phone' => (string) ($item->payer_phone ?? ''),
                        'transaction_date' => $newPaidAt ?? $item->paid_at ?? $item->created_at ?? now(),
                        'raw_payload' => [
                            'source' => 'sms_backfill',
                            'ingest_id' => (int) $item->id,
                            'raw_message' => (string) ($item->raw_message ?? ''),
                        ],
                    ], (string) ($item->match_note ?? 'No tenant match by account number, phone, or reference'), [
                        'payment_method' => 'sms_forwarder',
                    ]);
                } catch (QueryException $e) {
                    // Duplicate transactions are safe to ignore here.
                    $sqlState = (string) ($e->errorInfo[0] ?? '');
                    $driverCode = (int) ($e->errorInfo[1] ?? 0);
                    if ($sqlState !== '23000' && $driverCode !== 1062) {
                        throw $e;
                    }
                }
            }

            $stats['updated']++;
        }

        $this->info('Backfill complete.');
        $this->table(
            ['processed', 'updated', 'skipped', 'conflicts', 'mode'],
            [[
                $stats['processed'],
                $stats['updated'],
                $stats['skipped'],
                $stats['conflicts'],
                $dryRun ? 'dry-run' : 'write',
            ]]
        );

        return self::SUCCESS;
    }

    private function extractTxnCode(string $message): ?string
    {
        if (preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/iu', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if ($this->isLikelyTxnCode($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractPaidAt(string $message): ?Carbon
    {
        if (preg_match('/\bon\s+(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+at\s+(\d{1,2}):(\d{2})\s*(AM|PM)\b/iu', $message, $m) !== 1) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $meridiem = strtoupper((string) $m[6]);

        if ($year < 100) {
            $year += 2000;
        }
        if ($meridiem === 'PM' && $hour < 12) {
            $hour += 12;
        }
        if ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        }

        try {
            return Carbon::create($year, $month, $day, $hour, $minute, 0, 'Africa/Nairobi')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isLikelyTxnCode(string $value): bool
    {
        return preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{8,12}$/', $value) === 1;
    }
}

