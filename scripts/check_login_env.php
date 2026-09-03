<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    Illuminate\Support\Facades\Redis::connection()->ping();
    echo "redis: ok\n";
} catch (Throwable $e) {
    echo 'redis: fail '.$e->getMessage()."\n";
}

echo 'users: '.App\Models\User::query()->count()."\n";
echo 'session driver: '.config('session.driver')."\n";
echo 'app url: '.config('app.url')."\n";
