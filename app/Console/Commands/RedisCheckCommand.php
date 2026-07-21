<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisCheckCommand extends Command
{
    protected $signature = 'ops:redis-check
                            {--json : Output results as JSON for scripts}';

    protected $description = 'Verify Redis server, PHP client, cache lock, and queue connection readiness (does not modify .env).';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->recordEnvironmentProfile();

        if (! $this->checkRedisClient()) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkConnection('default', 'Redis default connection (DB '.config('database.redis.default.database', '0').')')) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkReadWrite('default')) {
            return $this->finish(self::FAILURE);
        }

        $cacheConnection = (string) config('cache.stores.redis.connection', 'cache');
        if (! $this->checkConnection($cacheConnection, 'Redis cache connection (DB '.config('database.redis.'.$cacheConnection.'.database', '?').')')) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkCacheLock()) {
            return $this->finish(self::FAILURE);
        }

        if (! $this->checkQueueReadiness()) {
            return $this->finish(self::FAILURE);
        }

        return $this->finish(self::SUCCESS);
    }

    private function recordEnvironmentProfile(): void
    {
        $this->recordPass('Environment (informational)', sprintf(
            'CACHE_STORE=%s, QUEUE_CONNECTION=%s, SESSION_DRIVER=%s, REDIS_CLIENT=%s',
            (string) config('cache.default'),
            (string) config('queue.default'),
            (string) config('session.driver'),
            (string) config('database.redis.client'),
        ));
    }

    private function checkRedisClient(): bool
    {
        $configuredClient = (string) config('database.redis.client', 'phpredis');
        $hasPhpRedis = extension_loaded('redis');
        $hasPredis = class_exists(\Predis\Client::class);

        if ($configuredClient === 'phpredis') {
            if ($hasPhpRedis) {
                $this->recordPass('PHP Redis client', 'phpredis extension loaded');

                return true;
            }

            if ($hasPredis) {
                return $this->recordFail(
                    'PHP Redis client',
                    'REDIS_CLIENT=phpredis but ext-redis is missing. Set REDIS_CLIENT=predis in .env (predis/predis is installed) and re-run.',
                );
            }

            return $this->recordFail(
                'PHP Redis client',
                'REDIS_CLIENT=phpredis but ext-redis is not loaded. Install phpredis or run composer require predis/predis and set REDIS_CLIENT=predis.',
            );
        }

        if ($configuredClient === 'predis') {
            if ($hasPredis) {
                $this->recordPass('PHP Redis client', 'predis/predis available');

                return true;
            }

            return $this->recordFail(
                'PHP Redis client',
                'REDIS_CLIENT=predis but predis/predis is not installed. Run: composer require predis/predis',
            );
        }

        if ($hasPhpRedis || $hasPredis) {
            $this->recordWarn(
                'PHP Redis client',
                'Unknown REDIS_CLIENT='.$configuredClient.'; phpredis='.($hasPhpRedis ? 'yes' : 'no').', predis='.($hasPredis ? 'yes' : 'no'),
            );

            return true;
        }

        return $this->recordFail('PHP Redis client', 'No phpredis extension or predis/predis package found.');
    }

    private function checkConnection(string $connection, string $label): bool
    {
        try {
            $response = Redis::connection($connection)->ping();
            $detail = is_bool($response)
                ? ($response ? 'PONG' : 'unexpected false')
                : (string) $response;

            return $this->recordPass($label, 'ping OK ('.$detail.')');
        } catch (Throwable $e) {
            return $this->recordFail($label, $e->getMessage());
        }
    }

    private function checkReadWrite(string $connection): bool
    {
        $key = 'ops:redis-check:rw:'.getmypid();
        $expected = 'ok-'.bin2hex(random_bytes(4));

        try {
            $redis = Redis::connection($connection);
            $redis->set($key, $expected, 'EX', 30);
            $actual = (string) $redis->get($key);
            $redis->del($key);

            if ($actual !== $expected) {
                return $this->recordFail('Redis read/write', 'Wrote '.$expected.' but read '.$actual);
            }

            return $this->recordPass('Redis read/write', 'SET/GET/DEL on connection "'.$connection.'"');
        } catch (Throwable $e) {
            return $this->recordFail('Redis read/write', $e->getMessage());
        }
    }

    private function checkCacheLock(): bool
    {
        $lockName = 'ops:redis-check:lock:'.bin2hex(random_bytes(4));

        try {
            $lock = Cache::store('redis')->lock($lockName, 10);

            if (! $lock->get()) {
                return $this->recordFail('Cache lock (redis store)', 'Could not acquire lock — another holder or misconfigured lock connection?');
            }

            $lock->release();

            return $this->recordPass(
                'Cache lock (redis store)',
                'Lock acquired via cache store "redis" (lock connection: '.config('cache.stores.redis.lock_connection', 'default').')',
            );
        } catch (Throwable $e) {
            return $this->recordFail('Cache lock (redis store)', $e->getMessage());
        }
    }

    private function checkQueueReadiness(): bool
    {
        $driver = (string) config('queue.connections.redis.driver', 'redis');
        if ($driver !== 'redis') {
            return $this->recordFail('Queue redis connection', 'queue.connections.redis.driver is not "redis".');
        }

        $connection = (string) config('queue.connections.redis.connection', 'default');
        $queueName = (string) config('queue.connections.redis.queue', 'default');
        $retryAfter = (int) config('queue.connections.redis.retry_after', 90);

        try {
            Redis::connection($connection)->ping();

            return $this->recordPass(
                'Queue connection readiness',
                'connection='.$connection.', queue='.$queueName.', retry_after='.$retryAfter.'s (ping OK)',
            );
        } catch (Throwable $e) {
            return $this->recordFail('Queue connection readiness', $e->getMessage());
        }
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
            $this->info('Redis readiness: all checks passed.');
            $this->line('Production cutover still requires manual .env changes — see docs/REDIS-READINESS.md');
            if ($warned > 0) {
                $this->warn($warned.' warning(s) — review before setting CACHE_STORE=redis.');
            }
        } else {
            $this->error('Redis readiness: '.$failed.' check(s) failed.');
            $this->line('Fix infrastructure or keep CACHE_STORE=database and QUEUE_CONNECTION=database.');
            $this->line('See docs/REDIS-READINESS.md');
        }

        return $code;
    }
}
