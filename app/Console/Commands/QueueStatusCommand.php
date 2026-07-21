<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class QueueStatusCommand extends Command
{
    protected $signature = 'ops:queue-status
                            {--json : Output machine-readable JSON}
                            {--failed-limit=5 : Number of recent failed jobs to list}';

    protected $description = 'Queue backlog, failed jobs, and worker health snapshot (database or Redis; Horizon when available).';

    public function handle(): int
    {
        $driver = (string) config('queue.default');
        $queues = ['high', 'default', 'low'];
        $payload = [
            'queue_connection' => $driver,
            'cache_store' => (string) config('cache.default'),
            'queues' => [],
            'failed_jobs' => [],
            'worker' => [],
            'horizon' => [],
            'monitoring' => [],
        ];

        if (! $this->option('json')) {
            $this->info('Queue status — connection: '.$driver);
            $this->newLine();
        }

        $payload['queues'] = $this->reportQueueDepths($queues);
        $payload['failed_jobs'] = $this->reportFailedJobs((int) $this->option('failed-limit'));
        $payload['worker'] = $this->reportWorkerHealth($driver);
        $payload['horizon'] = $this->reportHorizon($driver);
        $payload['monitoring'] = $this->monitoringPointers($driver);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Monitoring guide: docs/QUEUE-MONITORING.md');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $queues
     * @return list<array{queue: string, pending: int|null, error: string|null}>
     */
    private function reportQueueDepths(array $queues): array
    {
        $rows = [];

        if (! $this->option('json')) {
            $this->line('<fg=cyan>Backlog (pending jobs)</>');
        }

        foreach ($queues as $queue) {
            try {
                $pending = Queue::size($queue);
                $rows[] = ['queue' => $queue, 'pending' => $pending, 'error' => null];

                if (! $this->option('json')) {
                    $this->line(sprintf('  %-8s %d', $queue, $pending));
                }
            } catch (Throwable $e) {
                $rows[] = ['queue' => $queue, 'pending' => null, 'error' => $e->getMessage()];

                if (! $this->option('json')) {
                    $this->line('  <fg=red>✗</> '.$queue.': '.$e->getMessage());
                }
            }
        }

        if (! $this->option('json')) {
            $this->newLine();
        }

        return $rows;
    }

    /**
     * @return array{total: int|null, recent: list<array<string, mixed>>, error: string|null}
     */
    private function reportFailedJobs(int $limit): array
    {
        $result = ['total' => null, 'recent' => [], 'error' => null];

        if (! Schema::hasTable('failed_jobs')) {
            $result['error'] = 'failed_jobs table not found';

            if (! $this->option('json')) {
                $this->warn('Failed jobs: table not found (run migrations).');
                $this->newLine();
            }

            return $result;
        }

        try {
            $result['total'] = (int) DB::table('failed_jobs')->count();
            $recent = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get(['uuid', 'queue', 'connection', 'failed_at', 'exception']);

            foreach ($recent as $row) {
                $result['recent'][] = [
                    'uuid' => $row->uuid,
                    'queue' => $row->queue,
                    'connection' => $row->connection,
                    'failed_at' => $row->failed_at,
                    'exception' => Str::limit((string) $row->exception, 160),
                ];
            }

            if (! $this->option('json')) {
                $this->line('<fg=cyan>Failed jobs</>');
                $this->line('  Total: '.$result['total']);

                if ($result['total'] > 0) {
                    $this->line('  Recent (max '.$limit.'):');
                    foreach ($result['recent'] as $job) {
                        $this->line('    - '.$job['failed_at'].' ['.$job['queue'].'] '.$job['uuid']);
                        $this->line('      '.$job['exception']);
                    }
                    $this->line('  Inspect: php artisan queue:failed');
                    $this->line('  Retry all: php artisan queue:retry all');
                }

                $this->newLine();
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();

            if (! $this->option('json')) {
                $this->error('Failed jobs: '.$e->getMessage());
                $this->newLine();
            }
        }

        return $result;
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function reportWorkerHealth(string $driver): array
    {
        if (! $this->option('json')) {
            $this->line('<fg=cyan>Worker health</>');
        }

        if ($driver === 'redis') {
            $detail = 'Use Horizon on VPS: php artisan horizon:status and Supervisor program "php artisan horizon". '
                .'Without Horizon: php artisan queue:work redis --queue=high,default,low';

            if (! $this->option('json')) {
                $this->line('  Driver: redis — prefer Horizon master supervisor (see deploy/laravel-horizon.supervisor.example)');
            }

            return ['status' => 'redis', 'detail' => $detail];
        }

        if ($driver === 'database') {
            $detail = 'Check Supervisor/systemd for queue:work database, or cPanel cron fallback. See docs/QUEUE-WORKER-SETUP.md';

            if (! $this->option('json')) {
                $this->line('  Driver: database — verify long-running queue:work database process');
            }

            return ['status' => 'database', 'detail' => $detail];
        }

        $detail = 'Queue driver "'.$driver.'" — confirm a matching worker process is running.';

        if (! $this->option('json')) {
            $this->line('  '.$detail);
            $this->newLine();
        }

        return ['status' => $driver, 'detail' => $detail];
    }

    /**
     * @return array{available: bool, running: bool|null, detail: string}
     */
    private function reportHorizon(string $driver): array
    {
        $available = class_exists(\Laravel\Horizon\Horizon::class);
        $result = [
            'available' => $available,
            'running' => null,
            'detail' => '',
            'dashboard' => url(config('horizon.path', 'horizon')),
        ];

        if (! $available) {
            $result['detail'] = 'Horizon package not installed.';

            if (! $this->option('json')) {
                $this->line('<fg=cyan>Horizon</>');
                $this->line('  Not installed.');
                $this->newLine();
            }

            return $result;
        }

        if ($driver !== 'redis') {
            $result['detail'] = 'Horizon requires QUEUE_CONNECTION=redis (current: '.$driver.'). Use ops:queue-status for database queues.';

            if (! $this->option('json')) {
                $this->line('<fg=cyan>Horizon</>');
                $this->line('  Installed but inactive — QUEUE_CONNECTION is not redis.');
                $this->newLine();
            }

            return $result;
        }

        try {
            $exitCode = Artisan::call('horizon:status');
            $output = trim(Artisan::output());
            $result['running'] = $exitCode === 0 && str_contains(strtolower($output), 'running');
            $result['detail'] = $output !== '' ? $output : ($result['running'] ? 'Horizon is running.' : 'Horizon is not running.');
        } catch (Throwable $e) {
            $result['detail'] = 'Could not run horizon:status — '.$e->getMessage().' (Horizon requires Linux + ext-pcntl on the worker host)';
        }

        if (! $this->option('json')) {
            $this->line('<fg=cyan>Horizon</>');
            $this->line('  Dashboard: '.$result['dashboard'].' (super admin only)');
            $this->line('  '.$result['detail']);
            $this->newLine();
        }

        return $result;
    }

    /**
     * @return list<array{label: string, command: string}>
     */
    private function monitoringPointers(string $driver): array
    {
        $pointers = [
            ['label' => 'Queue snapshot', 'command' => 'php artisan ops:queue-status'],
            ['label' => 'Failed jobs CLI', 'command' => 'php artisan queue:failed'],
            ['label' => 'Worker log', 'command' => 'tail -f storage/logs/worker.log'],
            ['label' => 'App log', 'command' => 'tail -f storage/logs/laravel.log'],
            ['label' => 'Health endpoint', 'command' => 'GET /up'],
        ];

        if ($driver === 'redis') {
            $pointers[] = ['label' => 'Redis cutover verify', 'command' => 'php artisan ops:redis-cutover-verify'];
            $pointers[] = ['label' => 'Horizon metrics snapshot', 'command' => 'php artisan horizon:snapshot'];
        }

        if (! $this->option('json')) {
            $this->line('<fg=cyan>Where to check</>');
            foreach ($pointers as $item) {
                $this->line('  '.$item['label'].': '.$item['command']);
            }
        }

        return $pointers;
    }
}
