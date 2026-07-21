<?php

namespace App\Jobs;

use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\EquityBankService;
use App\Services\PaymentMatchingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchEquityTransactionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    /** @var list<int> */
    public array $backoff = [60, 180];

    public int $uniqueFor = 600;

    public function __construct(private readonly bool $manual = false)
    {
        $this->onQueue('low');
    }

    public function uniqueId(): string
    {
        return 'equity-sync';
    }

    public function handle(
        EquityBankService $equityBankService,
        EquityPaymentRepository $payments,
        PaymentMatchingService $matcher,
        PaymentAuditLogRepository $auditLogs
    ): void {
        $lock = Cache::lock('equity-sync-lock', 240);
        if (! $lock->get()) {
            Log::warning('Skipped Equity sync because previous run is still active.');

            return;
        }

        if (! $equityBankService->isConfigured()) {
            // Avoid creating a noisy "failed" sync run row every 5 minutes when the
            // tenant has not configured Equity API credentials. The service itself
            // emits a throttled log message once an hour explaining how to enable it.
            optional($lock)->release();

            return;
        }

        $run = $payments->startSyncRun($this->manual ? 'manual' : 'scheduler');
        $stats = ['fetched' => 0, 'matched' => 0, 'unmatched' => 0, 'duplicates' => 0, 'errors' => 0];

        try {
            $latest = $payments->latestTransactionDate();
            $since = $latest ? $latest->subMinutes(5)->toIso8601String() : null;

            $result = $equityBankService->fetchTransactions($since);
            if (! ($result['ok'] ?? false)) {
                $stats['errors']++;
                $payments->completeSyncRun($run, $stats, 'failed', (string) ($result['message'] ?? 'Fetch failed'));
                $this->logFinished('failed', $stats, (string) ($result['message'] ?? 'Fetch failed'));

                return;
            }

            $transactions = (array) ($result['transactions'] ?? []);
            $stats['fetched'] = count($transactions);

            foreach ($transactions as $transaction) {
                $transactionId = (string) ($transaction['transaction_id'] ?? '');
                if ($transactionId === '') {
                    $stats['errors']++;
                    $auditLogs->decision('fail', [
                        'stage' => 'validation',
                        'message' => 'Missing transaction_id',
                        'transaction' => $transaction,
                    ]);
                    continue;
                }

                if ($payments->transactionExists($transactionId)) {
                    $stats['duplicates']++;
                    $auditLogs->decision('success', [
                        'stage' => 'duplicate_check',
                        'decision' => 'duplicate_skipped',
                        'transaction_id' => $transactionId,
                    ]);
                    continue;
                }

                $match = $matcher->match((array) $transaction);
                if (($match['tenant_id'] ?? null) !== null) {
                    $payments->storeMatched((array) $transaction, (int) $match['tenant_id'], (string) ($match['matched_by'] ?? 'unknown'));
                    $stats['matched']++;
                    $auditLogs->decision('success', [
                        'stage' => 'matching',
                        'decision' => 'matched',
                        'transaction_id' => $transactionId,
                        'tenant_id' => (int) $match['tenant_id'],
                        'matched_by' => (string) ($match['matched_by'] ?? 'unknown'),
                    ]);
                    continue;
                }

                $payments->storeUnmatched((array) $transaction, (string) ($match['reason'] ?? 'No match'));
                $stats['unmatched']++;
                $auditLogs->decision('success', [
                    'stage' => 'matching',
                    'decision' => 'unmatched',
                    'transaction_id' => $transactionId,
                    'reason' => (string) ($match['reason'] ?? 'No match'),
                ]);
            }

            $payments->completeSyncRun($run, $stats, 'success');
            $this->logFinished('success', $stats);
        } catch (\Throwable $e) {
            $stats['errors']++;
            Log::error('Equity sync job failed', ['message' => $e->getMessage()]);
            $payments->completeSyncRun($run, $stats, 'failed', $e->getMessage());
            $this->logFinished('failed', $stats, $e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array{fetched: int, matched: int, unmatched: int, duplicates: int, errors: int}  $stats
     */
    private function logFinished(string $status, array $stats, ?string $message = null): void
    {
        Log::info('Equity sync job finished', [
            'status' => $status,
            'manual' => $this->manual,
            'fetched' => $stats['fetched'],
            'matched' => $stats['matched'],
            'unmatched' => $stats['unmatched'],
            'duplicates' => $stats['duplicates'],
            'errors' => $stats['errors'],
            'message' => $message,
        ]);
    }
}

