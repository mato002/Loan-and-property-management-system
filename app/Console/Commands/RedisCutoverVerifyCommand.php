<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RedisCutoverVerifyCommand extends Command
{
    protected $signature = 'ops:redis-cutover-verify
                            {--json : Output results as JSON for scripts}
                            {--skip-readiness : Skip ops:redis-check (not recommended)}';

    protected $description = 'Verify Phase 9 Redis cutover: cache store, queue driver, cache probe, and failed jobs (requires ops:redis-check to pass first).';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        if (! $this->option('skip-readiness')) {
            if (! $this->runReadinessCheck()) {
                return $this->finish(self::FAILURE);
            }
        } else {
            $this->recordWarn('Readiness gate', 'Skipped ops:redis-check (--skip-readiness)');
        }

        if (! $this->checkCacheDriver()) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkQueueDriver()) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkActiveCacheProbe()) {
            return $this->finish(self::FAILURE);
        }

        $this->checkSessionDriver();

        if (! $this->checkFailedJobs()) {
            return $this->finish(self::FAILURE);
        }

        $this->checkWorkerGuidance();

        return $this->finish(self::SUCCESS);
    }

    private function runReadinessCheck(): bool
    {
        $exitCode = Artisan::call('ops:redis-check', ['--json' => true]);

        if ($exitCode !== self::SUCCESS) {
            $this->recordFail(
                'Redis readiness (ops:redis-check)',
                'Did not pass — complete Phase 8 before Phase 9. See docs/REDIS-READINESS.md',
            );

            return false;
        }

        return $this->recordPass('Redis readiness (ops:redis-check)', 'All infrastructure checks passed');
    }

    private function checkCacheDriver(): bool
    {
        $driver = (string) config('cache.default');

        if ($driver !== 'redis') {
            return $this->recordFail(
                'Cache driver',
                'CACHE_STORE='.$driver.' — expected redis. Update .env and run php artisan config:cache',
            );
        }

        return $this->recordPass('Cache driver', 'CACHE_STORE=redis');
    }

    private function checkQueueDriver(): bool
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'redis') {
            return $this->recordFail(
                'Queue connection',
                'QUEUE_CONNECTION='.$connection.' — expected redis. Update .env and run php artisan config:cache',
            );
        }

        $retryAfter = (int) config('queue.connections.redis.retry_after', 90);

        return $this->recordPass(
            'Queue connection',
            'QUEUE_CONNECTION=redis (queue='.config('queue.connections.redis.queue', 'default').', retry_after='.$retryAfter.'s)',
        );
    }

    private function checkActiveCacheProbe(): bool
    {
        $key = 'ops:redis-cutover:probe';
        $token = 'ok-'.bin2hex(random_bytes(4));

        try {
            Cache::put($key, $token, 60);
            $read = Cache::get($key);
            Cache::forget($key);

            if ($read !== $token) {
                return $this->recordFail('Active cache (default store)', 'Wrote probe but read mismatch');
            }

            return $this->recordPass('Active cache (default store)', 'Cache::put/get via redis store OK');
        } catch (Throwable $e) {
            return $this->recordFail('Active cache (default store)', $e->getMessage());
        }
    }

    private function checkSessionDriver(): void
    {
        $driver = (string) config('session.driver');

        if ($driver === 'redis') {
            $this->recordWarn(
                'Session driver',
                'SESSION_DRIVER=redis — ensure Redis persistence and monitoring are in place',
            );

            return;
        }

        $this->recordPass(
            'Session driver',
            'SESSION_DRIVER='.$driver.' (recommended: keep database until Redis is stable 48h)',
        );
    }

    private function checkFailedJobs(): bool
    {
        if (! Schema::hasTable('failed_jobs')) {
            return $this->recordPass('Failed jobs', 'failed_jobs table not present (skipped)');
        }

        try {
            $count = (int) DB::table('failed_jobs')->count();

            if ($count > 0) {
                return $this->recordFail(
                    'Failed jobs',
                    $count.' row(s) in failed_jobs — run php artisan queue:failed and resolve before accepting cutover',
                );
            }

            return $this->recordPass('Failed jobs', 'failed_jobs table empty');
        } catch (Throwable $e) {
            return $this->recordFail('Failed jobs', $e->getMessage());
        }
    }

    private function checkWorkerGuidance(): void
    {
        $this->recordPass(
            'Queue worker',
            'Ensure a process runs: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 (see deploy/laravel-queue-worker-redis.supervisor.example)',
        );
    }

    private function recordPass(string $check, string $detail): bool
    {
        $this->results[] = ['check' => $check, 'status' => 'pass', 'detail' => $detail];

        if (! $this->option('json')) {
            $this->line('  <fg=green>✓</> '.$check.': '.$detail);
        }

        return true;
    }

    private function recordWarn(string $check, string $detail): void
    {
        $this->results[] = ['check' => $check, 'status' => 'warn', 'detail' => $detail];

        if (! $this->option('json')) {
            $this->line('  <fg=yellow>!</> '.$check.': '.$detail);
        }
    }

    private function recordFail(string $check, string $detail): bool
    {
        $this->results[] = ['check' => $check, 'status' => 'fail', 'detail' => $detail];

        if (! $this->option('json')) {
            $this->line('  <fg=red>✗</> '.$check.': '.$detail);
        }

        return false;
    }

    private function finish(int $code): int
    {
        $failed = collect($this->results)->where('status', 'fail')->count();
        $warned = collect($this->results)->where('status', 'warn')->count();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $code === self::SUCCESS,
                'failed' => $failed,
                'warned' => $warned,
                'checks' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $code;
        }

        $this->newLine();
        if ($code === self::SUCCESS) {
            $this->info('Phase 9 cutover verification: passed.');
            if ($warned > 0) {
                $this->warn($warned.' warning(s) — review session/monitoring notes in docs/REDIS-CUTOVER.md');
            }
        } else {
            $this->error('Phase 9 cutover verification: '.$failed.' check(s) failed.');
            $this->line('See docs/REDIS-CUTOVER.md');
        }

        return $code;
    }
}
